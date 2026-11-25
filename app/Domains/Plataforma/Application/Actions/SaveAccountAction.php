<?php

declare(strict_types=1);

namespace App\Domains\Plataforma\Application\Actions;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Organizacao\Application\Actions\CreateCompanyAction;
use App\Domains\Plataforma\Application\Data\SaveAccountData;
use App\Domains\Plataforma\Application\Queries\AccountCodesQuery;
use App\Domains\Plataforma\Domain\DTOs\AccountDTO;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Usuarios\Application\Actions\CreateUserAction;
use App\Domains\Usuarios\Application\Actions\SendUserInvitationAction;
use App\Domains\Usuarios\Domain\Enums\PasswordSetup;
use App\Domains\Usuarios\Domain\Enums\UserRole;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveAccountAction
{
    public function __construct(
        private CreateUserAction $createUser,
        private SendUserInvitationAction $invite,
        private CreateCompanyAction $createCompany,
        private AccountCodesQuery $codes,
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

        $user = AuditLog::transaction(function () use ($client, $accountDTO, $administrator, $data) {
            if ($client->exists) {
                $client->revise($accountDTO);
            } else {
                $client = Client::register($accountDTO);
            }

            // O código da URL é gerado pelo Model na criação e não muda depois (E09). Uma colisão concorrente
            // desfaz só o ponto de salvamento e gera o próximo código livre.
            $attempts = 0;

            while (true) {
                try {
                    DB::transaction(fn () => $client->saveOrFail());

                    break;
                } catch (UniqueConstraintViolationException) {
                    $attempts++;
                    $codeTaken = ! $client->exists && $this->codes->exists((string) $client->code);

                    if ($codeTaken && $attempts >= 5) {
                        throw ValidationException::withMessages([
                            'name' => 'Não foi possível gerar um código único. Tente novamente.',
                        ]);
                    }

                    if (! $codeTaken) {
                        throw ValidationException::withMessages([
                            'document' => 'Já existe uma conta com este documento.',
                        ]);
                    }

                    $client->code = null;
                }
            }

            if ($administrator === null) {
                return null;
            }

            // Toda conta nasce com a primeira empresa e a unidade principal (seção 6.5).
            $companyData = $data->companyFor($client);
            $this->createCompany->execute($client->id, $companyData);

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
