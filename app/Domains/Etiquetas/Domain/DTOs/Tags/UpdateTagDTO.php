<?php

declare(strict_types=1);

namespace App\Domains\Etiquetas\Domain\DTOs\Tags;

final readonly class UpdateTagDTO
{
    public function __construct(
        public string $name,
        public string $module,
        public string $color,
        public bool $active,
    ) {}
}
