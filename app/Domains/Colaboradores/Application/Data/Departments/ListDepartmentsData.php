<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Data\Departments;

use App\Support\NormalizedName;
use App\Support\PageSize;

/** Filtros da listagem de departamentos. */
final readonly class ListDepartmentsData
{
    public function __construct(
        public string $search,
        public ?bool $active,
        public string $company,
        public int $pageSize,
    ) {}

    /** @param array<string,mixed> $input */
    public static function fromInput(array $input): self
    {
        $search = $input['search'] ?? null;
        $company = $input['company'] ?? null;

        return new self(
            search: is_string($search) ? mb_substr(NormalizedName::display($search), 0, 100) : '',
            active: match ($input['status'] ?? null) {
                'active' => true,
                'inactive' => false,
                default => null,
            },
            company: is_string($company) ? mb_substr($company, 0, 40) : '',
            pageSize: PageSize::fromInput($input['per_page'] ?? null),
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
