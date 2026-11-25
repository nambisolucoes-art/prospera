<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Actions;

use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Organizacao\Application\Queries\Companies\LockActiveCompaniesByCodeQuery;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Pessoas\Application\Actions\People\UpdatePersonAction;
use App\Domains\Pessoas\Application\Data\CommercialRelations\UpdateCommercialRelationData;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Support\Exceptions\OperationNotAllowed;
use App\Support\Exceptions\ResourceNotAvailable;

final readonly class UpdateCommercialRelationAction
{
    public function __construct(
        private UpdatePersonAction $updatePerson,
        private SyncCommercialTagsAction $syncTags,
        private SetCommercialRelationAction $set,
        private LockActiveCompaniesByCodeQuery $lockActiveCompaniesByCode,
        private EffectivePermissions $permissions,
    ) {}

    public function execute(UpdateCommercialRelationData $data, Client $account, Company $company): void
    {
        AuditLog::transaction(function () use ($data, $account, $company): void {
            $role = $data->role;

            if (! $role->allows($this->permissions, auth()->user(), 'update', $company->id)) {
                throw new OperationNotAllowed;
            }

            Client::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $lockedCompany = Company::where('account_id', $account->id)->lockForUpdate()->findOrFail($company->id);

            if (! $lockedCompany->active) {
                throw new ResourceNotAvailable;
            }

            $relation = $role->model()::where('account_id', $account->id)
                ->where('company_id', $lockedCompany->id)
                ->findOrFail($data->relationId);

            // A ação da listagem também trava a pessoa antes de escrever o vínculo.
            $person = Person::where('account_id', $account->id)->lockForUpdate()->findOrFail($relation->person_id);
            $relation->refresh();

            if (! $data->centralChanges->isEmpty()) {
                if ($this->permissions->nodesFor(auth()->user(), 'account.people.view')->isEmpty()) {
                    throw new OperationNotAllowed;
                }

                $changes = $data->centralChanges->validatedFor($person);
                $this->updatePerson->execute($changes, $person, $account);
            }

            if ($data->active !== null && $relation->active !== $data->active) {
                $this->set->execute($role, $lockedCompany, $relation->person_id, active: $data->active, link: false);
            }

            $targets = $this->lockActiveCompaniesByCode->execute(
                $account->id,
                [...$data->addCompanies, ...$data->removeCompanies],
            );

            foreach ($targets as $target) {
                $selected = in_array($target->code, $data->addCompanies, true);
                $user = auth()->user();
                $action = $selected ? 'activate' : 'deactivate';
                $allowed = $role->allows($this->permissions, $user, $action, $target->id)
                    || ($selected && $role->allows($this->permissions, $user, 'create', $target->id));

                if (! $allowed) {
                    throw new OperationNotAllowed;
                }

                $this->set->execute(
                    $role,
                    $target,
                    $relation->person_id,
                    active: $selected,
                    link: $selected ? null : false,
                );
            }

            if ($data->tags !== null) {
                $this->syncTags->execute($data->tags, $role, $relation);
            }
        });
    }
}
