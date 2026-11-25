<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Actions;

use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Organizacao\Application\Queries\Companies\LockActiveCompaniesByCodeQuery;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Pessoas\Application\Actions\People\CreatePersonAction;
use App\Domains\Pessoas\Application\Actions\People\UpdatePersonAction;
use App\Domains\Pessoas\Application\Data\CommercialRelations\RegisterCommercialRelationData;
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

    public function execute(RegisterCommercialRelationData $data, Client $account, Company $company): void
    {
        AuditLog::transaction(function () use ($data, $account, $company): void {
            $role = $data->role;

            Client::whereKey($account->id)->lockForUpdate()->firstOrFail();

            $targets = $this->lockActiveCompaniesByCode->execute(
                $account->id,
                [$company->code, ...$data->additionalCompanies],
            );

            foreach ($targets as $target) {
                if (! $role->allows($this->permissions, auth()->user(), 'create', $target->id)) {
                    throw new OperationNotAllowed;
                }
            }

            if (is_int($data->person)) {
                if (! $data->centralChanges->isEmpty()) {
                    if ($this->permissions->nodesFor(auth()->user(), 'account.people.view')->isEmpty()) {
                        throw new OperationNotAllowed;
                    }
                    $existing = Person::where('account_id', $account->id)->lockForUpdate()->findOrFail($data->person);
                    $changes = $data->centralChanges->validatedFor($existing);
                    $this->updatePerson->execute($changes, $existing, $account);
                }

                $personId = $data->person;
            } else {
                $personId = $this->createPerson->execute($data->person, $account)->id;
            }

            foreach ($targets as $target) {
                $created = $this->set->execute(
                    $role,
                    $target,
                    $personId,
                    active: true,
                    link: $data->additionalCompanies === [] ? true : null,
                );

                if ($created && $data->tags->ids !== []) {
                    $relation = $role->model()::where('account_id', $account->id)
                        ->where('company_id', $target->id)
                        ->where('person_id', $personId)
                        ->firstOrFail();

                    $this->syncTags->execute($data->tags, $role, $relation);
                }
            }
        });
    }
}
