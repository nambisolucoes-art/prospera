<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Queries\Memberships;

use App\Domains\Acessos\Domain\Models\RoleAssignment;
use Illuminate\Database\Eloquent\Collection;

final readonly class ListMembershipRoleAssignmentsQuery
{
    /** @return Collection<int, RoleAssignment> */
    public function execute(int $userId, int $accountId): Collection
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
}
