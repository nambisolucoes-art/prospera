<?php

namespace App\Domains\Colaboradores\UI\Controllers;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Colaboradores\Application\Actions\SaveJobTitleAction;
use App\Domains\Colaboradores\Application\Data\CatalogCompaniesData;
use App\Domains\Colaboradores\Application\Data\CatalogFiltersData;
use App\Domains\Colaboradores\Application\Data\SaveJobTitleData;
use App\Domains\Colaboradores\Application\Queries\JobTitleDirectoryQuery;
use App\Domains\Colaboradores\Domain\Models\JobTitle;
use App\Domains\Organizacao\Application\Queries\OrganizationContextQuery;
use App\Domains\Organizacao\Domain\DTOs\NavigableCompany;
use App\Support\ApiPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Funções de todas as empresas que o acesso alcança, na mesma tela. A empresa de cada registro vem do
 * descritor da permissão e, no cadastro, da escolha validada no servidor.
 */
final class JobTitleController
{
    public function index(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        OrganizationContextQuery $units,
        JobTitleDirectoryQuery $items,
    ): JsonResponse|View {
        $accountId = $account->requireId();

        $nodes = $permissions->nodesFor($request->user(), 'account.job-titles.list');
        abort_unless($nodes->reachesCompanies(), 403);

        $filters = CatalogFiltersData::fromInput($request->query());

        $list = $items->list($accountId, $nodes, $filters, $account->path('funcoes'));

        $creatable = $units->companies($accountId, $permissions->nodesFor($request->user(), 'account.job-titles.create'));

        return ApiPage::respond([
            'job_titles' => $list->through(fn (JobTitle $item) => [
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
        JobTitleDirectoryQuery $items,
        int $itemId,
    ): JsonResponse {
        $nodes = $permissions->nodesFor($request->user(), 'account.job-titles.view');
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
        SaveJobTitleAction $save,
    ): RedirectResponse {
        $nodes = $permissions->nodesFor($request->user(), 'account.job-titles.create');
        abort_unless($nodes->reachesCompanies(), 403);

        $allowed = $units->companies($account->requireId(), $nodes);
        abort_if($allowed === [], 403);

        $selected = CatalogCompaniesData::fromInput(
            $request->all(),
            $allowed,
            fn () => SaveJobTitleData::fromInput($request->all(), [], null),
        );
        $data = SaveJobTitleData::fromInput($request->all(), $selected->ids(), null);

        $save->execute($account->requireId(), $selected->ids(), null, $data);
        ApiPage::toast(count($selected->ids()) > 1 ? 'Funções salvas.' : 'Função salva.');

        return redirect($account->path('funcoes'));
    }

    public function update(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        JobTitleDirectoryQuery $items,
        SaveJobTitleAction $save,
        int $itemId,
    ): RedirectResponse {
        $nodes = $permissions->nodesFor($request->user(), 'account.job-titles.update');
        abort_unless($nodes->reachesCompanies(), 403);

        $item = $items->find($account->requireId(), $nodes, $itemId);
        abort_if($item === null, 404);

        $data = SaveJobTitleData::fromInput($request->all(), [$item->company_id], $item->id);

        $save->execute($account->requireId(), [$item->company_id], $item, $data);
        ApiPage::toast('Função salva.');

        return redirect($account->path('funcoes'));
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
