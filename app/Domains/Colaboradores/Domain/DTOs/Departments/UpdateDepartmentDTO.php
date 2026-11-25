<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Domain\DTOs\Departments;

final readonly class UpdateDepartmentDTO
{
    public function __construct(
        public string $name,
        public bool $active,
    ) {}
}
