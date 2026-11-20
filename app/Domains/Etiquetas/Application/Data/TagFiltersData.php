<?php

namespace App\Domains\Etiquetas\Application\Data;

use App\Support\PageSize;

final readonly class TagFiltersData
{
    private function __construct(public string $search, public string $module, public ?bool $active, public int $pageSize) {}

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $search = $input['search'] ?? null;
        $module = $input['module'] ?? null;
        $status = $input['status'] ?? null;

        return new self(
            is_string($search) ? mb_substr(trim($search), 0, 100) : '',
            in_array($module, ['people'], true) ? $module : '',
            match ($status) {
                'active' => true,
                'inactive' => false,
                default => null,
            },
            PageSize::fromInput($input['per_page'] ?? null),
        );
    }

    /** @return array{search: string, module: string, status: string} */
    public function toArray(): array
    {
        return ['search' => $this->search, 'module' => $this->module, 'status' => match ($this->active) {
            true => 'active',
            false => 'inactive',
            null => '',
        }];
    }
}
