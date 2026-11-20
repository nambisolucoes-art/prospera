<?php

namespace App\Domains\Pessoas\Domain\DTOs;

final readonly class PersonDetailsDTO
{
    /** @param array<string,mixed> $attributes */
    public function __construct(public array $attributes) {}
}
