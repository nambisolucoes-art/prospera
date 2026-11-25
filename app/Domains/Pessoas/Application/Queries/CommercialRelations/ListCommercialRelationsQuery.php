<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Queries\CommercialRelations;

use App\Domains\Pessoas\Application\Data\CommercialRelationFiltersData;
use App\Domains\Pessoas\Domain\Enums\CommercialRole;
use App\Support\TaxDocument;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Listagem de Clientes ou Fornecedores: uma linha por relação, já restrita às empresas autorizadas.
 *
 * As empresas chegam do contexto da URL (unidade aberta ou seleção da área de trabalho) intersectadas com a
 * permissão de listar. Filtro, busca, contagem e paginação acontecem no SQL; duas unidades da mesma empresa
 * não repetem a relação, porque a consulta é por empresa.
 */
final readonly class ListCommercialRelationsQuery
{
    /**
     * @param  list<int>  $companyIds
     * @return LengthAwarePaginator<int, \stdClass>
     */
    public function execute(
        CommercialRole $role,
        int $accountId,
        array $companyIds,
        CommercialRelationFiltersData $filters,
        string $path,
    ): LengthAwarePaginator {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters->search);
        $needle = '%'.$escaped.'%';
        $document = TaxDocument::normalize($filters->search);

        return DB::table($role->table().' as r')
            ->join('people as p', function ($join): void {
                $join->on('p.id', '=', 'r.person_id')->on('p.account_id', '=', 'r.account_id');
            })
            ->join('companies as c', function ($join): void {
                $join->on('c.id', '=', 'r.company_id')->on('c.account_id', '=', 'r.account_id');
            })
            ->where('r.account_id', $accountId)
            ->whereIn('r.company_id', $companyIds)
            ->when($filters->active !== null, fn ($query) => $query->where('r.active', $filters->active))
            ->when($filters->search !== '', fn ($query) => $query->where(fn ($match) => $match
                ->whereLike('p.name', $needle)
                ->orWhereLike('p.email', $needle)
                ->when(strlen($document) >= 11, fn ($fiscal) => $fiscal->orWhere('p.document', $document))))
            ->select([
                'r.id',
                'r.person_id',
                'r.company_id',
                'r.active',
                'p.name',
                'p.person_type',
                'p.document',
                'p.email',
                'c.code as company_code',
                DB::raw('coalesce(c.trade_name, c.name) as company_name'),
                'c.active as company_active',
            ])
            ->orderBy('p.name')
            ->orderBy('r.id')
            ->paginate($filters->pageSize)
            ->withPath($path)
            ->withQueryString();
    }
}
