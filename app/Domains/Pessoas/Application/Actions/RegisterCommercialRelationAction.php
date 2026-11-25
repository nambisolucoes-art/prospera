<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Actions;

use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Organizacao\Application\Queries\Companies\LockActiveCompaniesByCodeQuery;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Pessoas\Application\Actions\People\CreatePersonAction;
use App\Domains\Pessoas\Application\Actions\People\UpdatePersonAction;
use App\Domains\Pessoas\Application\Data\CommercialTagsData;
use App\Domains\Pessoas\Application\Data\People\CreatePersonData;
use App\Domains\Pessoas\Application\Data\People\UpdatePersonData;
use App\Domains\Pessoas\Domain\Enums\CommercialRole;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Support\Exceptions\OperationNotAllowed;

final readonly class RegisterCommercialRelationAction
{
    public function __construct(
        private CreatePersonAction $createPerson,
        private UpdatePersonAction $updatePerson,
        private SetCommercialRelationAction $set,
        private SyncCommercialTagsAction $syncTags,
        private LockActiveCompaniesByCodeQuery $lockActiveCompaniesByCode,
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
        int|CreatePersonData $person,
        CommercialTagsData $selection,
        array $centralChanges = [],
        array $additionalCompanies = [],
    ): void {
        AuditLog::transaction(function () use (
            $role, $account, $company, $person, $selection, $centralChanges, $additionalCompanies,
        ): void {
            Client::whereKey($account->id)->lockForUpdate()->firstOrFail();

            $targets = $this->lockActiveCompaniesByCode->execute($account->id, [$company->code, ...$additionalCompanies]);
            foreach ($targets as $target) {
                if (! $role->allows($this->permissions, auth()->user(), 'create', $target->id)) {
                    throw new OperationNotAllowed;
                }
            }

            if (is_int($person)) {
                if ($centralChanges !== []) {
                    $existing = Person::where('account_id', $account->id)->lockForUpdate()->findOrFail($person);
                    $changes = UpdatePersonData::forCommercialReuse($centralChanges, $existing);
                    $this->updatePerson->execute($changes, $existing, $account);
                }

                $personId = $person;
            } else {
                $personId = $this->createPerson->execute($person, $account)->id;
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
                    $this->syncTags->execute($role, $relation, $selection);
                }
            }
        });
    }
}
