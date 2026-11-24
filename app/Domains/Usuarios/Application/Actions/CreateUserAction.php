<?php

namespace App\Domains\Usuarios\Application\Actions;

use App\Domains\Acessos\Application\Actions\DefaultRolesAction;
use App\Domains\Acessos\Application\Actions\RegisterAccountMembershipAction;
use App\Domains\Acessos\Application\Actions\SaveRoleAssignmentsAction;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Domain\DTOs\AccessNode;
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
        private DefaultRolesAction $defaultRoles,
        private RegisterAccountMembershipAction $registerMembership,
        private EffectivePermissions $permissions,
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
            if ($role->requiresAccount() !== ($account !== null) || ($node === null) !== ($roleId === null)) {
                throw new InvalidArgumentException('O contexto da conta não corresponde ao papel do usuário.');
            }

            $assignedRole = null;

            if ($node !== null) {
                abort_unless($account?->id === $node->accountId && $roleId !== null, 403);
                Client::whereKey($node->accountId)->lockForUpdate()->firstOrFail();
                $this->permissions->forget();
                Gate::authorize('account.users.create', $node);
                $assignedRole = $this->assignments->assertAssignable($node->accountId, $roleId, $node);
            }

            if ($this->emailExists->execute($data->email)) {
                if ($node !== null) {
                    throw new IdentityCreationUnavailable;
                }

                throw ValidationException::withMessages([
                    'email' => 'Não foi possível criar o acesso com os dados informados.',
                ]);
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
            $user->provisionFor($account?->id);

            if ($person !== null && ($person->account_id !== $account?->id || $person->user()->exists())) {
                throw ValidationException::withMessages(['person_id' => 'Pessoa indisponível para este acesso.']);
            }

            $user->forceFill([
                'is_saas_admin' => $role === UserRole::PlatformAdministrator,
                'email_verified_at' => now(),
                'password_setup_pending' => $data->setup === PasswordSetup::Invite,
            ]);

            try {
                $user->saveOrFail();
            } catch (UniqueConstraintViolationException) {
                if ($node !== null) {
                    throw new IdentityCreationUnavailable;
                }

                throw ValidationException::withMessages([
                    'email' => 'Não foi possível criar o acesso com os dados informados.',
                ]);
            }

            if ($account !== null) {
                if ($assignedRole === null) {
                    [$administrator, $member] = $this->defaultRoles->execute($account->id);
                    $assignedRole = $role === UserRole::AccountAdministrator ? $administrator : $member;
                }

                $this->registerMembership->execute(
                    $user,
                    $assignedRole,
                    $node ?? AccessNode::tenant($account->id),
                    $person,
                );
            }

            return $user;
        });
    }
}
