<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Actions\People;

use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Pessoas\Application\Data\People\CreatePersonData;
use App\Domains\Pessoas\Application\Queries\PersonDocumentExistsQuery;
use App\Domains\Pessoas\Domain\DTOs\People\CreatePersonDTO;
use App\Domains\Pessoas\Domain\Exceptions\PersonChangeNotAllowed;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use Illuminate\Validation\ValidationException;

/** Cadastra uma pessoa na conta. Exige a permissão de cadastrar pessoas em algum nó. */
final readonly class CreatePersonAction
{
    public function __construct(
        private PersonDocumentExistsQuery $documentExists,
        private EffectivePermissions $effective,
    ) {}

    /**
     * @throws PersonChangeNotAllowed
     * @throws ValidationException
     */
    public function execute(CreatePersonData $data, Client $account): Person
    {
        return AuditLog::transaction(function () use ($data, $account): Person {
            $account->newQuery()
                ->whereKey($account->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->effective->nodesFor(auth()->user(), 'account.people.create')->isEmpty()) {
                throw new PersonChangeNotAllowed;
            }

            $personDTO = new CreatePersonDTO(
                name: $data->name,
                email: $data->email,
                phone: $data->phone,
                personType: $data->personType,
                document: $data->document,
                tradeName: $data->tradeName,
                stateRegistration: $data->stateRegistration,
                municipalRegistration: $data->municipalRegistration,
                contactName: $data->contactName,
                whatsapp: $data->whatsapp,
                website: $data->website,
                address: $data->address,
                additionalPhones: $data->additionalPhones,
                additionalEmails: $data->additionalEmails,
            );

            $person = Person::register($personDTO, $account->id);

            if (
                $person->document !== null
                && $this->documentExists->execute(accountId: $account->id, document: $person->document)
            ) {
                throw ValidationException::withMessages([
                    'document' => 'Já existe uma pessoa com este documento nesta conta.',
                ]);
            }

            $person->saveOrFail();

            return $person;
        });
    }
}
