<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Queries;

use App\Domains\Acessos\Application\Data\Roles\ListRolesData;
use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class AccessDirectoryQuery
{
    /** @return Builder<Permission> */
    public function permissions(int $accountId): Builder
    {
        return Permission::where('is_system', true)
            ->whereIn('scope', ['account', 'self'])
            ->where(fn ($query) => $query
                ->whereNull('account_id')
                ->orWhere('account_id', $accountId))
            ->orderBy('module')
            ->orderBy('name')
            ->orderBy('id');
    }

    /** @return Builder<Role> */
    public function roles(int $accountId): Builder
    {
        return Role::where('account_id', $accountId)->orderBy('name')->orderBy('id');
    }

    /**
     * @param  list<int>|null  $roleIds
     * @return LengthAwarePaginator<int, Role>
     */
    public function roleList(
        int $accountId,
        ListRolesData $filters,
        ?array $roleIds = null,
        ?AuthorizedNodes $nodes = null,
    ): LengthAwarePaginator {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters->search);
        $needle = '%'.$escaped.'%';

        return $this->roles($accountId)
            ->when($roleIds !== null, fn ($query) => $query->whereKey($roleIds))
            ->withCount(['permissions', 'assignments' => fn (Builder $query) => $nodes?->apply($query)])
            ->when($filters->search !== '', fn ($query) => $query->whereLike('name', $needle))
            ->when($filters->origin === 'system', fn ($query) => $query->whereNotNull('system_key'))
            ->when($filters->origin === 'custom', fn ($query) => $query->whereNull('system_key'))
            ->paginate($filters->pageSize)
            ->withQueryString();
    }
}
