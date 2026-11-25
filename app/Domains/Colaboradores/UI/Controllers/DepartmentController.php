<?php

namespace App\Domains\Colaboradores\UI\Controllers;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Colaboradores\Application\Actions\SaveDepartmentAction;
use App\Domains\Colaboradores\Application\Data\CatalogCompaniesData;
use App\Domains\Colaboradores\Application\Data\CatalogFiltersData;
use App\Domains\Colaboradores\Application\Data\SaveDepartmentData;
use App\Domains\Colaboradores\Application\Queries\DepartmentDirectoryQuery;
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
        DepartmentDirectoryQuery $items,
    ): JsonResponse|View {
        $accountId = $account->requireId();

        $nodes = $permissions->nodesFor($request->user(), 'account.departments.list');
        abort_unless($nodes->reachesCompanies(), 403);

        $filters = CatalogFiltersData::fromInput($request->query());

        $list = $items->list($accountId, $nodes, $filters, $account->path('departamentos'));

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
        DepartmentDirectoryQuery $items,
        int $itemId,
    ): JsonResponse {
        $nodes = $permissions->nodesFor($request->user(), 'account.departments.view');
        abort_unless($nodes->reachesCompanies(), 403);

        $item = $items->find($account->requireId(), $nodes, $itemId);
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
        SaveDepartmentAction $save,
    ): RedirectResponse {
        $nodes = $permissions->nodesFor($request->user(), 'account.departments.create');
        abort_unless($nodes->reachesCompanies(), 403);

        $allowed = $units->companies($account->requireId(), $nodes);
        abort_if($allowed === [], 403);

        $selected = CatalogCompaniesData::fromInput(
            $request->all(),
            $allowed,
            fn () => SaveDepartmentData::fromInput($request->all(), [], null),
        );
        $data = SaveDepartmentData::fromInput($request->all(), $selected->ids(), null);

        $save->execute($account->requireId(), $selected->ids(), null, $data);
        ApiPage::toast(count($selected->ids()) > 1 ? 'Departamentos salvos.' : 'Departamento salvo.');

        return redirect($account->path('departamentos'));
    }

    public function update(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        DepartmentDirectoryQuery $items,
        SaveDepartmentAction $save,
        int $itemId,
    ): RedirectResponse {
        $nodes = $permissions->nodesFor($request->user(), 'account.departments.update');
        abort_unless($nodes->reachesCompanies(), 403);

        $item = $items->find($account->requireId(), $nodes, $itemId);
        abort_if($item === null, 404);

        $data = SaveDepartmentData::fromInput($request->all(), [$item->company_id], $item->id);

        $save->execute($account->requireId(), [$item->company_id], $item, $data);
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
