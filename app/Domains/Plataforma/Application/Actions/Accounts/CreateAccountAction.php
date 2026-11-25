<?php

declare(strict_types=1);

namespace App\Domains\Plataforma\Application\Actions\Accounts;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Organizacao\Application\Actions\Companies\CreateCompanyAction;
use App\Domains\Plataforma\Application\Data\Accounts\CreateAccountData;
use App\Domains\Plataforma\Application\Queries\AccountCodesQuery;
use App\Domains\Plataforma\Domain\DTOs\Accounts\CreateAccountDTO;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Usuarios\Application\Actions\CreateUserAction;
use App\Domains\Usuarios\Application\Actions\SendUserInvitationAction;
use App\Domains\Usuarios\Domain\Enums\PasswordSetup;
use App\Domains\Usuarios\Domain\Enums\UserRole;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Cria a conta, o administrador e a primeira empresa com a unidade principal, numa única transação. */
final readonly class CreateAccountAction
{
    public function __construct(
        private CreateUserAction $createUser,
        private SendUserInvitationAction $invite,
        private CreateCompanyAction $createCompany,
        private AccountCodesQuery $codes,
    ) {}

    /** @throws ValidationException */
    public function execute(#[\SensitiveParameter] CreateAccountData $data): string
    {
        $accountDTO = new CreateAccountDTO(
            name: $data->name,
            email: $data->email,
            plan: $data->plan,
            personType: $data->personType,
            document: $data->document,
            active: $data->active,
            details: $data->details,
        );

        $user = AuditLog::transaction(function () use ($accountDTO, $data) {
            $account = Client::register($accountDTO);

            // O código da URL é gerado pelo Model na criação e não muda depois (E09). Uma colisão concorrente
            // desfaz só o ponto de salvamento e gera o próximo código livre.
            $attempts = 0;

            while (true) {
                try {
                    DB::transaction(fn () => $account->saveOrFail());

                    break;
                } catch (UniqueConstraintViolationException) {
                    $attempts++;
                    $codeTaken = $this->codes->exists((string) $account->code);

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

                    $account->code = null;
                }
            }

            // Toda conta nasce com a primeira empresa e a unidade principal (seção 6.5).
            $this->createCompany->execute($data->companyFor($account), $account->id);

            try {
                return $this->createUser->execute($data->administrator, UserRole::AccountAdministrator, $account);
            } catch (ValidationException $exception) {
                // Os erros do administrador aparecem no grupo correspondente do formulário.
                $errors = [];

                foreach ($exception->errors() as $field => $messages) {
                    $errors['administrator.'.$field] = $messages;
                }

                throw ValidationException::withMessages($errors);
            }
        });

        if ($data->administrator->setup === PasswordSetup::Invite) {
            return $this->invite->execute($user)
                ? 'Conta criada. O link para definir a senha foi enviado ao administrador.'
                : 'Conta criada. O administrador pode solicitar o link em Esqueceu a senha.';
        }

        return 'Conta salva.';
    }
}
