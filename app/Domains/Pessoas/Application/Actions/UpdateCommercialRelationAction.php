<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Actions;

use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Organizacao\Application\Queries\CompanyDirectoryQuery;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Pessoas\Application\Data\CommercialTagsData;
use App\Domains\Pessoas\Application\Data\SavePersonData;
use App\Domains\Pessoas\Domain\Enums\CommercialRole;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;

final readonly class UpdateCommercialRelationAction
{
    public function __construct(
        private SavePersonAction $savePerson,
        private SaveCommercialTagsAction $saveTags,
        private SetCommercialRelationAction $set,
        private CompanyDirectoryQuery $companies,
        private EffectivePermissions $permissions,
    ) {}

    /**
     * @param  array<string,mixed>  $centralChanges
     * @param  list<string>  $addCompanies
     * @param  list<string>  $removeCompanies
     */
    public function execute(
        CommercialRole $role,
        Client $account,
        Company $company,
        int $relationId,
        ?CommercialTagsData $tags,
        array $centralChanges,
        ?bool $active = null,
        array $addCompanies = [],
        array $removeCompanies = [],
    ): void {
        AuditLog::transaction(function () use (
            $role, $account, $company, $relationId, $tags, $centralChanges, $active, $addCompanies, $removeCompanies,
        ): void {
            Client::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $lockedCompany = Company::where('account_id', $account->id)->lockForUpdate()->findOrFail($company->id);
            abort_unless($lockedCompany->active, 404);

            $relation = $role->model()::where('account_id', $account->id)
                ->where('company_id', $lockedCompany->id)
                ->findOrFail($relationId);

            // A ação da listagem também trava a pessoa antes de escrever o vínculo.
            $person = Person::where('account_id', $account->id)->lockForUpdate()->findOrFail($relation->person_id);
            $relation->refresh();

            if ($centralChanges !== []) {
                $data = SavePersonData::forCommercialReuse($centralChanges, $person, allowIdentificationChange: true);
                $this->savePerson->execute($account, $person, $data);
            }

            if ($active !== null && $relation->active !== $active) {
                $this->set->execute($role, $lockedCompany, $relation->person_id, active: $active, link: false);
            }

            $targets = $this->companies->lockActiveCodes($account->id, [...$addCompanies, ...$removeCompanies]);
            foreach ($targets as $target) {
                $selected = in_array($target->code, $addCompanies, true);
                $user = auth()->user();
                $action = $selected ? 'activate' : 'deactivate';
                $allowed = $role->allows($this->permissions, $user, $action, $target->id)
                    || ($selected && $role->allows($this->permissions, $user, 'create', $target->id));
                abort_unless($allowed, 403);

                $this->set->execute($role, $target, $relation->person_id, active: $selected, link: $selected ? null : false);
            }

            if ($tags !== null) {
                $this->saveTags->execute($role, $relation, $tags);
            }
        });
    }
}
