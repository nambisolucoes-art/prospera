<?php

namespace App\Domains\Acessos\UI\Controllers;

use App\Domains\Acessos\Application\Actions\SaveRoleAction;
use App\Domains\Acessos\Application\Context\AccountContext;
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
            'roles' => $directory->roleList(app(AccountContext::class)->requireId(), $filters),
            'filters' => $filters->toArray(),
        ]);
    }

    public function roleForm(AccessDirectoryQuery $directory, ?int $roleId = null): JsonResponse|View
    {
        $accountId = app(AccountContext::class)->requireId();

        $role = $roleId
            ? $directory->roles($accountId)->with('permissions')->findOrFail($roleId)
            : null;

        return ApiPage::respond([
            'role' => $role,
            'permissions' => $directory->permissions($accountId)->get(),
            'modules' => config('permissions.modules'),
        ]);
    }

    public function rolePreview(AccessDirectoryQuery $directory, int $roleId): JsonResponse
    {
        $role = $directory->roles(app(AccountContext::class)->requireId())
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
        $accountId = app(AccountContext::class)->requireId();
        $role = $roleId ? $directory->roles($accountId)->findOrFail($roleId) : null;

        $save->execute($accountId, $role, SaveRoleData::fromInput($request->all(), $accountId, $roleId));
        ApiPage::toast('Cargo salvo.');

        if ($request->user()->can('account.roles.list')) {
            return redirect('/conta/cargos');
        }

        return redirect($roleId ? '/conta/cargos/'.$roleId.'/editar' : '/conta/cargos/cadastrar');
    }
}
