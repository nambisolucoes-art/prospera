<?php

namespace App\Domains\Usuarios\Application\Actions;

use App\Domains\Acessos\Application\Actions\SaveRoleAssignmentsAction;
use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Usuarios\Application\Data\CreateUserData;
use App\Domains\Usuarios\Application\Queries\UserEmailExistsQuery;
use App\Domains\Usuarios\Domain\DTOs\UserIdentityDTO;
use App\Domains\Usuarios\Domain\Enums\PasswordSetup;
use App\Domains\Usuarios\Domain\Enums\UserRole;
use App\Domains\Usuarios\Domain\Exceptions\IdentityCreationUnavailable;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class CreateUserAction
{
    public function __construct(
        private UserEmailExistsQuery $emailExists,
        private SaveRoleAssignmentsAction $assignments,
    ) {}

    public function execute(
        #[\SensitiveParameter] CreateUserData $data,
        UserRole $role,
        ?Client $account = null,
        ?Person $person = null,
        ?int $roleId = null,
        ?AccessNode $node = null,
    ): User {
        return AuditLog::transaction(function () use ($data, $role, $account, $person, $roleId, $node): User {
            if ($role->requiresAccount() !== ($account !== null)) {
                throw new InvalidArgumentException('O contexto da conta não corresponde ao papel do usuário.');
            }

            if ($node !== null) {
                abort_unless($account?->id === $node->accountId && $roleId !== null, 403);
                Client::whereKey($node->accountId)->lockForUpdate()->firstOrFail();
                Gate::authorize('account.users.create', $node);
                $this->assignments->assertAssignable($node->accountId, $roleId, $node);
            }

            if ($this->emailExists->execute($data->email)) {
                if ($node !== null) {
                    throw new IdentityCreationUnavailable;
                }

                throw ValidationException::withMessages(['email' => 'Não foi possível criar o acesso com os dados informados.']);
            }

            $password = $data->setup === PasswordSetup::Invite ? Str::random(48) : $data->password;

            if ($password === null || $password === '') {
                throw ValidationException::withMessages(['password' => 'Informe a senha inicial.']);
            }

            $identityDTO = new UserIdentityDTO(
                name: $data->name,
                email: $data->email,
            );

            $user = new User(['password' => $password]);
            $user->applyIdentity($identityDTO);
            $user->account()->associate($account);

            if ($node !== null) {
                $user->prepareInitialAssignment($node);
            }

            if ($person !== null) {
                if ($person->account_id !== $account?->id || $person->user()->exists()) {
                    throw new InvalidArgumentException('Pessoa indisponível para este acesso.');
                }

                $user->person()->associate($person);
            }

            $assignedRole = $roleId === null
                ? null
                : Role::where('account_id', $account?->id)->findOrFail($roleId);

            if ($assignedRole !== null) {
                $user->role_id = $assignedRole->id;
            }

            $user->forceFill([
                'is_saas_admin' => $role === UserRole::PlatformAdministrator,
                'is_account_admin' => $assignedRole !== null
                    ? $assignedRole->system_key === 'administrator'
                    : $role === UserRole::AccountAdministrator,
                'email_verified_at' => now(),
                'password_setup_pending' => $data->setup === PasswordSetup::Invite,
            ]);

            try {
                $user->saveOrFail();
            } catch (UniqueConstraintViolationException) {
                if ($node !== null) {
                    throw new IdentityCreationUnavailable;
                }

                throw ValidationException::withMessages(['email' => 'Não foi possível criar o acesso com os dados informados.']);
            }

            return $user;
        });
    }
}
