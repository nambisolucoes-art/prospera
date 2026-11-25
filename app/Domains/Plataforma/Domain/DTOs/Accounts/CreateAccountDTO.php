<?php

declare(strict_types=1);

namespace App\Domains\Plataforma\Domain\DTOs\Accounts;

final readonly class CreateAccountDTO
{
    /** @param array<string,mixed>|null $details */
    public function __construct(
        public string $name,
        public ?string $email,
        public ?string $plan,
        public string $personType,
        public string $document,
        public bool $active,
        public ?array $details,
    ) {}
}
