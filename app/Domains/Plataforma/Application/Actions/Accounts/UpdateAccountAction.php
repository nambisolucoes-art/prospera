<?php

declare(strict_types=1);

namespace App\Domains\Plataforma\Application\Actions\Accounts;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Plataforma\Application\Data\Accounts\UpdateAccountData;
use App\Domains\Plataforma\Domain\DTOs\Accounts\UpdateAccountDTO;
use App\Domains\Plataforma\Domain\Models\Client;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/** Edição de uma conta pela plataforma. */
final readonly class UpdateAccountAction
{
    /** @throws ValidationException */
    public function execute(UpdateAccountData $data, Client $client): void
    {
        AuditLog::transaction(function () use ($data, $client): void {
            $accountDTO = new UpdateAccountDTO(
                name: $data->name,
                email: $data->email,
                plan: $data->plan,
                personType: $data->personType,
                document: $data->document,
                active: $data->active,
                details: $data->details,
            );

            $client->revise($accountDTO);

            try {
                $client->saveOrFail();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'document' => 'Já existe uma conta com este documento.',
                ]);
            }
        });
    }
}
