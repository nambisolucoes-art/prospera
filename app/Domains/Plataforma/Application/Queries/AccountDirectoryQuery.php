<?php

namespace App\Domains\Plataforma\Application\Queries;

use App\Domains\Plataforma\Application\Data\AccountFiltersData;
use App\Domains\Plataforma\Domain\Models\Client;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

final class AccountDirectoryQuery
{
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

        return Client::query()->select(['id', 'name', 'document', 'person_type', 'email', 'active', 'plan'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($filter) use ($search) {
                    $filter->whereRaw('lower(name) like ?', ['%'.Str::lower($search).'%']);
                    $digits = preg_replace('/\D/', '', $search);
                    if ($digits !== '') {
                        $filter->orWhere('document', 'like', "%$digits%");
                    }
                });
            })
            ->withCount('users')->orderBy('name')->orderBy('id')->paginate($filters->pageSize)->withQueryString();
    }
}
