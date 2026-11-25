<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Queries;

use App\Domains\Pessoas\Domain\Enums\DerivedRole;
use Illuminate\Support\Facades\DB;

/**
 * Relações comerciais de uma pessoa, sempre filtradas pela conta e pelas empresas autorizadas no SQL.
 */
final class PersonCommercialRelationsQuery
{
    /**
     * Empresas com relação ativa da pessoa em qualquer papel derivado: Colaborador, Cliente ou Fornecedor.
     *
     * @return list<int>
     */
    public function activeCompanyIds(int $accountId, int $personId): array
    {
        $union = null;

        foreach (DerivedRole::cases() as $role) {
            $roleCompanies = DB::table($role->table())
                ->where('account_id', $accountId)
                ->where('person_id', $personId)
                ->where('active', true)
                ->select('company_id');

            $union = $union === null ? $roleCompanies : $union->union($roleCompanies);
        }

        return array_values($union->pluck('company_id')->map(fn ($id): int => (int) $id)->all());
    }
}
