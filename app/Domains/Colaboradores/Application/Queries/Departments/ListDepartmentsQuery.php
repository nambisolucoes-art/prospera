<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Queries\Departments;

use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use App\Domains\Colaboradores\Application\Data\Departments\ListDepartmentsData;
use App\Domains\Colaboradores\Domain\Models\Department;
use App\Support\NormalizedName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Lista departamentos dentro da conta e das empresas que o descritor da permissão autoriza, no SQL.
 *
 * Busca, filtros, contagem e paginação acontecem no banco, antes de paginar.
 */
final readonly class ListDepartmentsQuery
{
    /** @return LengthAwarePaginator<int, Department> */
    public function execute(
        int $accountId,
        AuthorizedNodes $nodes,
        ListDepartmentsData $filters,
        string $path,
    ): LengthAwarePaginator {
        $key = NormalizedName::key($filters->search);
        $needle = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $key).'%';

        $query = Department::query()
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
}
