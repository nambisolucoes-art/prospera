<?php

namespace App\Domains\Plataforma\Domain\DTOs;

final readonly class PointDTO
{
    public function __construct(
        public string $name,
        public bool $active,
    ) {}
}
