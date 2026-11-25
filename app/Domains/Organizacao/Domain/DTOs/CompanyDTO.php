<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Domain\DTOs;

final readonly class CompanyDTO
{
    public function __construct(
        public string $name,
        public ?string $tradeName,
        public ?string $personType,
        public ?string $taxRoot,
        public ?string $individualDocument,
        public ?string $taxRegime,
        public ?string $mainCnae,
        public bool $active,
    ) {}
}
