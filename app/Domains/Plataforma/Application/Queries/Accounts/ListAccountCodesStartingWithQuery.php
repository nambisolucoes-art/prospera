<?php

declare(strict_types=1);

namespace App\Domains\Plataforma\Application\Queries\Accounts;

use App\Domains\Plataforma\Domain\Models\Client;

final readonly class ListAccountCodesStartingWithQuery
{
    /** @return list<string> */
    public function execute(string $base): array
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], substr($base, 0, 20));

        $codes = Client::query()
            ->where(fn ($query) => $query
                ->where('code', $base)
                ->orWhereLike('code', $escaped.'%'))
            ->pluck('code')
            ->map(fn (mixed $code): string => (string) $code)
            ->all();

        return array_values($codes);
    }
}
