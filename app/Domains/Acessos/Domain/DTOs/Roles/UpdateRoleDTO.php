<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Domain\DTOs\Roles;

final readonly class UpdateRoleDTO
{
    public function __construct(
        public string $name,
        public string $availability,
    ) {}
}
