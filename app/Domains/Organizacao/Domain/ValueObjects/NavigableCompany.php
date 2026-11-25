<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Domain\ValueObjects;

/**
 * Empresa resolvida para o contexto de navegação (E06). Não carrega dados fiscais.
 */
final readonly class NavigableCompany
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public bool $active,
    ) {}

    /** @return array{code: string, name: string} */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
        ];
    }
}
