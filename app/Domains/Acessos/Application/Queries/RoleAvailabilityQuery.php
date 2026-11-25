<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Queries;

use App\Domains\Acessos\Application\Data\RoleAvailabilityData;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Acessos\Domain\Models\RoleAssignment;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class RoleAvailabilityQuery
{
    /** @return array{companies:Collection<int, Company>,establishments:Collection<int, Establishment>} */
    public function options(int $accountId): array
    {
        return [
            'companies' => Company::where('account_id', $accountId)
                ->orderBy('name')
                ->get(['id', 'name', 'active']),
            'establishments' => Establishment::where('account_id', $accountId)
                ->orderBy('name')
                ->get(['id', 'company_id', 'name', 'active']),
        ];
    }

    /** @return Builder<RoleAssignment> */
    public function uncovered(Role $role, RoleAvailabilityData $availability): Builder
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

    /** @return Collection<int, Establishment> */
    public function selectedUnits(int $accountId, RoleAvailabilityData $availability): Collection
    {
        return Establishment::where('account_id', $accountId)
            ->whereKey($availability->establishments)
            ->get(['id', 'company_id']);
    }
}
