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
    public function roles(Request $r, AccessDirectoryQuery $q): JsonResponse|View
    {
        $filters = RoleFiltersData::fromInput($r->query());

        return ApiPage::respond(['roles' => $q->roleList($r->user()->account_id, $filters), 'filters' => $filters->toArray()]);
    }

    public function roleForm(Request $r, AccessDirectoryQuery $q, ?int $roleId = null): JsonResponse|View
    {
        return ApiPage::respond(['role' => $roleId ? $q->roles($r->user()->account_id)->with('permissions')->findOrFail($roleId) : null, 'permissions' => $q->permissions($r->user()->account_id)->get(), 'modules' => config('permissions.modules')]);
    }

    public function rolePreview(Request $r, AccessDirectoryQuery $q, int $roleId): JsonResponse
    {
        $role = $q->roles($r->user()->account_id)->with('permissions')->findOrFail($roleId);
        $modules = $role->permissions->pluck('module')->unique()->mapWithKeys(fn ($module) => [$module => config('permissions.modules.'.$module) ?? Str::headline($module)]);

        return response()->json([...$role->toArray(), 'modules' => $modules]);
    }

    public function saveRole(Request $r, AccessDirectoryQuery $q, SaveRoleAction $save, ?int $roleId = null): RedirectResponse
    {
        $accountId = $r->user()->account_id;
        $role = $roleId ? $q->roles($accountId)->findOrFail($roleId) : null;
        $save->execute($accountId, $role, SaveRoleData::fromInput($r->all(), $accountId, $roleId));
        ApiPage::toast('Cargo salvo.');

        return redirect($r->user()->can('account.roles.list') ? '/conta/cargos' : ($roleId ? '/conta/cargos/'.$roleId.'/editar' : '/conta/cargos/cadastrar'));
    }
}
