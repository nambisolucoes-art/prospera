<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\UI\Controllers;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Organizacao\Application\Actions\CreateCompanyAction;
use App\Domains\Organizacao\Application\Actions\SaveCompanyAction;
use App\Domains\Organizacao\Application\Data\CompanyFiltersData;
use App\Domains\Organizacao\Application\Data\SaveCompanyData;
use App\Domains\Organizacao\Application\Queries\CompanyDirectoryQuery;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Support\ApiPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CompanyController
{
    public function index(Request $request, CompanyDirectoryQuery $companies, EffectivePermissions $permissions): JsonResponse|View
    {
        $accountId = app(AccountContext::class)->requireId();
        $filters = CompanyFiltersData::fromInput($request->query());

        $nodes = $permissions->nodesFor($request->user(), 'account.companies.list');
        abort_if($nodes->isEmpty(), 403);

        $list = $companies->list($accountId, $filters, app(AccountContext::class)->path('empresas'), $nodes);

        return ApiPage::respond([
            'companies' => $list->through(fn (Company $company) => [
                ...$this->summary($company),
                'establishments_count' => $company->establishments_count,
            ]),
            'filters' => $filters->toArray(),
        ]);
    }

    public function form(Request $request, CompanyDirectoryQuery $companies, ?int $companyId = null): JsonResponse|View
    {
        $accountId = app(AccountContext::class)->requireId();
        $company = $companyId === null ? null : $companies->find($accountId, $companyId);

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

    public function preview(Request $request, CompanyDirectoryQuery $companies, int $companyId): JsonResponse
    {
        $accountId = app(AccountContext::class)->requireId();
        abort_if(app(EffectivePermissions::class)->nodesFor($request->user(), 'account.companies.view')->isEmpty(), 403);
        $company = $companies->find($accountId, $companyId);
        abort_unless($request->user()->can('account.companies.view', AccessNode::company($accountId, $company->id)), 404);

        return response()->json([
            ...$this->summary($company),
            'tax_regime' => $company->tax_regime === null ? null : Company::TAX_REGIMES[$company->tax_regime],
            'main_cnae' => $company->main_cnae,
        ]);
    }

    public function save(
        Request $request,
        CompanyDirectoryQuery $companies,
        CreateCompanyAction $create,
        SaveCompanyAction $save,
        ?int $companyId = null,
    ): RedirectResponse {
        $accountId = app(AccountContext::class)->requireId();
        $company = $companyId === null ? null : $companies->find($accountId, $companyId);

        if ($company !== null) {
            abort_unless($request->user()->can('account.companies.update', AccessNode::company($accountId, $company->id)), 404);
        }
        $data = SaveCompanyData::fromInput($request->all(), $accountId, $company);

        if ($company === null) {
            $company = $create->execute($accountId, $data);
            ApiPage::toast('Empresa salva com o estabelecimento principal.');
        } else {
            $save->execute($company, $data);
            ApiPage::toast('Empresa salva.');
        }

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
