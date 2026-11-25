<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Domain\DTOs\Employees;

final readonly class UpdateEmployeeDTO
{
    public function __construct(
        public bool $active,
        public ?string $workEmail,
        public ?string $workPhone,
        public ?string $workWhatsapp,
    ) {}
}
