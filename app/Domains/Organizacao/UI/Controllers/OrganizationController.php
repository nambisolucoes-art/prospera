<?php

namespace App\Domains\Organizacao\UI\Controllers;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Organizacao\Application\Data\CompanyFiltersData;
use App\Domains\Organizacao\Application\Data\EstablishmentFiltersData;
use App\Domains\Organizacao\Application\Queries\CompanyDirectoryQuery;
use App\Domains\Organizacao\Application\Queries\EstablishmentDirectoryQuery;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Support\ApiPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class OrganizationController
{
    public function __invoke(
        Request $request,
        AccountContext $context,
        CompanyDirectoryQuery $companies,
        EstablishmentDirectoryQuery $establishments,
    ): JsonResponse|View {
        $user = $request->user();
        $listCompanies = $user->can('account.companies.list');
        $listUnits = $user->can('account.establishments.list');

        abort_unless(
            $listCompanies || $listUnits || $user->can('account.account-data.update')
                || $user->can('account.companies.create'),
            403,
        );

        $account = $context->requireAccount();
        $path = '/conta/organizacao';
        $requestedCompany = $request->integer('empresa') ?: null;
        $companyPage = $listCompanies
            ? $companies->list($account->id, CompanyFiltersData::fromInput([]), $path)
            : null;

        $expanded = null;
        $focusedCompany = null;

        if ($listCompanies && $requestedCompany !== null) {
            $listedCompany = $companyPage?->getCollection()->find($requestedCompany);
            $focusedCompany = $listedCompany === null
                ? $companies->find($account->id, $requestedCompany)
                : null;

            $expanded = $requestedCompany;
        } elseif ($listUnits && $requestedCompany !== null) {
            $expanded = $companies->find($account->id, $requestedCompany)->id;
        } elseif ($listUnits && (
            $request->query('nivel') === 'estabelecimentos'
            || ($companyPage?->total() === 1 && ! $request->boolean('recolhida'))
        )) {
            $expanded = $companyPage?->first()?->id;
        }

        $unitPage = $listUnits && (! $listCompanies || $expanded !== null)
            ? $establishments->list(
                $account->id,
                EstablishmentFiltersData::fromInput(['company' => $expanded]),
                $path,
                'unidades',
            )
            : null;

        return ApiPage::respond([
            'account' => $account->only(['name']),
            'companies' => $companyPage?->through($this->company(...)),
            'focusedCompany' => $focusedCompany === null ? null : $this->company($focusedCompany),
            'establishments' => $unitPage?->through(fn (Establishment $unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
                'trade_name' => $unit->trade_name,
                'classification' => $unit->legalClassification(),
                'is_primary' => $unit->is_primary,
                'active' => $unit->active,
                'company_name' => $unit->company->trade_name ?: $unit->company->name,
            ]),
            'expandedCompany' => $expanded,
            'level' => $request->query('nivel') === 'conta' ? 'conta' : null,
        ]);
    }

    /** @return array<string, mixed> */
    private function company(Company $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'trade_name' => $company->trade_name,
            'active' => $company->active,
        ];
    }
}
