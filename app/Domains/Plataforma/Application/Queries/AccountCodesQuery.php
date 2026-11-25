<?php

declare(strict_types=1);

namespace App\Domains\Plataforma\Application\Queries;

use App\Domains\Plataforma\Domain\Models\Client;

final class AccountCodesQuery
{
    /** @return list<string> */
    public function startingWith(string $base): array
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

    public function exists(string $code): bool
    {
        return Client::where('code', $code)->exists();
    }
}
