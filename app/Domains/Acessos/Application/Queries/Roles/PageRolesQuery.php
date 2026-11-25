<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Queries\Roles;

use App\Domains\Acessos\Application\Data\Roles\ListRolesData;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class PageRolesQuery
{
    public function __construct(
        private ListRolesQuery $listRoles,
    ) {}

    /**
     * @param  list<int>|null  $roleIds
     * @return LengthAwarePaginator<int, Role>
     */
    public function execute(
        int $accountId,
        ListRolesData $filters,
        ?array $roleIds = null,
        ?AuthorizedNodes $nodes = null,
    ): LengthAwarePaginator {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters->search);
        $needle = '%'.$escaped.'%';

        return $this->listRoles->execute($accountId)
            ->when($roleIds !== null, fn ($query) => $query->whereKey($roleIds))
            ->withCount(['permissions', 'assignments' => fn (Builder $query) => $nodes?->apply($query)])
            ->when($filters->search !== '', fn ($query) => $query->whereLike('name', $needle))
            ->when($filters->origin === 'system', fn ($query) => $query->whereNotNull('system_key'))
            ->when($filters->origin === 'custom', fn ($query) => $query->whereNull('system_key'))
            ->paginate($filters->pageSize)
            ->withQueryString();
    }
}
