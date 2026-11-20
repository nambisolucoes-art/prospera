<?php

namespace App\Domains\Usuarios\Application\Actions;

use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Pessoas\Application\Actions\DirectoryAudit;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Usuarios\Application\Data\CreateUserData;
use App\Domains\Usuarios\Domain\DTOs\UserIdentityDTO;
use App\Domains\Usuarios\Domain\Enums\PasswordSetup;
use App\Domains\Usuarios\Domain\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class CreateUserAction
{
    public function execute(
        #[\SensitiveParameter] CreateUserData $data,
        UserRole $role,
        ?Client $account = null,
        ?Person $person = null,
        ?int $roleId = null,
    ): User {
        return AuditLog::transaction(function () use ($data, $role, $account, $person, $roleId): User {
            if ($role->requiresAccount() !== ($account !== null)) {
                throw new InvalidArgumentException('O contexto da conta não corresponde ao papel do usuário.');
            }

            if (User::query()->whereRaw('lower(email) = ?', [$data->email])->exists()) {
                throw $this->duplicateEmail();
            }

            $password = $data->setup === PasswordSetup::Invite ? Str::random(48) : $data->password;

            if ($password === null || $password === '') {
                throw ValidationException::withMessages(['password' => 'Informe a senha inicial.']);
            }

            $user = new User(['password' => $password]);
            $user->applyIdentity(new UserIdentityDTO($data->name, $data->email));
            $user->account()->associate($account);
            if ($person) {
                if ($person->account_id !== $account?->id || $person->user()->exists()) {
                    throw new InvalidArgumentException('Pessoa indisponível para este acesso.');
                }
                $user->person()->associate($person);
            }

            if ($roleId !== null) {
                $assignedRole = Role::where('account_id', $account?->id)->findOrFail($roleId);
                $user->role_id = $assignedRole->id;
            }
            $user->forceFill([
                'is_saas_admin' => $role === UserRole::PlatformAdministrator,
                'is_account_admin' => $roleId !== null ? $assignedRole->system_key === 'administrator' : $role === UserRole::AccountAdministrator,
                'email_verified_at' => now(),
            ]);

            try {
                $user->saveOrFail();
            } catch (UniqueConstraintViolationException) {
                throw $this->duplicateEmail();
            }

            if ($account) {
                DirectoryAudit::record($account->id, 'user.created', 'user', $user->id, ['person_id' => $user->person_id, 'role_id' => $user->role_id]);
            }

            return $user;
        });
    }

    private function duplicateEmail(): ValidationException
    {
        return ValidationException::withMessages(['email' => 'Já existe um usuário com este e-mail.']);
    }
}
