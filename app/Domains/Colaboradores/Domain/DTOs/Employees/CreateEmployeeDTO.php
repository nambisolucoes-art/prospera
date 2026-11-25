<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Domain\DTOs\Employees;

final readonly class CreateEmployeeDTO
{
    public function __construct(
        public ?string $workEmail,
        public ?string $workPhone,
        public ?string $workWhatsapp,
    ) {}
}
