<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Queries\Companies;

use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use App\Domains\Organizacao\Application\Data\Companies\ListCompaniesData;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Support\TaxDocument;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListCompaniesQuery
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
    public function execute(
        int $accountId,
        ListCompaniesData $filters,
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
}
