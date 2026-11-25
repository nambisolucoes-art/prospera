<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Actions\People;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Pessoas\Application\Data\People\UpdatePersonData;
use App\Domains\Pessoas\Application\Queries\PersonAccessQuery;
use App\Domains\Pessoas\Application\Queries\PersonDocumentExistsQuery;
use App\Domains\Pessoas\Domain\DTOs\People\UpdatePersonDTO;
use App\Domains\Pessoas\Domain\Exceptions\PersonChangeNotAllowed;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use Illuminate\Validation\ValidationException;

/** Altera os dados centrais de uma pessoa, só dentro da autoridade do operador (seção 10.2.1). */
final readonly class UpdatePersonAction
{
    public function __construct(
        private PersonDocumentExistsQuery $documentExists,
        private PersonAccessQuery $permissions,
    ) {}

    /**
     * @throws PersonChangeNotAllowed
     * @throws ValidationException
     */
    public function execute(UpdatePersonData $data, Person $person, Client $account): Person
    {
        return AuditLog::transaction(function () use ($data, $person, $account): Person {
            $account->newQuery()
                ->whereKey($account->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedPerson = Person::where('account_id', $account->id)
                ->lockForUpdate()
                ->findOrFail($person->id);

            // Avaliada antes das mudanças desta operação, com as relações ativas atuais.
            $authority = $this->permissions->authority(auth()->user(), $lockedPerson);

            if (! $authority->allowed) {
                throw new PersonChangeNotAllowed;
            }

            $personDTO = new UpdatePersonDTO(
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

            $lockedPerson->revise($personDTO);

            // Um envio forjado não altera dados centrais fora da autoridade do operador (seção 10.2.1).
            $changesIdentification = $lockedPerson->isDirty(Person::IDENTIFICATION_FIELDS);
            $changesContacts = $lockedPerson->isDirty(Person::CONTACT_FIELDS);

            if ($changesIdentification && ! $authority->identification) {
                throw new PersonChangeNotAllowed;
            }

            if ($changesContacts && ! $authority->contacts) {
                throw new PersonChangeNotAllowed;
            }

            if (
                $lockedPerson->document !== null
                && $this->documentExists->execute(
                    accountId: $account->id,
                    document: $lockedPerson->document,
                    ignoreId: $lockedPerson->id,
                )
            ) {
                throw ValidationException::withMessages([
                    'document' => 'Já existe uma pessoa com este documento nesta conta.',
                ]);
            }

            $lockedPerson->saveOrFail();

            return $lockedPerson;
        });
    }
}
