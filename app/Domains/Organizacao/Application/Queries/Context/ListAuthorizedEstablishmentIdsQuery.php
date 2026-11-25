<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Queries\Context;

use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Resolve códigos de URL e enumera os contextos navegáveis, sempre dentro da conta informada.
 */
final readonly class ListAuthorizedEstablishmentIdsQuery
{
    private function available(int $accountId, AuthorizedNodes $nodes): Builder
    {
        $query = $this->establishments($accountId)
            ->where('e.active', true)
            ->where('c.active', true);

        if (! $nodes->tenant) {
            $query->where(fn (Builder $covered) => $covered
                ->whereIn('e.company_id', $nodes->companies)
                ->orWhereIn('e.id', $nodes->establishments));
        }

        return $query;
    }

    private function establishments(int $accountId): Builder
    {
        return DB::table('establishments as e')
            ->join('companies as c', function ($join): void {
                $join->on('c.id', '=', 'e.company_id')->on('c.account_id', '=', 'e.account_id');
            })
            ->where('e.account_id', $accountId)
            ->select([
                'e.id',
                'e.code',
                'e.name',
                'e.active',
                'e.is_primary',
                'e.is_headquarters',
                DB::raw('case when e.tax_document is null then 0 else 1 end as registered'),
                'c.id as company_id',
                'c.code as company_code',
                'c.name as company_name',
                'c.trade_name as company_trade_name',
                'c.active as company_active',
            ]);
    }

    /**
     * @param  list<string>  $codes
     * @return list<int>
     */
    public function execute(int $accountId, AuthorizedNodes $nodes, array $codes): array
    {
        if ($nodes->isEmpty()) {
            return [];
        }

        $ids = $this->available($accountId, $nodes)
            ->whereIn('e.code', $codes)
            ->select('e.id')
            ->pluck('e.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values($ids);
    }
}
