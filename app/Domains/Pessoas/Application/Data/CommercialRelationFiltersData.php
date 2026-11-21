<?php

namespace App\Domains\Pessoas\Application\Data;

use App\Domains\Organizacao\Domain\DTOs\OrganizationCode;
use App\Support\PageSize;

final readonly class CommercialRelationFiltersData
{
    private function __construct(
        public string $search,
        public ?bool $active,
        public ?string $company,
        public int $pageSize,
    ) {}

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $search = $input['search'] ?? null;
        $status = $input['status'] ?? null;
        $company = $input['empresa'] ?? null;
        $validCompany = is_string($company) && preg_match('/^'.OrganizationCode::ROUTE_PATTERN.'$/', $company) === 1;

        return new self(
            is_string($search) ? mb_substr(trim($search), 0, 100) : '',
            match ($status) {
                'active' => true,
                'inactive' => false,
                default => null,
            },
            $validCompany ? $company : (is_string($company) && $company !== '' ? '' : null),
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

        return [
            'search' => $this->search,
            'status' => $status,
            'company' => $this->company ?? '',
        ];
    }
}
