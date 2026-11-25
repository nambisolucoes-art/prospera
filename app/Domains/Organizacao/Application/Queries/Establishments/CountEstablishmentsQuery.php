<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Queries\Establishments;

use App\Domains\Organizacao\Domain\Models\Establishment;

final readonly class CountEstablishmentsQuery
{
    public function execute(int $accountId): int
    {
        return Establishment::where('account_id', $accountId)->count();
    }
}
