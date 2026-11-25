<?php

declare(strict_types=1);

namespace App\Domains\Acessos\Application\Queries;

use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Models\User;

final readonly class AssignmentOptionsQuery
{
    public function __construct(private EffectivePermissions $permissions) {}

    /** @return list<array<string,mixed>> */
    public function forOperator(
        User $operator,
        int $accountId,
        bool $creating = false,
        ?string $catalogAbility = null,
    ): array {
        $scope = $this->permissions->nodesFor($operator, 'account.users.assign-role');

        if ($scope->isEmpty()) {
            return [];
        }

        $companies = Company::where('account_id', $accountId)->where('active', true);
        $scope->apply($companies, 'id', null);

        $units = Establishment::where('account_id', $accountId)
            ->where('active', true)
            ->whereHas('company', fn ($company) => $company->where('active', true))
            ->with('company:id,name');
        $scope->apply($units, 'company_id', 'id');

        $choices = [];

        if ($scope->tenant) {
            $choices[] = [AccessNode::tenant($accountId), 'Toda a conta'];
        }

        foreach ($companies->orderBy('name')->get(['id', 'name']) as $company) {
            $choices[] = [AccessNode::company($accountId, $company->id), $company->name.' (Empresa)'];
        }

        foreach ($units->orderBy('name')->get(['id', 'company_id', 'name']) as $unit) {
            $choices[] = [
                AccessNode::establishment($accountId, $unit->company_id, $unit->id),
                $unit->company->name.' / '.$unit->name,
            ];
        }

        $roles = Role::where('account_id', $accountId)->with('permissions', 'availabilityNodes')->orderBy('name')->get();
        $options = [];

        foreach ($choices as [$node, $name]) {
            if (
                ($creating && ! $operator->can('account.users.create', $node))
                || ($catalogAbility !== null && ! $operator->can($catalogAbility, $node))
            ) {
                continue;
            }

            $assignable = $roles
                ->filter(fn (Role $role): bool => $role->isAvailableAt($node)
                    && $role->permissions->every(fn ($permission) => $this->permissions
                        ->canDelegate($operator, $permission->slug, $node)))
                ->map(fn (Role $role) => ['id' => $role->id, 'name' => $role->name])
                ->values();

            if ($assignable->isNotEmpty()) {
                $options[] = [
                    'key' => implode(':', [$node->type, $node->companyId, $node->establishmentId]),
                    'label' => $name,
                    'scope' => $node->type,
                    'company_id' => $node->companyId,
                    'establishment_id' => $node->establishmentId,
                    'roles' => $assignable,
                ];
            }
        }

        return $options;
    }
}
