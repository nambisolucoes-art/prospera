<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Domain\DTOs\Assignments;

final readonly class UpdateEmployeeAssignmentDTO
{
    public function __construct(
        public ?int $departmentId,
        public ?int $jobTitleId,
        public string $startsOn,
        public ?string $endsOn,
        public string $contactMode,
        /** @var array{email: string|null, phone: string|null, whatsapp: string|null} */
        public array $workContacts,
    ) {}
}
