<?php

namespace App\Domains\Pessoas\Application\Actions;

use App\Domains\Acessos\Application\Actions\SaveRoleAssignmentsAction;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Clientes\Application\Actions\SaveCustomerAction;
use App\Domains\Fornecedores\Application\Actions\SaveSupplierAction;
use App\Domains\Pessoas\Application\Data\SavePersonData;
use App\Domains\Pessoas\Application\Queries\PersonAccessQuery;
use App\Domains\Pessoas\Application\Queries\PersonDocumentExistsQuery;
use App\Domains\Pessoas\Domain\DTOs\PersonDTO;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Usuarios\Application\Actions\CreateUserAction;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class SavePersonAction
{
    public function __construct(
        private SavePersonAccessAction $saveAccess,
        private SaveRoleAssignmentsAction $assignRole,
        private CreateUserAction $createUser,
        private SaveSupplierAction $saveSupplier,
        private SaveCustomerAction $saveCustomer,
        private PersonDocumentExistsQuery $documentExists,
        private PersonAccessQuery $permissions,
        private EffectivePermissions $effective,
    ) {}

    public function execute(Client $account, ?Person $person, SavePersonData $data): Person
    {
        return AuditLog::transaction(function () use ($account, $person, $data): Person {
            $account->newQuery()
                ->whereKey($account->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedPerson = $person === null
                ? null
                : Person::where('account_id', $account->id)
                    ->lockForUpdate()
                    ->findOrFail($person->id);

            $operator = auth()->user();

            if ($lockedPerson === null) {
                abort_if($this->effective->nodesFor($operator, 'account.people.create')->isEmpty(), 403);
            } else {
                abort_unless($this->permissions->canUpdate($operator, $lockedPerson), 403);
            }

            $personDTO = new PersonDTO(
                name: $data->values['name'],
                email: $data->values['email'] ?? null,
                phone: $data->values['phone'] ?? null,
                personType: $data->values['person_type'],
                document: $data->values['document'] ?? null,
                tradeName: $data->values['trade_name'] ?? null,
                stateRegistration: $data->values['state_registration'] ?? null,
                municipalRegistration: $data->values['municipal_registration'] ?? null,
                contactName: $data->values['contact_name'] ?? null,
                whatsapp: $data->values['whatsapp'] ?? null,
                website: $data->values['website'] ?? null,
                address: $data->values['address'] ?? null,
                additionalPhones: $data->values['additional_phones'] ?? [],
                additionalEmails: $data->values['additional_emails'] ?? [],
            );

            if ($lockedPerson === null) {
                $lockedPerson = Person::register($personDTO, $account->id);
            } else {
                $lockedPerson->revise($personDTO);
            }

            $user = $lockedPerson->user()->first();

            if ($user !== null) {
                $this->saveAccess->execute($account, $lockedPerson, $user, $data);
            }

            if (
                $data->access !== null
                || ($user !== null && isset($data->values['role_id']))
            ) {
                Gate::authorize('account.users.assign-role');

                $roleId = $data->access->roleId ?? (int) $data->values['role_id'];
                $this->assignRole->assertAssignable(accountId: $account->id, roleId: $roleId);

                if ($user !== null) {
                    $this->assignRole->replaceTenant($user, $roleId);
                }
            }

            if (
                $lockedPerson->document !== null
                && $this->documentExists->execute(
                    accountId: $account->id,
                    document: $lockedPerson->document,
                    ignoreId: $lockedPerson->id,
                )
            ) {
                throw ValidationException::withMessages([
                    'document' => 'Já existe uma pessoa com este documento nesta conta.',
                ]);
            }

            $lockedPerson->saveOrFail();

            if ($data->access !== null) {
                Gate::authorize('account.users.create');

                if ($user !== null) {
                    throw ValidationException::withMessages([
                        'tag_ids' => 'Esta pessoa já possui acesso. Atualize o cadastro antes de continuar.',
                    ]);
                }

                $this->createUser->execute(
                    data: $data->access->user,
                    role: $data->access->role,
                    account: $account,
                    person: $lockedPerson,
                    roleId: $data->access->roleId,
                    node: AccessNode::tenant($account->id),
                );
            }

            $this->saveSupplier->execute($lockedPerson, $data->supplierSelected);
            $this->saveCustomer->execute($lockedPerson, $data->customerSelected);

            $lockedPerson->assignTags($data->values['tag_ids'] ?? []);

            return $lockedPerson;
        });
    }
}
