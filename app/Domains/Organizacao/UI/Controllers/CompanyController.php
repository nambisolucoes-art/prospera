<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\UI\Controllers;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Organizacao\Application\Actions\Companies\CreateCompanyAction;
use App\Domains\Organizacao\Application\Actions\Companies\UpdateCompanyAction;
use App\Domains\Organizacao\Application\Data\Companies\CreateCompanyData;
use App\Domains\Organizacao\Application\Data\Companies\ListCompaniesData;
use App\Domains\Organizacao\Application\Data\Companies\UpdateCompanyData;
use App\Domains\Organizacao\Application\Queries\Companies\ListCompaniesQuery;
use App\Domains\Organizacao\Application\Queries\Companies\ShowCompanyQuery;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Support\ApiPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CompanyController
{
    public function index(
        Request $request,
        ListCompaniesQuery $listCompanies,
        EffectivePermissions $permissions,
    ): JsonResponse|View {
        $accountId = app(AccountContext::class)->requireId();
        $filters = ListCompaniesData::fromInput($request->query());

        $nodes = $permissions->nodesFor($request->user(), 'account.companies.list');
        abort_if($nodes->isEmpty(), 403);

        $list = $listCompanies->execute($accountId, $filters, app(AccountContext::class)->path('empresas'), $nodes);

        return ApiPage::respond([
            'companies' => $list->through(fn (Company $company) => [
                ...$this->summary($company),
                'establishments_count' => $company->establishments_count,
            ]),
            'filters' => $filters->toArray(),
        ]);
    }

    public function form(Request $request, ShowCompanyQuery $showCompany, ?int $companyId = null): JsonResponse|View
    {
        $accountId = app(AccountContext::class)->requireId();
        $company = $companyId === null ? null : $showCompany->execute($accountId, $companyId);

        if ($company !== null) {
            abort_unless($request->user()->can('account.companies.update', AccessNode::company($accountId, $company->id)), 404);
        }

        return ApiPage::respond([
            'company' => $company === null ? null : [
                ...$this->summary($company),
                'tax_regime' => $company->tax_regime,
                'main_cnae' => $company->main_cnae,
            ],
            'taxRegimes' => Company::TAX_REGIMES,
        ]);
    }

    public function preview(Request $request, ShowCompanyQuery $showCompany, int $companyId): JsonResponse
    {
        $accountId = app(AccountContext::class)->requireId();
        abort_if(app(EffectivePermissions::class)->nodesFor($request->user(), 'account.companies.view')->isEmpty(), 403);
        $company = $showCompany->execute($accountId, $companyId);
        abort_unless($request->user()->can('account.companies.view', AccessNode::company($accountId, $company->id)), 404);

        return response()->json([
            ...$this->summary($company),
            'tax_regime' => $company->tax_regime === null ? null : Company::TAX_REGIMES[$company->tax_regime],
            'main_cnae' => $company->main_cnae,
        ]);
    }

    public function store(Request $request, CreateCompanyAction $action): RedirectResponse
    {
        $accountId = app(AccountContext::class)->requireId();

        $data = CreateCompanyData::fromInput($request->all(), $accountId);

        $company = $action->execute($data, $accountId);
        ApiPage::toast('Empresa salva com o estabelecimento principal.');

        return $this->afterSave($request, $company);
    }

    public function update(
        Request $request,
        ShowCompanyQuery $showCompany,
        UpdateCompanyAction $action,
        int $companyId,
    ): RedirectResponse {
        $accountId = app(AccountContext::class)->requireId();
        $company = $showCompany->execute($accountId, $companyId);

        abort_unless($request->user()->can('account.companies.update', AccessNode::company($accountId, $company->id)), 404);

        $data = UpdateCompanyData::fromInput($request->all(), $company);

        $action->execute($data, $company);
        ApiPage::toast('Empresa salva.');

        return $this->afterSave($request, $company);
    }

    private function afterSave(Request $request, Company $company): RedirectResponse
    {
        if ($request->query('origem') === 'organizacao') {
            return to_route('account.organization', ['empresa' => $company->id]);
        }

        if ($request->user()->can('account.companies.list')) {
            return redirect(app(AccountContext::class)->path('empresas'));
        }

        return redirect(app(AccountContext::class)->path('empresas/'.$company->id.'/editar'));
    }

    /** @return array<string,mixed> */
    private function summary(Company $company): array
    {
        return [
            'id' => $company->id,
            'code' => $company->code,
            'name' => $company->name,
            'trade_name' => $company->trade_name,
            'person_type' => $company->person_type,
            'document' => $company->taxDocument()?->value(),
            'document_label' => $company->taxDocument()?->formatted(),
            'active' => $company->active,
        ];
    }
}
