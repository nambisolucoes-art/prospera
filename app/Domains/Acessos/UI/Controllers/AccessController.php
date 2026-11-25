<?php

declare(strict_types=1);

namespace App\Domains\Acessos\UI\Controllers;

use App\Domains\Acessos\Application\Actions\Roles\CreateRoleAction;
use App\Domains\Acessos\Application\Actions\Roles\UpdateRoleAction;
use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Application\Data\Roles\CreateRoleData;
use App\Domains\Acessos\Application\Data\Roles\ListRolesData;
use App\Domains\Acessos\Application\Data\Roles\RoleAvailabilityData;
use App\Domains\Acessos\Application\Data\Roles\UpdateRoleData;
use App\Domains\Acessos\Application\Queries\AssignmentOptionsQuery;
use App\Domains\Acessos\Application\Queries\Roles\ListPermissionsQuery;
use App\Domains\Acessos\Application\Queries\Roles\ListRoleAvailabilityOptionsQuery;
use App\Domains\Acessos\Application\Queries\Roles\ListRolesQuery;
use App\Domains\Acessos\Application\Queries\Roles\ListUncoveredAssignmentsQuery;
use App\Domains\Acessos\Application\Queries\Roles\PageRolesQuery;
use App\Support\ApiPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class AccessController
{
    public function roles(
        Request $request,
        PageRolesQuery $pageRoles,
        AssignmentOptionsQuery $options,
        EffectivePermissions $permissions,
    ): JsonResponse|View {
        $filters = ListRolesData::fromInput($request->query());
        $accountId = app(AccountContext::class)->requireId();
        $user = $request->user();
        $nodes = $permissions->nodesFor($user, 'account.roles.list');
        abort_if($nodes->isEmpty(), 403);

        $ids = $nodes->tenant ? null : $this->roleIds($options->forOperator(
            $user,
            $accountId,
            catalogAbility: 'account.roles.list',
        ));

        return ApiPage::respond([
            'roles' => $pageRoles->execute($accountId, $filters, $ids, $nodes),
            'filters' => $filters->toArray(),
        ]);
    }

    public function roleForm(
        ListRolesQuery $listRoles,
        ListPermissionsQuery $listPermissions,
        ListRoleAvailabilityOptionsQuery $listRoleAvailabilityOptions,
        ?int $roleId = null,
    ): JsonResponse|View {
        $accountId = app(AccountContext::class)->requireId();

        $role = $roleId
            ? $listRoles->execute($accountId)->with(['permissions', 'availabilityNodes'])->findOrFail($roleId)
            : null;

        return ApiPage::respond([
            'role' => $role,
            'permissions' => $listPermissions->execute($accountId)->get(),
            'modules' => config('permissions.modules'),
            'organization' => $listRoleAvailabilityOptions->execute($accountId),
        ]);
    }

    public function rolePreview(
        Request $request,
        ListRolesQuery $listRoles,
        AssignmentOptionsQuery $options,
        EffectivePermissions $permissions,
        int $roleId,
    ): JsonResponse {
        $accountId = app(AccountContext::class)->requireId();
        $user = $request->user();
        $nodes = $permissions->nodesFor($user, 'account.roles.view');
        abort_if($nodes->isEmpty(), 403);
        $query = $listRoles->execute($accountId)->with('permissions');

        if (! $nodes->tenant) {
            $query->whereKey($this->roleIds($options->forOperator($user, $accountId, catalogAbility: 'account.roles.view')));
        }

        $role = $query->findOrFail($roleId);

        $modules = $role->permissions
            ->pluck('module')
            ->unique()
            ->mapWithKeys(fn ($module) => [
                $module => config('permissions.modules.'.$module) ?? Str::headline($module),
            ]);

        return response()->json([...$role->toArray(), 'modules' => $modules]);
    }

    public function roleImpact(
        Request $request,
        ListRolesQuery $listRoles,
        ListUncoveredAssignmentsQuery $listUncoveredAssignments,
        int $roleId,
    ): JsonResponse {
        $accountId = app(AccountContext::class)->requireId();
        $role = $listRoles->execute($accountId)->findOrFail($roleId);
        $proposed = RoleAvailabilityData::fromInput($request->query(), $accountId);

        $rows = $listUncoveredAssignments->execute($role, $proposed)
            ->join('account_memberships as m', 'm.id', '=', 'role_assignments.membership_id')
            ->join('people', 'people.id', '=', 'm.person_id')
            ->orderBy('people.name')
            ->orderBy('role_assignments.id')
            ->get([
                'role_assignments.id',
                'people.name',
                'role_assignments.scope',
                'role_assignments.company_id',
                'role_assignments.establishment_id',
            ]);

        return response()->json(['assignments' => $rows]);
    }

    public function storeRole(Request $request, CreateRoleAction $action): RedirectResponse
    {
        $accountId = app(AccountContext::class)->requireId();

        $data = CreateRoleData::fromInput($request->all(), $accountId);

        $action->execute($data, $accountId);
        ApiPage::toast('Cargo salvo.');

        return $this->afterSave($request, null);
    }

    public function updateRole(
        Request $request,
        ListRolesQuery $listRoles,
        UpdateRoleAction $action,
        int $roleId,
    ): RedirectResponse {
        $accountId = app(AccountContext::class)->requireId();
        $role = $listRoles->execute($accountId)->findOrFail($roleId);

        $data = UpdateRoleData::fromInput($request->all(), $role);

        $action->execute($data, $role);
        ApiPage::toast('Cargo salvo.');

        return $this->afterSave($request, $roleId);
    }

    private function afterSave(Request $request, ?int $roleId): RedirectResponse
    {
        if ($request->user()->can('account.roles.list')) {
            return redirect(app(AccountContext::class)->path('cargos'));
        }

        return redirect(app(AccountContext::class)->path($roleId ? 'cargos/'.$roleId.'/editar' : 'cargos/cadastrar'));
    }

    /**
     * @param  list<array<string,mixed>>  $options
     * @return list<int>
     */
    private function roleIds(array $options): array
    {
        $ids = [];

        foreach ($options as $option) {
            foreach ($option['roles'] as $role) {
                $ids[$role['id']] = $role['id'];
            }
        }

        return array_values($ids);
    }
}
