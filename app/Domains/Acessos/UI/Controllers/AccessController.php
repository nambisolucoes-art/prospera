<?php

declare(strict_types=1);

namespace App\Domains\Acessos\UI\Controllers;

use App\Domains\Acessos\Application\Actions\SaveRoleAction;
use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Application\Data\RoleAvailabilityData;
use App\Domains\Acessos\Application\Data\RoleFiltersData;
use App\Domains\Acessos\Application\Data\SaveRoleData;
use App\Domains\Acessos\Application\Queries\AccessDirectoryQuery;
use App\Domains\Acessos\Application\Queries\AssignmentOptionsQuery;
use App\Domains\Acessos\Application\Queries\RoleAvailabilityQuery;
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
        AccessDirectoryQuery $directory,
        AssignmentOptionsQuery $options,
        EffectivePermissions $permissions,
    ): JsonResponse|View {
        $filters = RoleFiltersData::fromInput($request->query());
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
            'roles' => $directory->roleList($accountId, $filters, $ids, $nodes),
            'filters' => $filters->toArray(),
        ]);
    }

    public function roleForm(AccessDirectoryQuery $directory, RoleAvailabilityQuery $availability, ?int $roleId = null): JsonResponse|View
    {
        $accountId = app(AccountContext::class)->requireId();

        $role = $roleId
            ? $directory->roles($accountId)->with(['permissions', 'availabilityNodes'])->findOrFail($roleId)
            : null;

        return ApiPage::respond([
            'role' => $role,
            'permissions' => $directory->permissions($accountId)->get(),
            'modules' => config('permissions.modules'),
            'organization' => $availability->options($accountId),
        ]);
    }

    public function rolePreview(
        Request $request,
        AccessDirectoryQuery $directory,
        AssignmentOptionsQuery $options,
        EffectivePermissions $permissions,
        int $roleId,
    ): JsonResponse {
        $accountId = app(AccountContext::class)->requireId();
        $user = $request->user();
        $nodes = $permissions->nodesFor($user, 'account.roles.view');
        abort_if($nodes->isEmpty(), 403);
        $query = $directory->roles($accountId)->with('permissions');

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
        AccessDirectoryQuery $directory,
        RoleAvailabilityQuery $availability,
        int $roleId,
    ): JsonResponse {
        $accountId = app(AccountContext::class)->requireId();
        $role = $directory->roles($accountId)->findOrFail($roleId);
        $proposed = RoleAvailabilityData::fromInput($request->query(), $accountId);

        $rows = $availability->uncovered($role, $proposed)
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

    public function saveRole(
        Request $request,
        AccessDirectoryQuery $directory,
        SaveRoleAction $save,
        ?int $roleId = null,
    ): RedirectResponse {
        $accountId = app(AccountContext::class)->requireId();
        $role = $roleId ? $directory->roles($accountId)->findOrFail($roleId) : null;

        $save->execute(
            $accountId,
            $role,
            SaveRoleData::fromInput($request->all(), $accountId, $roleId),
            RoleAvailabilityData::fromInput($request->all(), $accountId, $role === null ? 'tenant' : $role->availability),
        );
        ApiPage::toast('Cargo salvo.');

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
