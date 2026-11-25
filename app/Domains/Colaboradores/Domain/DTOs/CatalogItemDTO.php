<?php

namespace App\Domains\Colaboradores\Domain\DTOs;

/** Valores de um departamento ou de uma função da empresa. */
final readonly class CatalogItemDTO
{
    public function __construct(
        public string $name,
        public bool $active,
    ) {}
}
