<?php

namespace App\Domains\Acessos\Application\Queries;

use App\Domains\Acessos\Application\Data\RoleFiltersData;
use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class AccessDirectoryQuery
{
    /** @return Builder<Permission> */
    public function permissions(int $accountId): Builder
    {
        return Permission::where('is_system', true)->whereIn('scope', ['account', 'self'])->where(fn ($q) => $q->whereNull('account_id')->orWhere('account_id', $accountId))->orderBy('module')->orderBy('name')->orderBy('id');
    }

    /** @return Builder<Role> */
    public function roles(int $accountId): Builder
    {
        return Role::where('account_id', $accountId)->orderBy('name')->orderBy('id');
    }

    /** @return LengthAwarePaginator<int, Role> */
    public function roleList(int $accountId, RoleFiltersData $filters): LengthAwarePaginator
    {
        $needle = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters->search).'%';

        return $this->roles($accountId)->withCount('permissions')
            ->when($filters->search !== '', fn ($q) => $q->whereLike('name', $needle))
            ->when($filters->origin === 'system', fn ($q) => $q->whereNotNull('system_key'))
            ->when($filters->origin === 'custom', fn ($q) => $q->whereNull('system_key'))
            ->paginate($filters->pageSize)->withQueryString();
    }
}
