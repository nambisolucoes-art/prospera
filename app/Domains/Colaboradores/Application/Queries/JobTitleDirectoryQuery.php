<?php

namespace App\Domains\Colaboradores\Application\Queries;

use App\Domains\Acessos\Domain\DTOs\AuthorizedNodes;
use App\Domains\Colaboradores\Application\Data\CatalogFiltersData;
use App\Domains\Colaboradores\Domain\Models\JobTitle;
use App\Support\NormalizedName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Listagem e leitura de funções sempre dentro da conta e das empresas autorizadas no SQL.
 *
 * O descritor da permissão decide quais empresas aparecem: a conta inteira ou só as empresas atribuídas.
 * Busca, filtros, contagem e paginação acontecem no banco, antes de paginar.
 */
final class JobTitleDirectoryQuery
{
    /** @return LengthAwarePaginator<int, JobTitle> */
    public function list(
        int $accountId,
        AuthorizedNodes $nodes,
        CatalogFiltersData $filters,
        string $path,
    ): LengthAwarePaginator {
        $key = NormalizedName::key($filters->search);
        $needle = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $key).'%';

        $query = JobTitle::query()
            ->with('company:id,code,name,trade_name')
            ->select(['id', 'company_id', 'name', 'active', 'updated_at'])
            ->where('account_id', $accountId)
            ->when($filters->active !== null, fn (Builder $query) => $query->where('active', $filters->active))
            ->when($key !== '', fn (Builder $query) => $query->whereLike('normalized_name', $needle))
            ->when($filters->company !== '', fn (Builder $query) => $query->whereIn(
                'company_id',
                DB::table('companies')
                    ->where('account_id', $accountId)
                    ->where('code', $filters->company)
                    ->select('id'),
            ));

        $nodes->apply($query, 'company_id', null);

        return $query
            ->orderBy('normalized_name')
            ->orderBy('company_id')
            ->orderBy('id')
            ->paginate($filters->pageSize)
            ->withPath($path)
            ->withQueryString();
    }

    public function find(int $accountId, AuthorizedNodes $nodes, int $id): ?JobTitle
    {
        $query = JobTitle::query()
            ->with('company:id,code,name,trade_name')
            ->where('account_id', $accountId)
            ->whereKey($id);

        $nodes->apply($query, 'company_id', null);

        return $query->first();
    }
}
