<?php

namespace App\Domains\Organizacao\Application\Queries;

use App\Domains\Organizacao\Application\Data\EstablishmentFiltersData;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Support\TaxDocument;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

final class EstablishmentDirectoryQuery
{
    private const LIST_COLUMNS = [
        'id',
        'account_id',
        'company_id',
        'code',
        'name',
        'trade_name',
        'tax_document',
        'is_headquarters',
        'is_primary',
        'active',
        'address',
    ];

    /** @return LengthAwarePaginator<int, Establishment> */
    public function list(
        int $accountId,
        EstablishmentFiltersData $filters,
        string $path,
        string $pageName = 'page',
    ): LengthAwarePaginator {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters->search);
        $needle = '%'.$escaped.'%';
        $document = TaxDocument::normalize($filters->search);

        return Establishment::where('account_id', $accountId)
            ->select(self::LIST_COLUMNS)
            ->with('company:id,name,trade_name,person_type,tax_root,individual_document,active')
            ->when($filters->companyId !== null, fn ($query) => $query->where('company_id', $filters->companyId))
            ->when($filters->search !== '', fn ($query) => $query->where(fn ($match) => $match
                ->whereLike('name', $needle)
                ->orWhereLike('trade_name', $needle)
                ->orWhereLike('code', $needle)
                ->when(strlen($document) === 14, fn ($fiscal) => $fiscal->orWhere('tax_document', $document))))
            ->when($filters->active !== null, fn ($query) => $query->where('active', $filters->active))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($filters->pageSize, pageName: $pageName)
            ->withPath($path)
            ->withQueryString();
    }

    public function find(int $accountId, int $id): Establishment
    {
        return Establishment::where('account_id', $accountId)
            ->with('company:id,account_id,name,trade_name,person_type,tax_root,individual_document,active')
            ->findOrFail($id);
    }

    /** @return Collection<int, Establishment> */
    public function forCompany(int $accountId, int $companyId): Collection
    {
        return Establishment::where('account_id', $accountId)
            ->where('company_id', $companyId)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'company_id', 'code', 'name', 'tax_document', 'is_headquarters', 'is_primary', 'active']);
    }

    public function count(int $accountId): int
    {
        return Establishment::where('account_id', $accountId)->count();
    }
}
