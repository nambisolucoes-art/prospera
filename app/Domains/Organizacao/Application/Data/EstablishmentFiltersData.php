<?php

namespace App\Domains\Organizacao\Application\Data;

use App\Support\PageSize;

final readonly class EstablishmentFiltersData
{
    private function __construct(
        public string $search,
        public ?int $companyId,
        public ?bool $active,
        public int $pageSize,
    ) {}

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $search = $input['search'] ?? null;
        $status = $input['status'] ?? null;
        $companyId = filter_var($input['company'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return new self(
            is_string($search) ? mb_substr(trim($search), 0, 100) : '',
            $companyId === false ? null : $companyId,
            match ($status) {
                'active' => true,
                'inactive' => false,
                default => null,
            },
            PageSize::fromInput($input['per_page'] ?? null),
        );
    }

    /** @return array{search: string, company: string, status: string} */
    public function toArray(): array
    {
        $status = match ($this->active) {
            true => 'active',
            false => 'inactive',
            null => '',
        };

        return [
            'search' => $this->search,
            'company' => $this->companyId === null ? '' : (string) $this->companyId,
            'status' => $status,
        ];
    }
}
