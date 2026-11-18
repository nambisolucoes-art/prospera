<?php

namespace App\Domains\Plataforma\Domain\DTOs;

final readonly class PointConfigurationDTO
{
    public function __construct(public string $name, public bool $active) {}
}
