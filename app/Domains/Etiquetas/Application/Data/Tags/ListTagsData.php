<?php

declare(strict_types=1);

namespace App\Domains\Etiquetas\Application\Data\Tags;

use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Support\PageSize;

final readonly class ListTagsData
{
    public function __construct(
        public string $search,
        public string $module,
        public ?bool $active,
        public int $pageSize,
    ) {}

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $search = $input['search'] ?? null;
        $module = $input['module'] ?? null;
        $status = $input['status'] ?? null;

        return new self(
            is_string($search) ? mb_substr(trim($search), 0, 100) : '',
            in_array($module, Tag::MODULES, true) ? $module : '',
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
        $status = match ($this->active) {
            true => 'active',
            false => 'inactive',
            null => '',
        };

        return [
            'search' => $this->search,
            'module' => $this->module,
            'status' => $status,
        ];
    }
}
