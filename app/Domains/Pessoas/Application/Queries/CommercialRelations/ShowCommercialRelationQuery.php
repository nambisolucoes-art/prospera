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
final readonly class ShowCommercialRelationQuery
{
    /** @return array{person_id: int, active: bool}|null */
    public function execute(CommercialRole $role, int $accountId, int $companyId, int $relationId): ?array
    {
        $relation = DB::table($role->table())
            ->where('account_id', $accountId)
            ->where('company_id', $companyId)
            ->where('id', $relationId)
            ->first(['person_id', 'active']);

        return $relation === null ? null : ['person_id' => (int) $relation->person_id, 'active' => (bool) $relation->active];
    }
}
