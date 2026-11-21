<?php

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

final readonly class RegisterCommercialRelationAction
{
    public function __construct(
        private SavePersonAction $savePerson,
        private SetCommercialRelationAction $set,
        private SaveCommercialTagsAction $saveTags,
        private CompanyDirectoryQuery $companies,
        private EffectivePermissions $permissions,
    ) {}

    /**
     * @param  array<string,mixed>  $centralChanges
     * @param  list<string>  $additionalCompanies
     */
    public function execute(
        CommercialRole $role,
        Client $account,
        Company $company,
        int|SavePersonData $person,
        CommercialTagsData $selection,
        array $centralChanges = [],
        array $additionalCompanies = [],
    ): void {
        AuditLog::transaction(function () use (
            $role, $account, $company, $person, $selection, $centralChanges, $additionalCompanies,
        ): void {
            Client::whereKey($account->id)->lockForUpdate()->firstOrFail();

            $targets = $this->companies->lockActiveCodes($account->id, [$company->code, ...$additionalCompanies]);
            foreach ($targets as $target) {
                abort_unless($role->allows($this->permissions, auth()->user(), 'create', $target->id), 403);
            }

            if (is_int($person)) {
                if ($centralChanges !== []) {
                    $existing = Person::where('account_id', $account->id)->lockForUpdate()->findOrFail($person);
                    $changes = SavePersonData::forCommercialReuse($centralChanges, $existing);
                    $this->savePerson->execute($account, $existing, $changes);
                }

                $personId = $person;
            } else {
                $personId = $this->savePerson->execute($account, null, $person)->id;
            }

            foreach ($targets as $target) {
                $created = $this->set->execute(
                    $role, $target, $personId, active: true, link: $additionalCompanies === [] ? true : null,
                );
                if ($created && $selection->ids !== []) {
                    $relation = $role->model()::where('account_id', $account->id)
                        ->where('company_id', $target->id)
                        ->where('person_id', $personId)
                        ->firstOrFail();
                    $this->saveTags->execute($role, $relation, $selection);
                }
            }
        });
    }
}
