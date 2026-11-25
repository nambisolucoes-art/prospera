<?php

namespace App\Domains\Colaboradores\Application\Data;

use App\Support\NormalizedName;
use App\Support\PageSize;

/** Filtros da listagem de departamentos e de funções. */
final readonly class CatalogFiltersData
{
    private function __construct(
        public string $search,
        public ?bool $active,
        public string $company,
        public int $pageSize,
    ) {}

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $search = $input['search'] ?? null;
        $status = $input['status'] ?? null;

        return new self(
            is_string($search) ? mb_substr(NormalizedName::display($search), 0, 100) : '',
            match ($status) {
                'active' => true,
                'inactive' => false,
                default => null,
            },
            is_string($input['company'] ?? null) ? mb_substr($input['company'], 0, 40) : '',
            PageSize::fromInput($input['per_page'] ?? null),
        );
    }

    /** @return array{search: string, status: string, company: string} */
    public function toArray(): array
    {
        $status = match ($this->active) {
            true => 'active',
            false => 'inactive',
            null => '',
        };

        return ['search' => $this->search, 'status' => $status, 'company' => $this->company];
    }
}
