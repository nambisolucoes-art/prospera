<?php

declare(strict_types=1);

namespace App\Domains\Plataforma\Application\Queries\Accounts;

use App\Domains\Plataforma\Application\Data\Accounts\ListAccountsData;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Support\TaxDocument;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

final readonly class ListAccountsQuery
{
    /** @return LengthAwarePaginator<int, Client> */
    public function execute(ListAccountsData $filters): LengthAwarePaginator
    {
        $search = $filters->search;

        // O documento é comparado normalizado, preservando as letras do CNPJ alfanumérico.
        $document = TaxDocument::normalize($search);
        $documentSearch = preg_match('/\d/', $document) === 1;
        $escapedDocument = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $document);

        return Client::query()
            ->select(['id', 'name', 'document', 'person_type', 'email', 'active', 'plan'])
            ->when($search !== '', fn ($query) => $query->where(fn ($filter) => $filter
                ->whereRaw('lower(name) like ?', ['%'.Str::lower($search).'%'])
                ->when($documentSearch, fn ($fiscal) => $fiscal->orWhereLike('document', '%'.$escapedDocument.'%'))))
            ->withCount('memberships as users_count')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($filters->pageSize)
            ->withQueryString();
    }
}
