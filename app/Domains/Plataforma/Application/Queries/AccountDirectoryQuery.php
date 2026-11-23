<?php

namespace App\Domains\Plataforma\Application\Queries;

use App\Domains\Plataforma\Application\Data\AccountFiltersData;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use App\Support\TaxDocument;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

final class AccountDirectoryQuery
{
    public function administrator(int $accountId): ?User
    {
        return User::where('access_enabled', true)
            ->whereHas('memberships', fn ($membership) => $membership
                ->where('account_id', $accountId)
                ->where('status', 'active')
                ->whereHas('assignments', fn ($assignment) => $assignment
                    ->where('scope', 'tenant')
                    ->whereHas('role', fn ($role) => $role->where('system_key', 'administrator'))))
            ->orderBy('id')
            ->first(['name', 'email']);
    }

    public function activeAccount(int $accountId, int $userId): ?Client
    {
        return Client::whereKey($accountId)
            ->where('active', true)
            ->whereExists(fn ($membership) => $membership->selectRaw('1')
                ->from('account_memberships')
                ->whereColumn('account_memberships.account_id', 'clients.id')
                ->where('user_id', $userId)
                ->where('status', 'active'))
            ->first();
    }

    /** @return LengthAwarePaginator<int, Client> */
    public function list(AccountFiltersData $filters): LengthAwarePaginator
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
