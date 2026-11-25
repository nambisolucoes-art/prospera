<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Queries\Establishments;

use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use App\Domains\Organizacao\Application\Data\Establishments\ListEstablishmentsData;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Support\TaxDocument;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListEstablishmentsQuery
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
    public function execute(
        int $accountId,
        ListEstablishmentsData $filters,
        string $path,
        string $pageName = 'page',
        ?AuthorizedNodes $nodes = null,
    ): LengthAwarePaginator {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters->search);
        $needle = '%'.$escaped.'%';
        $document = TaxDocument::normalize($filters->search);

        $query = Establishment::where('account_id', $accountId);
        $nodes?->apply($query, 'company_id', 'id');

        return $query->select(self::LIST_COLUMNS)
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
}
