<?php

namespace App\Domains\Plataforma\Domain\DTOs;

final readonly class AccountDetailsDTO
{
    /** @param array{email?: ?string, plan?: ?string, person_type?: ?string, document?: ?string, active?: bool|int|string, details?: array<string, mixed>} $attributes */
    public function __construct(public string $name, public array $attributes) {}
}
