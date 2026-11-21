<?php

namespace App\Domains\Pessoas\Application\Actions;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Pessoas\Application\Data\CommercialTagsData;
use App\Domains\Pessoas\Application\Data\SavePersonData;
use App\Domains\Pessoas\Domain\Enums\CommercialRole;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;

final readonly class UpdateCommercialRelationAction
{
    public function __construct(private SavePersonAction $savePerson, private SaveCommercialTagsAction $saveTags) {}

    /** @param array<string, mixed> $centralChanges */
    public function execute(
        CommercialRole $role,
        Client $account,
        Company $company,
        int $relationId,
        ?CommercialTagsData $tags,
        array $centralChanges,
    ): void {
        AuditLog::transaction(function () use ($role, $account, $company, $relationId, $tags, $centralChanges): void {
            Client::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $lockedCompany = Company::where('account_id', $account->id)->lockForUpdate()->findOrFail($company->id);
            abort_unless($lockedCompany->active, 404);

            $relation = $role->model()::where('account_id', $account->id)
                ->where('company_id', $lockedCompany->id)
                ->lockForUpdate()
                ->findOrFail($relationId);

            if ($centralChanges !== []) {
                $person = Person::where('account_id', $account->id)->lockForUpdate()->findOrFail($relation->person_id);
                $data = SavePersonData::forCommercialReuse($centralChanges, $person);
                $this->savePerson->execute($account, $person, $data);
            }

            if ($tags !== null) {
                $this->saveTags->execute($role, $relation, $tags);
            }
        });
    }
}
