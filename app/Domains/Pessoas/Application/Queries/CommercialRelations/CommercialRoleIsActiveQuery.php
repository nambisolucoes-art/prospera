<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Queries\CommercialRelations;

use App\Domains\Pessoas\Domain\Enums\CommercialRole;
use Illuminate\Support\Facades\DB;

/**
 * Listagem de Clientes ou Fornecedores: uma linha por relação, já restrita às empresas autorizadas.
 *
 * As empresas chegam do contexto da URL (unidade aberta ou seleção da área de trabalho) intersectadas com a
 * permissão de listar. Filtro, busca, contagem e paginação acontecem no SQL; duas unidades da mesma empresa
 * não repetem a relação, porque a consulta é por empresa.
 */
final readonly class CommercialRoleIsActiveQuery
{
    public function execute(CommercialRole $role, int $accountId, int $companyId, int $personId): bool
    {
        return DB::table($role->table())->where('account_id', $accountId)->where('company_id', $companyId)
            ->where('person_id', $personId)->where('active', true)->exists();
    }
}
