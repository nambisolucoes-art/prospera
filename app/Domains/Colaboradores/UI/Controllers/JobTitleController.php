<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\UI\Controllers;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Colaboradores\Application\Actions\JobTitles\CreateJobTitleAction;
use App\Domains\Colaboradores\Application\Actions\JobTitles\UpdateJobTitleAction;
use App\Domains\Colaboradores\Application\Data\JobTitles\CreateJobTitleData;
use App\Domains\Colaboradores\Application\Data\JobTitles\ListJobTitlesData;
use App\Domains\Colaboradores\Application\Data\JobTitles\UpdateJobTitleData;
use App\Domains\Colaboradores\Application\Queries\JobTitles\ListJobTitlesQuery;
use App\Domains\Colaboradores\Application\Queries\JobTitles\ShowJobTitleQuery;
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
        ListJobTitlesQuery $query,
    ): JsonResponse|View {
        $accountId = $account->requireId();

        $nodes = $permissions->nodesFor($request->user(), 'account.job-titles.list');
        abort_unless($nodes->reachesCompanies(), 403);

        $filters = ListJobTitlesData::fromInput($request->query());

        $list = $query->execute($accountId, $nodes, $filters, $account->path('funcoes'));

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
        ShowJobTitleQuery $query,
        int $itemId,
    ): JsonResponse {
        $nodes = $permissions->nodesFor($request->user(), 'account.job-titles.view');
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
        CreateJobTitleAction $action,
    ): RedirectResponse {
        $nodes = $permissions->nodesFor($request->user(), 'account.job-titles.create');
        abort_unless($nodes->reachesCompanies(), 403);

        $allowed = $units->companies($account->requireId(), $nodes);
        abort_if($allowed === [], 403);

        $data = CreateJobTitleData::fromInput($request->all(), $allowed);

        $action->execute($data, $account->requireId());
        ApiPage::toast(count($data->companies) > 1 ? 'Funções salvas.' : 'Função salva.');

        return redirect($account->path('funcoes'));
    }

    public function update(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        ShowJobTitleQuery $query,
        UpdateJobTitleAction $action,
        int $itemId,
    ): RedirectResponse {
        $nodes = $permissions->nodesFor($request->user(), 'account.job-titles.update');
        abort_unless($nodes->reachesCompanies(), 403);

        $item = $query->execute($account->requireId(), $nodes, $itemId);
        abort_if($item === null, 404);

        $data = UpdateJobTitleData::fromInput($request->all(), $item->company_id, $item->id);

        $action->execute($data, $item);
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
