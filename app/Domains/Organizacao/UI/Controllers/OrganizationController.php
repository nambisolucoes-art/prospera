<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\UI\Controllers;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Organizacao\Application\Data\Companies\ListCompaniesData;
use App\Domains\Organizacao\Application\Data\Establishments\ListEstablishmentsData;
use App\Domains\Organizacao\Application\Queries\CompanyDirectoryQuery;
use App\Domains\Organizacao\Application\Queries\EstablishmentDirectoryQuery;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Models\User;
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
        EffectivePermissions $permissions,
    ): JsonResponse|View {
        $user = $request->user();
        $companyNodes = $permissions->nodesFor($user, 'account.companies.list');
        $unitNodes = $permissions->nodesFor($user, 'account.establishments.list');
        $listCompanies = ! $companyNodes->isEmpty();
        $listUnits = ! $unitNodes->isEmpty();

        abort_unless(
            $listCompanies || $listUnits || $user->can('account.account-data.update')
                || $user->can('account.companies.create'),
            403,
        );

        $account = $context->requireAccount();
        $path = $context->path('organizacao');
        $requestedCompany = $request->integer('empresa') ?: null;
        $companyPage = $listCompanies
            ? $companies->list($account->id, ListCompaniesData::fromInput([]), $path, $companyNodes)
            : null;

        $expanded = null;
        $focusedCompany = null;

        if ($listCompanies && $requestedCompany !== null) {
            abort_unless($companyNodes->covers(AccessNode::company($account->id, $requestedCompany)), 404);
            $listedCompany = $companyPage?->getCollection()->find($requestedCompany);
            $focusedCompany = $listedCompany === null
                ? $companies->find($account->id, $requestedCompany)
                : null;

            $expanded = $requestedCompany;
        } elseif ($listUnits && $requestedCompany !== null) {
            $allowed = $companies->options($account->id, $unitNodes);
            abort_unless($allowed->contains($requestedCompany), 404);
            $expanded = $requestedCompany;
        } elseif ($listUnits && (
            $request->query('nivel') === 'estabelecimentos'
            || ($companyPage?->total() === 1 && ! $request->boolean('recolhida'))
        )) {
            $expanded = $companyPage?->first()?->id;
        }

        $unitPage = $listUnits && (! $listCompanies || $expanded !== null)
            ? $establishments->list(
                $account->id,
                ListEstablishmentsData::fromInput(['company' => $expanded]),
                $path,
                'unidades',
                $unitNodes,
            )
            : null;

        return ApiPage::respond([
            'account' => $account->only(['name']),
            'companies' => $companyPage?->through(fn (Company $company) => $this->company($user, $company)),
            'focusedCompany' => $focusedCompany === null ? null : $this->company($user, $focusedCompany),
            'establishments' => $unitPage?->through(fn (Establishment $unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
                'trade_name' => $unit->trade_name,
                'classification' => $unit->legalClassification(),
                'is_primary' => $unit->is_primary,
                'active' => $unit->active,
                'company_name' => $unit->company->trade_name ?: $unit->company->name,
                'can_view' => $user->can('account.establishments.view', AccessNode::establishment($unit->account_id, $unit->company_id, $unit->id)),
                'can_update' => $user->can('account.establishments.update', AccessNode::establishment($unit->account_id, $unit->company_id, $unit->id)),
            ]),
            'expandedCompany' => $expanded,
            'level' => $request->query('nivel') === 'conta' ? 'conta' : null,
        ]);
    }

    /** @return array<string, mixed> */
    private function company(User $user, Company $company): array
    {
        $node = AccessNode::company($company->account_id, $company->id);

        return [
            'can_view' => $user->can('account.companies.view', $node),
            'can_update' => $user->can('account.companies.update', $node),
            'can_add_establishments' => $user->can('account.establishments.create', $node),
            'id' => $company->id,
            'name' => $company->name,
            'trade_name' => $company->trade_name,
            'active' => $company->active,
        ];
    }
}
