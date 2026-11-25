<?php

declare(strict_types=1);

namespace App\Domains\Usuarios\Domain\DTOs;

final readonly class UserIdentityDTO
{
    public function __construct(public string $name, public string $email) {}
}
