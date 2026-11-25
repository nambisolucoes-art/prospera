<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Queries\Roles;

use App\Domains\Acessos\Application\Data\Roles\RoleAvailabilityData;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Acessos\Domain\Models\RoleAssignment;
use Illuminate\Database\Eloquent\Builder;

final readonly class ListUncoveredAssignmentsQuery
{
    /** @return Builder<RoleAssignment> */
    public function execute(Role $role, RoleAvailabilityData $availability): Builder
    {
        $assignments = RoleAssignment::where('role_assignments.account_id', $role->account_id)
            ->where('role_assignments.role_id', $role->id);

        if ($availability->mode === 'tenant') {
            return $assignments->whereRaw('1 = 0');
        }

        return $assignments->whereNot(fn (Builder $coverage) => $coverage
            ->where(fn (Builder $company) => $company
                ->whereIn('scope', ['company', 'establishment'])
                ->whereIn('role_assignments.company_id', $availability->companies))
            ->orWhere(fn (Builder $unit) => $unit
                ->where('scope', 'establishment')
                ->whereIn('role_assignments.establishment_id', $availability->establishments)));
    }
}
