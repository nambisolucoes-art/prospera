<?php

declare(strict_types=1);

namespace App\Domains\Plataforma\Application\Actions\Accounts;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Plataforma\Application\Data\Accounts\UpdateAccountProfileData;
use App\Domains\Plataforma\Domain\DTOs\Accounts\UpdateAccountDTO;
use App\Domains\Plataforma\Domain\Models\Client;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/** Edição dos dados da própria conta. */
final readonly class UpdateAccountProfileAction
{
    /** @throws ValidationException */
    public function execute(UpdateAccountProfileData $data, Client $client): void
    {
        AuditLog::transaction(function () use ($data, $client): void {
            $accountDTO = new UpdateAccountDTO(
                name: $data->name,
                email: $data->email,
                plan: $client->plan,
                personType: $client->person_type,
                document: $client->document,
                active: $client->active,
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
