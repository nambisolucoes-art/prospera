<?php

namespace App\Domains\Organizacao\Application\Queries;

use App\Domains\Acessos\Domain\DTOs\AuthorizedNodes;
use App\Domains\Organizacao\Application\Data\CompanyFiltersData;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Support\TaxDocument;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

final class CompanyDirectoryQuery
{
    private const LIST_COLUMNS = [
        'id',
        'account_id',
        'code',
        'name',
        'trade_name',
        'person_type',
        'tax_root',
        'individual_document',
        'active',
    ];

    /** @return LengthAwarePaginator<int, Company> */
    public function list(
        int $accountId,
        CompanyFiltersData $filters,
        string $path,
        ?AuthorizedNodes $nodes = null,
    ): LengthAwarePaginator {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters->search);
        $needle = '%'.$escaped.'%';
        $document = TaxDocument::normalize($filters->search);

        $query = Company::where('account_id', $accountId);
        $nodes?->apply($query, 'id', null);

        return $query->select(self::LIST_COLUMNS)
            ->withCount('establishments')
            ->when($filters->search !== '', fn ($query) => $query->where(fn ($match) => $match
                ->whereLike('name', $needle)
                ->orWhereLike('trade_name', $needle)
                ->orWhereLike('code', $needle)
                ->when(strlen($document) >= 8, fn ($fiscal) => $fiscal
                    ->orWhere('tax_root', substr($document, 0, 8))
                    ->orWhere('individual_document', $document))))
            ->when($filters->active !== null, fn ($query) => $query->where('active', $filters->active))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($filters->pageSize)
            ->withPath($path)
            ->withQueryString();
    }

    public function find(int $accountId, int $id): Company
    {
        return Company::where('account_id', $accountId)->findOrFail($id);
    }

    /**
     * Empresas cobertas por um descritor de alvo empresa (relações comerciais), em ordem de nome.
     *
     * @return Collection<int, Company>
     */
    public function covered(int $accountId, AuthorizedNodes $nodes): Collection
    {
        $query = Company::where('account_id', $accountId);

        if (! $nodes->tenant) {
            $query->whereIn('id', $nodes->companies);
        }

        return $query->orderByRaw('coalesce(trade_name, name)')
            ->orderBy('id')
            ->get(['id', 'code', 'name', 'trade_name', 'active']);
    }

    // Com uma empresa só, as telas omitem a escolha de empresa (contexto organizacional, Empresa pequena).
    public function hasSingleCompany(int $accountId): bool
    {
        return Company::where('account_id', $accountId)->limit(2)->count() === 1;
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Company>
     */
    public function findMany(int $accountId, array $ids): Collection
    {
        return Company::where('account_id', $accountId)
            ->whereKey($ids)
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, Company> */
    public function options(int $accountId, ?AuthorizedNodes $unitNodes = null): Collection
    {
        $query = Company::where('account_id', $accountId);

        if ($unitNodes !== null && ! $unitNodes->tenant) {
            $query->where(fn ($company) => $company->whereIn('id', $unitNodes->companies)
                ->orWhereHas('establishments', fn ($unit) => $unit->whereIn('id', $unitNodes->establishments)));
        }

        return $query->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'trade_name', 'person_type', 'tax_root', 'individual_document', 'active']);
    }
}
