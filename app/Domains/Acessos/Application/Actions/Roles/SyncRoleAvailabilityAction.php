<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Actions\Roles;

use App\Domains\Acessos\Application\Data\Roles\RoleAvailabilityData;
use App\Domains\Acessos\Application\Queries\Roles\ListSelectedUnitsQuery;
use App\Domains\Acessos\Domain\Models\Role;

/** Grava os nós de abrangência de um cargo restrito a empresas ou estabelecimentos. */
final readonly class SyncRoleAvailabilityAction
{
    public function __construct(private ListSelectedUnitsQuery $listSelectedUnits) {}

    public function execute(Role $role, RoleAvailabilityData $availability): void
    {
        $nodes = [];

        if ($availability->mode === 'restricted') {
            foreach ($availability->companies as $companyId) {
                $nodes[] = ['company_id' => $companyId, 'establishment_id' => null];
            }

            foreach ($this->listSelectedUnits->execute($role->account_id, $availability) as $unit) {
                if (! in_array($unit->company_id, $availability->companies, true)) {
                    $nodes[] = ['company_id' => $unit->company_id, 'establishment_id' => $unit->id];
                }
            }
        }

        $role->syncAvailabilityNodes($nodes);
    }
}
