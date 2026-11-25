<?php

declare(strict_types=1);

namespace App\Domains\Plataforma\Application\Queries\Memberships;

use Illuminate\Support\Facades\DB;

final readonly class CountSelectableAccountsQuery
{
    /** Quantidade de contas que a identidade pode abrir, para decidir entre a entrada direta e a escolha. */
    public function execute(int $userId): int
    {
        return DB::table('account_memberships as m')
            ->join('clients as c', 'c.id', '=', 'm.account_id')
            ->where('m.user_id', $userId)
            ->where('m.status', 'active')
            ->where('c.active', true)
            ->count();
    }
}
