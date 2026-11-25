<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Usuarios\Application\Actions\CreateUserAction;
use App\Domains\Usuarios\Application\Data\CreateUserData;
use App\Domains\Usuarios\Domain\Enums\UserRole;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

final class CreateUserCommand extends Command
{
    protected $signature = 'auth:create-user
        {--role= : platform-admin, account-admin ou account-member}
        {--account= : ID da conta para usuários vinculados}
        {--name= : Nome do usuário}
        {--email= : E-mail do usuário}';

    protected $description = 'Cria um usuário no contexto informado';

    public function handle(CreateUserAction $create): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Execute em um terminal interativo para informar a senha.');

            return self::FAILURE;
        }

        $roleValue = $this->option('role') ?? $this->choice('Papel', array_column(UserRole::cases(), 'value'));
        $role = is_string($roleValue) ? UserRole::tryFrom($roleValue) : null;
        if ($role === null) {
            $this->error('Papel inválido.');

            return self::FAILURE;
        }

        $accountId = $this->option('account') ?? ($role->requiresAccount() ? $this->ask('ID da conta') : null);
        $account = (is_string($accountId) || is_int($accountId)) && ctype_digit((string) $accountId)
            ? Client::query()->find($accountId)
            : null;
        if (($role->requiresAccount() && ! $account) || (! $role->requiresAccount() && $accountId !== null)) {
            $this->error('Informe uma conta válida apenas para um papel vinculado à conta.');

            return self::FAILURE;
        }

        $name = (string) ($this->option('name') ?? $this->ask('Nome'));
        $email = (string) ($this->option('email') ?? $this->ask('E-mail'));
        $password = (string) $this->secret('Senha (mínimo de 12 caracteres)');
        $confirmation = (string) $this->secret('Confirme a senha');

        try {
            $data = CreateUserData::fromInput([
                'name' => $name, 'email' => $email, 'mode' => 'manual',
                'password' => $password, 'password_confirmation' => $confirmation,
            ], minimumPasswordLength: 12);

            $create->execute($data, $role, $account);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }

        $this->info('Usuário criado.');

        return self::SUCCESS;
    }
}
