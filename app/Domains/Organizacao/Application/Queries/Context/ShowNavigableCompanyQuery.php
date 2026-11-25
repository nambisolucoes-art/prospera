<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Queries\Context;

use App\Domains\Organizacao\Domain\ValueObjects\NavigableCompany;
use Illuminate\Support\Facades\DB;

/**
 * Resolve códigos de URL e enumera os contextos navegáveis, sempre dentro da conta informada.
 */
final readonly class ShowNavigableCompanyQuery
{
    public function execute(int $accountId, string $code): ?NavigableCompany
    {
        $row = DB::table('companies')
            ->where('account_id', $accountId)
            ->where('code', $code)
            ->first(['id', 'code', 'name', 'trade_name', 'active']);

        if ($row === null) {
            return null;
        }

        return new NavigableCompany(
            id: (int) $row->id,
            code: (string) $row->code,
            name: (string) ($row->trade_name ?: $row->name),
            active: (bool) $row->active,
        );
    }
}
