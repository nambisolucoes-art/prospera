<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Queries\Context;

use Illuminate\Support\Facades\DB;

/**
 * Resolve códigos de URL e enumera os contextos navegáveis, sempre dentro da conta informada.
 */
final readonly class ListPreferredWorkspaceCodesQuery
{
    /** @return list<string> */
    public function execute(int $accountId, int $userId): array
    {
        $value = DB::table('account_memberships')
            ->where('account_id', $accountId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->value('workspace_establishments');

        return $value === null ? [] : json_decode($value, true, flags: JSON_THROW_ON_ERROR);
    }
}
