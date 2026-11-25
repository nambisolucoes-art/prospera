<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Domain\DTOs\Assignments;

final readonly class CreateEmployeeAssignmentDTO
{
    public function __construct(
        public int $establishmentId,
        public ?int $departmentId,
        public ?int $jobTitleId,
        public string $startsOn,
        public ?string $endsOn,
        public bool $isPrimary,
    ) {}
}
