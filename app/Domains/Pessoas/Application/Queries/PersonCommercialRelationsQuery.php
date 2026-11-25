<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Queries;

use Illuminate\Support\Facades\DB;

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
}
