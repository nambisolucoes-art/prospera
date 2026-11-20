<?php

namespace App\Domains\Pessoas\Application\Actions;

use App\Domains\Acessos\Application\Actions\AssignRoleAction;
use App\Domains\Pessoas\Application\Data\SavePersonData;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Usuarios\Application\Queries\UserEmailExistsQuery;
use App\Domains\Usuarios\Domain\DTOs\UserIdentityDTO;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class SavePersonAccessAction
{
    public function __construct(
        private AssignRoleAction $assignRole,
        private UserEmailExistsQuery $emailExists,
    ) {}

    public function execute(Client $account, Person $person, User $user, SavePersonData $data): void
    {
        if ($user->access_enabled && ! $data->userSelected) {
            Gate::authorize('account.users.revoke');

            if ($user->isLastAdministratorWithAccess()) {
                throw ValidationException::withMessages([
                    'tag_ids' => 'Mantenha ao menos um administrador da conta com acesso.',
                ]);
            }
        }

        if (! $user->access_enabled && $data->userSelected) {
            Gate::authorize('account.users.create');

            $this->assignRole->assertAssignable(
                accountId: $account->id,
                roleId: (int) ($data->values['role_id'] ?? $user->role_id),
            );
        }

        $user->access_enabled = $data->userSelected;

        if ($person->isDirty(['name', 'email'])) {
            Gate::authorize('account.users.update');
        }

        if ($this->emailExists->execute($person->email, ignoreId: $user->id)) {
            throw ValidationException::withMessages([
                'email' => 'Já existe um usuário com este e-mail.',
            ]);
        }

        $identityDTO = new UserIdentityDTO(
            name: $person->name,
            email: $person->email,
        );

        $user->applyIdentity($identityDTO);
        $user->saveOrFail();
    }
}
