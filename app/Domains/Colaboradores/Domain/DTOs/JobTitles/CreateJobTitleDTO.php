<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Domain\DTOs\JobTitles;

final readonly class CreateJobTitleDTO
{
    public function __construct(
        public string $name,
        public bool $active,
    ) {}
}
