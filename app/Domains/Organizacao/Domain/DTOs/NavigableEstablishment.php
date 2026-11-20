<?php

namespace App\Domains\Organizacao\Domain\DTOs;

/**
 * Estabelecimento resolvido para o contexto de navegação (E06), com a empresa proprietária.
 */
final readonly class NavigableEstablishment
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public bool $active,
        public bool $primary,
        public string $classification,
        public NavigableCompany $company,
    ) {}

    /** @return array<string, string|bool> */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'primary' => $this->primary,
            'classification' => $this->classification,
            'company_code' => $this->company->code,
            'company_name' => $this->company->name,
        ];
    }
}
