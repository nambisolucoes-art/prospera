<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Domain\DTOs\Profile;

final readonly class SaveProfileSectionDTO
{
    /** @param array<string,mixed>|null $value */
    public function __construct(
        public string $mode,
        public ?array $value,
    ) {}
}
