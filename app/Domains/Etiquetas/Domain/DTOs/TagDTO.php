<?php

namespace App\Domains\Etiquetas\Domain\DTOs;

final readonly class TagDTO
{
    public function __construct(
        public string $name,
        public string $module,
        public string $color,
        public bool $active,
    ) {}
}
