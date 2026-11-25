<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Domain\DTOs\Establishments;

final readonly class UpdateEstablishmentDTO
{
    /**
     * @param  array<string,string|null>|null  $address
     * @param  array<int,string>  $additionalPhones
     * @param  array<int,string>  $additionalEmails
     */
    public function __construct(
        public string $name,
        public ?string $tradeName,
        public ?string $taxDocument,
        public ?string $stateRegistration,
        public ?string $municipalRegistration,
        public bool $isHeadquarters,
        public bool $active,
        public ?array $address,
        public ?string $phone,
        public ?string $whatsapp,
        public ?string $email,
        public array $additionalPhones,
        public array $additionalEmails,
    ) {}
}
