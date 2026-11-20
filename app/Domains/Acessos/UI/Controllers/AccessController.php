<?php

namespace App\Domains\Acessos\UI\Controllers;

use App\Domains\Acessos\Application\Actions\SaveRoleAction;
use App\Domains\Acessos\Application\Data\RoleFiltersData;
use App\Domains\Acessos\Application\Data\SaveRoleData;
use App\Domains\Acessos\Application\Queries\AccessDirectoryQuery;
use App\Support\ApiPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class AccessController
{
    public function roles(Request $request, AccessDirectoryQuery $directory): JsonResponse|View
    {
        $filters = RoleFiltersData::fromInput($request->query());

        return ApiPage::respond([
            'roles' => $directory->roleList($request->user()->account_id, $filters),
            'filters' => $filters->toArray(),
        ]);
    }

    public function roleForm(Request $request, AccessDirectoryQuery $directory, ?int $roleId = null): JsonResponse|View
    {
        $accountId = $request->user()->account_id;

        $role = $roleId
            ? $directory->roles($accountId)->with('permissions')->findOrFail($roleId)
            : null;

        return ApiPage::respond([
            'role' => $role,
            'permissions' => $directory->permissions($accountId)->get(),
            'modules' => config('permissions.modules'),
        ]);
    }

    public function rolePreview(Request $request, AccessDirectoryQuery $directory, int $roleId): JsonResponse
    {
        $role = $directory->roles($request->user()->account_id)
            ->with('permissions')
            ->findOrFail($roleId);

        $modules = $role->permissions
            ->pluck('module')
            ->unique()
            ->mapWithKeys(fn ($module) => [
                $module => config('permissions.modules.'.$module) ?? Str::headline($module),
            ]);

        return response()->json([...$role->toArray(), 'modules' => $modules]);
    }

    public function saveRole(
        Request $request,
        AccessDirectoryQuery $directory,
        SaveRoleAction $save,
        ?int $roleId = null,
    ): RedirectResponse {
        $accountId = $request->user()->account_id;
        $role = $roleId ? $directory->roles($accountId)->findOrFail($roleId) : null;

        $save->execute($accountId, $role, SaveRoleData::fromInput($request->all(), $accountId, $roleId));
        ApiPage::toast('Cargo salvo.');

        if ($request->user()->can('account.roles.list')) {
            return redirect('/conta/cargos');
        }

        return redirect($roleId ? '/conta/cargos/'.$roleId.'/editar' : '/conta/cargos/cadastrar');
    }
}
