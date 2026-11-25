<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Domain\DTOs\Roles;

final readonly class CreateRoleDTO
{
    public function __construct(
        public string $name,
        public string $availability,
    ) {}
}
