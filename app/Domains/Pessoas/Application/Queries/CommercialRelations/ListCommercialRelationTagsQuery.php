<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Queries\CommercialRelations;

use App\Domains\Pessoas\Domain\Enums\CommercialRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Listagem de Clientes ou Fornecedores: uma linha por relação, já restrita às empresas autorizadas.
 *
 * As empresas chegam do contexto da URL (unidade aberta ou seleção da área de trabalho) intersectadas com a
 * permissão de listar. Filtro, busca, contagem e paginação acontecem no SQL; duas unidades da mesma empresa
 * não repetem a relação, porque a consulta é por empresa.
 */
final readonly class ListCommercialRelationTagsQuery
{
    /**
     * @param  array<int, int>  $relationIds
     * @return Collection<int, Collection<int, \stdClass>>
     */
    public function execute(CommercialRole $role, int $accountId, array $relationIds): Collection
    {
        if ($relationIds === []) {
            return collect();
        }

        return DB::table($role->table().'_tags as selected')
            ->join('tags', 'tags.id', '=', 'selected.tag_id')
            ->where('selected.account_id', $accountId)
            ->whereIntegerInRaw('selected.relation_id', $relationIds)
            ->orderBy('tags.name')
            ->get(['selected.relation_id', 'tags.id', 'tags.name', 'tags.color'])
            ->groupBy('relation_id');
    }
}
