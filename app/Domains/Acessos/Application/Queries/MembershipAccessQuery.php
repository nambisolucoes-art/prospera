<?php

namespace App\Domains\Acessos\Application\Queries;

use App\Domains\Acessos\Domain\Models\RoleAssignment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class MembershipAccessQuery
{
    /** @return Collection<int, RoleAssignment> */
    public function assignments(int $userId, int $accountId): Collection
    {
        return RoleAssignment::query()
            ->join('account_memberships as m', 'm.id', '=', 'role_assignments.membership_id')
            ->join('clients as c', 'c.id', '=', 'm.account_id')
            ->join('roles as r', 'r.id', '=', 'role_assignments.role_id')
            ->where('m.user_id', $userId)
            ->where('m.account_id', $accountId)
            ->where('m.status', 'active')
            ->where('c.active', true)
            ->where(fn ($query) => $query->whereNull('r.system_key')->orWhere('role_assignments.scope', 'tenant'))
            ->where(fn ($query) => $query->where('r.availability', 'tenant')
                ->orWhereExists(fn ($available) => $available->selectRaw('1')
                    ->from('role_availability as availability')
                    ->whereColumn('availability.role_id', 'role_assignments.role_id')
                    ->whereColumn('availability.company_id', 'role_assignments.company_id')
                    ->where(fn ($node) => $node->whereNull('availability.establishment_id')
                        ->orWhereColumn('availability.establishment_id', 'role_assignments.establishment_id'))))
            ->get([
                'role_assignments.role_id',
                'role_assignments.scope',
                'role_assignments.company_id',
                'role_assignments.establishment_id',
            ]);
    }

    /**
     * @param  list<int>  $roles
     * @return array<int,array<string,true>>
     */
    public function permissions(array $roles, int $accountId): array
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
