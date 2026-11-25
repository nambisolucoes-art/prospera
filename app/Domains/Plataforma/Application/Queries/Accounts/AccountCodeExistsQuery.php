<?php

declare(strict_types=1);

namespace App\Domains\Plataforma\Application\Queries\Accounts;

use App\Domains\Plataforma\Domain\Models\Client;

final readonly class AccountCodeExistsQuery
{
    public function execute(string $code): bool
    {
        return Client::where('code', $code)->exists();
    }
}
