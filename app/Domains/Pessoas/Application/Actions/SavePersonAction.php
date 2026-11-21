<?php

namespace App\Domains\Pessoas\Application\Actions;

use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Pessoas\Application\Data\SavePersonData;
use App\Domains\Pessoas\Application\Queries\PersonAccessQuery;
use App\Domains\Pessoas\Application\Queries\PersonDocumentExistsQuery;
use App\Domains\Pessoas\Domain\DTOs\PersonDTO;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use Illuminate\Validation\ValidationException;

final readonly class SavePersonAction
{
    public function __construct(
        private PersonDocumentExistsQuery $documentExists,
        private PersonAccessQuery $permissions,
        private EffectivePermissions $effective,
    ) {}

    public function execute(Client $account, ?Person $person, SavePersonData $data): Person
    {
        return AuditLog::transaction(function () use ($account, $person, $data): Person {
            $account->newQuery()
                ->whereKey($account->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedPerson = $person === null
                ? null
                : Person::where('account_id', $account->id)
                    ->lockForUpdate()
                    ->findOrFail($person->id);

            $operator = auth()->user();
            $authority = null;

            if ($lockedPerson === null) {
                abort_if($this->effective->nodesFor($operator, 'account.people.create')->isEmpty(), 403);
            } else {
                // Avaliada antes das mudanças desta operação, com as relações ativas atuais.
                $authority = $this->permissions->authority($operator, $lockedPerson);
                abort_unless($authority->allowed, 403);
            }

            $personDTO = new PersonDTO(
                name: $data->values['name'],
                email: $data->values['email'] ?? null,
                phone: $data->values['phone'] ?? null,
                personType: $data->values['person_type'],
                document: $data->values['document'] ?? null,
                tradeName: $data->values['trade_name'] ?? null,
                stateRegistration: $data->values['state_registration'] ?? null,
                municipalRegistration: $data->values['municipal_registration'] ?? null,
                contactName: $data->values['contact_name'] ?? null,
                whatsapp: $data->values['whatsapp'] ?? null,
                website: $data->values['website'] ?? null,
                address: $data->values['address'] ?? null,
                additionalPhones: $data->values['additional_phones'] ?? [],
                additionalEmails: $data->values['additional_emails'] ?? [],
            );

            if ($lockedPerson === null) {
                $lockedPerson = Person::register($personDTO, $account->id);
            } else {
                $lockedPerson->revise($personDTO);
            }

            // Um envio forjado não altera dados centrais fora da autoridade do operador (seção 10.2.1).
            if ($authority !== null) {
                $changesIdentification = $lockedPerson->isDirty(Person::IDENTIFICATION_FIELDS);
                $changesContacts = $lockedPerson->isDirty(Person::CONTACT_FIELDS);

                abort_if($changesIdentification && ! $authority->identification, 403);
                abort_if($changesContacts && ! $authority->contacts, 403);
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
