<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Queries\Memberships;

use Illuminate\Support\Facades\DB;

final readonly class ListRolesPermissionSlugsQuery
{
    /**
     * @param  list<int>  $roles
     * @return array<int,array<string,true>>
     */
    public function execute(array $roles, int $accountId): array
    {
        if ($roles === []) {
            return [];
        }

        $rows = DB::table('permission_role as pr')
            ->join('permissions as p', 'p.id', '=', 'pr.permission_id')
            ->whereIn('pr.role_id', $roles)
            ->where('p.is_system', true)
            ->whereIn('p.scope', ['account', 'self'])
            ->where(fn ($query) => $query->whereNull('p.account_id')->orWhere('p.account_id', $accountId))
            ->get(['pr.role_id', 'p.slug']);

        $permissions = [];

        foreach ($rows as $row) {
            $permissions[(int) $row->role_id][(string) $row->slug] = true;
        }

        return $permissions;
    }
}
