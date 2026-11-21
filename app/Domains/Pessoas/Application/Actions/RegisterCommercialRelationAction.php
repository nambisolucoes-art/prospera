<?php

namespace App\Domains\Pessoas\Application\Actions;

use App\Domains\Auditoria\Application\Actions\AuditLog;
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
    ) {}

    /** @param array<string,mixed> $centralChanges */
    public function execute(
        CommercialRole $role,
        Client $account,
        Company $company,
        int|SavePersonData $person,
        CommercialTagsData $selection,
        array $centralChanges = [],
    ): void {
        AuditLog::transaction(function () use ($role, $account, $company, $person, $selection, $centralChanges): void {
            Client::whereKey($account->id)->lockForUpdate()->firstOrFail();

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

            $this->set->execute($role, $company, $personId, active: true, link: true);

            if ($selection->ids === []) {
                return;
            }

            $relation = $role->model()::where('account_id', $account->id)
                ->where('company_id', $company->id)
                ->where('person_id', $personId)
                ->firstOrFail();

            $this->saveTags->execute($role, $relation, $selection);
        });
    }
}
