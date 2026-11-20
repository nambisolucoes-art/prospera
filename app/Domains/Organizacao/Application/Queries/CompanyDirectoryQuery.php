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
