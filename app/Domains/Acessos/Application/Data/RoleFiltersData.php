<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Data;

use App\Support\PageSize;

final readonly class RoleFiltersData
{
    private function __construct(public string $search, public string $origin, public int $pageSize) {}

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $search = $input['search'] ?? null;
        $origin = $input['origin'] ?? null;

        return new self(
            is_string($search) ? mb_substr(trim($search), 0, 100) : '',
            in_array($origin, ['system', 'custom'], true) ? $origin : '',
            PageSize::fromInput($input['per_page'] ?? null),
        );
    }

    /** @return array{search: string, origin: string} */
    public function toArray(): array
    {
        return ['search' => $this->search, 'origin' => $this->origin];
    }
}
