<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Queries\Roles;

use App\Domains\Acessos\Application\Data\Roles\RoleAvailabilityData;
use App\Domains\Organizacao\Domain\Models\Establishment;
use Illuminate\Database\Eloquent\Collection;

final readonly class ListSelectedUnitsQuery
{
    /** @return Collection<int, Establishment> */
    public function execute(int $accountId, RoleAvailabilityData $availability): Collection
    {
        return Establishment::where('account_id', $accountId)
            ->whereKey($availability->establishments)
            ->get(['id', 'company_id']);
    }
}
