<?php

namespace App\Domains\Plataforma\Application\Actions;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Plataforma\Application\Data\SaveAccountData;
use App\Domains\Plataforma\Domain\DTOs\AccountDTO;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Usuarios\Application\Actions\CreateUserAction;
use App\Domains\Usuarios\Application\Actions\SendUserInvitationAction;
use App\Domains\Usuarios\Domain\Enums\PasswordSetup;
use App\Domains\Usuarios\Domain\Enums\UserRole;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

final readonly class SaveAccountAction
{
    public function __construct(
        private CreateUserAction $createUser,
        private SendUserInvitationAction $invite,
    ) {}

    public function execute(Client $client, #[\SensitiveParameter] SaveAccountData $data): string
    {
        $administrator = $client->exists ? null : $data->administrator;

        $accountDTO = new AccountDTO(
            name: $data->name,
            email: $data->email,
            plan: $data->plan,
            personType: $data->personType,
            document: $data->document,
            active: $data->active,
            details: $data->details,
        );

        $user = AuditLog::transaction(function () use ($client, $accountDTO, $administrator) {
            if ($client->exists) {
                $client->revise($accountDTO);
            } else {
                $client = Client::register($accountDTO);
            }

            try {
                $client->saveOrFail();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['document' => 'Já existe uma conta com este documento.']);
            }

            if ($administrator === null) {
                return null;
            }

            try {
                return $this->createUser->execute($administrator, UserRole::AccountAdministrator, $client);
            } catch (ValidationException $exception) {
                // Os erros do administrador aparecem no grupo correspondente do formulário.
                $errors = [];

                foreach ($exception->errors() as $field => $messages) {
                    $errors['administrator.'.$field] = $messages;
                }

                throw ValidationException::withMessages($errors);
            }
        });

        if ($user !== null && $administrator->setup === PasswordSetup::Invite) {
            return $this->invite->execute($user)
                ? 'Conta criada. O link para definir a senha foi enviado ao administrador.'
                : 'Conta criada. O administrador pode solicitar o link em Esqueceu a senha.';
        }

        return 'Conta salva.';
    }
}
