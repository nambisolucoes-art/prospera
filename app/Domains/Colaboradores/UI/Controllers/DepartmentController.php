<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\UI\Controllers;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Colaboradores\Application\Actions\Departments\CreateDepartmentAction;
use App\Domains\Colaboradores\Application\Actions\Departments\UpdateDepartmentAction;
use App\Domains\Colaboradores\Application\Data\Departments\CreateDepartmentData;
use App\Domains\Colaboradores\Application\Data\Departments\ListDepartmentsData;
use App\Domains\Colaboradores\Application\Data\Departments\UpdateDepartmentData;
use App\Domains\Colaboradores\Application\Queries\Departments\ListDepartmentsQuery;
use App\Domains\Colaboradores\Application\Queries\Departments\ShowDepartmentQuery;
use App\Domains\Colaboradores\Domain\Models\Department;
use App\Domains\Organizacao\Application\Queries\OrganizationContextQuery;
use App\Domains\Organizacao\Domain\DTOs\NavigableCompany;
use App\Support\ApiPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Departamentos de todas as empresas que o acesso alcança, na mesma tela. A empresa de cada registro vem do
 * descritor da permissão e, no cadastro, da escolha validada no servidor.
 */
final class DepartmentController
{
    public function index(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        OrganizationContextQuery $units,
        ListDepartmentsQuery $query,
    ): JsonResponse|View {
        $accountId = $account->requireId();

        $nodes = $permissions->nodesFor($request->user(), 'account.departments.list');
        abort_unless($nodes->reachesCompanies(), 403);

        $filters = ListDepartmentsData::fromInput($request->query());

        $list = $query->execute($accountId, $nodes, $filters, $account->path('departamentos'));

        $creatable = $units->companies($accountId, $permissions->nodesFor($request->user(), 'account.departments.create'));

        return ApiPage::respond([
            'departments' => $list->through(fn (Department $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'active' => $item->active,
                'company' => $item->company->trade_name ?: $item->company->name,
                'company_code' => $item->company->code,
                'updated_at' => $item->updated_at?->toIso8601String(),
            ]),
            'filters' => $filters->toArray(),
            'companies' => $this->options($units->companies($accountId, $nodes)),
            'creatable' => $this->options($creatable),
        ]);
    }

    public function show(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        ShowDepartmentQuery $query,
        int $itemId,
    ): JsonResponse {
        $nodes = $permissions->nodesFor($request->user(), 'account.departments.view');
        abort_unless($nodes->reachesCompanies(), 403);

        $item = $query->execute($account->requireId(), $nodes, $itemId);
        abort_if($item === null, 404);

        return response()->json([
            'id' => $item->id,
            'name' => $item->name,
            'active' => $item->active,
            'company_name' => $item->company->trade_name ?: $item->company->name,
            'created_at' => $item->created_at?->toIso8601String(),
            'updated_at' => $item->updated_at?->toIso8601String(),
        ]);
    }

    public function store(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        OrganizationContextQuery $units,
        CreateDepartmentAction $action,
    ): RedirectResponse {
        $nodes = $permissions->nodesFor($request->user(), 'account.departments.create');
        abort_unless($nodes->reachesCompanies(), 403);

        $allowed = $units->companies($account->requireId(), $nodes);
        abort_if($allowed === [], 403);

        $data = CreateDepartmentData::fromInput($request->all(), $allowed);

        $action->execute($data, $account->requireId());
        ApiPage::toast(count($data->companies) > 1 ? 'Departamentos salvos.' : 'Departamento salvo.');

        return redirect($account->path('departamentos'));
    }

    public function update(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        ShowDepartmentQuery $query,
        UpdateDepartmentAction $action,
        int $itemId,
    ): RedirectResponse {
        $nodes = $permissions->nodesFor($request->user(), 'account.departments.update');
        abort_unless($nodes->reachesCompanies(), 403);

        $item = $query->execute($account->requireId(), $nodes, $itemId);
        abort_if($item === null, 404);

        $data = UpdateDepartmentData::fromInput($request->all(), $item->company_id, $item->id);

        $action->execute($data, $item);
        ApiPage::toast('Departamento salvo.');

        return redirect($account->path('departamentos'));
    }

    /**
     * @param  list<NavigableCompany>  $companies
     * @return list<array{code: string, name: string}>
     */
    private function options(array $companies): array
    {
        return array_map(fn (NavigableCompany $company) => $company->toArray(), $companies);
    }
}
