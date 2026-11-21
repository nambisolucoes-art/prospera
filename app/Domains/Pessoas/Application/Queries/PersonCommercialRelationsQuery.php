<?php

namespace App\Domains\Pessoas\Application\Queries;

use App\Domains\Acessos\Domain\DTOs\AuthorizedNodes;
use App\Domains\Pessoas\Domain\Enums\CommercialRole;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Relações comerciais de uma pessoa, sempre filtradas pela conta e pelas empresas autorizadas no SQL.
 */
final class PersonCommercialRelationsQuery
{
    /** @return list<int> */
    public function activeCompanyIds(int $accountId, int $personId): array
    {
        $customers = DB::table('customers')
            ->where('account_id', $accountId)
            ->where('person_id', $personId)
            ->where('active', true)
            ->select('company_id');

        $suppliers = DB::table('suppliers')
            ->where('account_id', $accountId)
            ->where('person_id', $personId)
            ->where('active', true)
            ->select('company_id');

        $ids = $customers->union($suppliers)
            ->pluck('company_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return array_values($ids);
    }

    /**
     * Relações que o leitor pode ver: cada papel só nas empresas em que ele tem a permissão de visualizar.
     *
     * @return Collection<int, stdClass>
     */
    public function visible(
        int $accountId,
        int $personId,
        AuthorizedNodes $customers,
        AuthorizedNodes $suppliers,
    ): Collection {
        $branches = [];

        if (! $customers->isEmpty()) {
            $branches[] = $this->branch(CommercialRole::Customer, $accountId, $personId, $customers);
        }

        if (! $suppliers->isEmpty()) {
            $branches[] = $this->branch(CommercialRole::Supplier, $accountId, $personId, $suppliers);
        }

        if ($branches === []) {
            return collect();
        }

        $query = array_shift($branches);

        foreach ($branches as $branch) {
            $query->unionAll($branch);
        }

        return $query->get();
    }

    private function branch(CommercialRole $role, int $accountId, int $personId, AuthorizedNodes $nodes): Builder
    {
        $query = DB::table($role->table().' as r')
            ->join('companies as c', function ($join): void {
                $join->on('c.id', '=', 'r.company_id')->on('c.account_id', '=', 'r.account_id');
            })
            ->where('r.account_id', $accountId)
            ->where('r.person_id', $personId)
            ->select([
                DB::raw("'".$role->value."' as role"),
                'r.id',
                'r.company_id',
                'r.active',
                'c.code as company_code',
                DB::raw('coalesce(c.trade_name, c.name) as company_name'),
                'c.active as company_active',
            ]);

        if (! $nodes->tenant) {
            $query->whereIn('r.company_id', $nodes->companies);
        }

        return $query;
    }
}
