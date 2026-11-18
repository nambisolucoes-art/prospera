<?php

namespace App\Domains\Usuarios\Application\Actions;

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
    ): User {
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

        $user->forceFill([
            'is_saas_admin' => $role === UserRole::PlatformAdministrator,
            'is_account_admin' => $role === UserRole::AccountAdministrator,
            'email_verified_at' => now(),
        ]);

        try {
            $user->saveOrFail();
        } catch (UniqueConstraintViolationException) {
            throw $this->duplicateEmail();
        }

        return $user;
    }

    private function duplicateEmail(): ValidationException
    {
        return ValidationException::withMessages(['email' => 'Já existe um usuário com este e-mail.']);
    }
}
