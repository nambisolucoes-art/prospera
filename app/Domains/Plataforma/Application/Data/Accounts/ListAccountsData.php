<?php

declare(strict_types=1);

namespace App\Domains\Plataforma\Application\Data\Accounts;

use App\Support\PageSize;

final readonly class ListAccountsData
{
    public function __construct(public string $search, public int $pageSize) {}

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $search = $input['q'] ?? null;

        return new self(
            is_string($search) ? mb_substr(trim($search), 0, 100) : '',
            PageSize::fromInput($input['per_page'] ?? null),
        );
    }
}
