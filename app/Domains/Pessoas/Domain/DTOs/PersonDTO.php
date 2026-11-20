<?php

namespace App\Domains\Pessoas\Domain\DTOs;

final readonly class PersonDTO
{
    /**
     * @param  array<string,string|null>|null  $address
     * @param  array<int,string>  $additionalPhones
     * @param  array<int,string>  $additionalEmails
     */
    public function __construct(
        public string $name,
        public ?string $email,
        public ?string $phone,
        public string $personType,
        public ?string $document,
        public ?string $tradeName,
        public ?string $stateRegistration,
        public ?string $municipalRegistration,
        public ?string $contactName,
        public ?string $whatsapp,
        public ?string $website,
        public ?array $address,
        public array $additionalPhones,
        public array $additionalEmails,
    ) {}
}
