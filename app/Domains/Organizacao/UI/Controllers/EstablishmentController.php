<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\UI\Controllers;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Acessos\Domain\DTOs\AuthorizedNodes;
use App\Domains\Organizacao\Application\Actions\PromotePrimaryEstablishmentAction;
use App\Domains\Organizacao\Application\Actions\SaveEstablishmentAction;
use App\Domains\Organizacao\Application\Data\EstablishmentFiltersData;
use App\Domains\Organizacao\Application\Data\SaveEstablishmentData;
use App\Domains\Organizacao\Application\Queries\CompanyDirectoryQuery;
use App\Domains\Organizacao\Application\Queries\EstablishmentDirectoryQuery;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Support\ApiPage;
use App\Support\TaxDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class EstablishmentController
{
    public function index(
        Request $request,
        EstablishmentDirectoryQuery $establishments,
        CompanyDirectoryQuery $companies,
        EffectivePermissions $permissions,
    ): JsonResponse|View {
        $accountId = app(AccountContext::class)->requireId();
        $filters = EstablishmentFiltersData::fromInput($request->query());
        $nodes = $permissions->nodesFor($request->user(), 'account.establishments.list');
        abort_if($nodes->isEmpty(), 403);
        $options = $this->companyOptions($companies, $accountId, $filters->companyId, $nodes);
        $list = $establishments->list($accountId, $filters, app(AccountContext::class)->path('estabelecimentos'), nodes: $nodes);

        return ApiPage::respond([
            'establishments' => $list->through(fn (Establishment $establishment) => [
                ...$this->summary($establishment),
                'company' => $this->companyLabel($establishment->company),
            ]),
            'companies' => $options,
            'filters' => $filters->toArray(),
        ]);
    }

    public function form(
        Request $request,
        EstablishmentDirectoryQuery $establishments,
        CompanyDirectoryQuery $companies,
        EffectivePermissions $permissions,
        ?int $establishmentId = null,
    ): JsonResponse|View {
        $accountId = app(AccountContext::class)->requireId();
        $establishment = $establishmentId === null ? null : $establishments->find($accountId, $establishmentId);
        $nodes = $permissions->nodesFor($request->user(), 'account.establishments.create');

        if ($establishment !== null) {
            abort_unless($request->user()->can('account.establishments.update', $this->node($establishment)), 404);
        } else {
            abort_if(! $nodes->tenant && $nodes->companies === [], 403);
        }

        $selectedCompany = $establishment === null ? ($request->integer('empresa') ?: null) : null;

        $companyOptions = $establishment === null
            ? $this->companyOptions($companies, $accountId, $selectedCompany, $nodes)
            : [];

        $companyIds = $establishment === null
            ? array_column($companyOptions, 'id')
            : [$establishment->company_id];

        // A matriz aparece só para quem pode ver estabelecimentos no nó dela.
        $headquarters = $establishments->headquarters(
            $accountId,
            $companyIds,
            $permissions->nodesFor($request->user(), 'account.establishments.view'),
        );

        return ApiPage::respond([
            'establishment' => $establishment === null ? null : [
                ...$this->details($establishment),
                'company' => $this->companyLabel($establishment->company),
            ],
            'companies' => $companyOptions,
            'selectedCompany' => $selectedCompany,
            'headquarters' => $headquarters
                ->map(fn (Establishment $unit) => [
                    'id' => $unit->id,
                    'company_id' => $unit->company_id,
                    'name' => $unit->trade_name ?? $unit->name,
                    'tax_document_label' => TaxDocument::cnpj($unit->tax_document)?->formatted(),
                    'active' => $unit->active,
                ])
                ->values()
                ->all(),
        ]);
    }

    public function preview(Request $request, EstablishmentDirectoryQuery $establishments, int $establishmentId): JsonResponse
    {
        $establishment = $establishments->find(app(AccountContext::class)->requireId(), $establishmentId);

        abort_unless($request->user()->can('account.establishments.view', $this->node($establishment)), 404);

        return response()->json([
            ...$this->details($establishment),
            'company' => $this->companyLabel($establishment->company),
        ]);
    }

    public function save(
        Request $request,
        EstablishmentDirectoryQuery $establishments,
        CompanyDirectoryQuery $companies,
        SaveEstablishmentAction $save,
        ?int $establishmentId = null,
    ): RedirectResponse {
        $accountId = app(AccountContext::class)->requireId();

        if ($establishmentId === null) {
            $companyId = filter_var($request->input('company_id'), FILTER_VALIDATE_INT);

            if ($companyId === false) {
                throw ValidationException::withMessages(['company_id' => 'Selecione a empresa.']);
            }

            $establishment = null;
            abort_if(app(EffectivePermissions::class)->nodesFor($request->user(), 'account.establishments.create')->isEmpty(), 403);
            $company = $companies->find($accountId, $companyId);
            abort_unless($request->user()->can('account.establishments.create', AccessNode::company($accountId, $company->id)), 404);
        } else {
            // A empresa proprietária é fixa: a edição nunca move a unidade para outra pessoa jurídica.
            $establishment = $establishments->find($accountId, $establishmentId);
            abort_unless($request->user()->can('account.establishments.update', $this->node($establishment)), 404);
            $company = $establishment->company;
        }

        $data = SaveEstablishmentData::fromInput($request->all(), $company, $establishment);
        $saved = $save->execute($company, $establishment, $data);

        ApiPage::toast('Estabelecimento salvo.');

        if ($request->query('origem') === 'organizacao') {
            return to_route('account.organization', ['empresa' => $company->id]);
        }

        if ($request->user()->can('account.establishments.list')) {
            return redirect(app(AccountContext::class)->path('estabelecimentos'));
        }

        return redirect(app(AccountContext::class)->path('estabelecimentos/'.$saved->id.'/editar'));
    }

    public function promote(
        Request $request,
        EstablishmentDirectoryQuery $establishments,
        PromotePrimaryEstablishmentAction $promote,
        int $establishmentId,
    ): RedirectResponse {
        $accountId = app(AccountContext::class)->requireId();
        abort_if(app(EffectivePermissions::class)->nodesFor($request->user(), 'account.establishments.promote')->isEmpty(), 403);
        $establishment = $establishments->find($accountId, $establishmentId);
        $node = AccessNode::company($establishment->account_id, $establishment->company_id);
        abort_unless($request->user()->can('account.establishments.promote', $node), 404);
        $promote->execute($establishment);
        ApiPage::toast('Estabelecimento principal alterado.');

        if ($request->query('origem') === 'organizacao') {
            return to_route('account.organization', ['empresa' => $establishment->company_id]);
        }

        return redirect(app(AccountContext::class)->path('estabelecimentos'));
    }

    private function node(Establishment $unit): AccessNode
    {
        return AccessNode::establishment($unit->account_id, $unit->company_id, $unit->id);
    }

    /** @return array<string,mixed> */
    private function summary(Establishment $establishment): array
    {
        $document = TaxDocument::cnpj($establishment->tax_document);

        return [
            'id' => $establishment->id,
            'company_id' => $establishment->company_id,
            'code' => $establishment->code,
            'name' => $establishment->name,
            'trade_name' => $establishment->trade_name,
            'classification' => $establishment->legalClassification(),
            'is_primary' => $establishment->is_primary,
            'active' => $establishment->active,
            'tax_document' => $establishment->tax_document,
            'tax_document_label' => $document?->formatted(),
            'city' => $establishment->address['city'] ?? null,
            'state' => $establishment->address['state'] ?? null,
        ];
    }

    /** @return array<string,mixed> */
    private function details(Establishment $establishment): array
    {
        return [
            ...$this->summary($establishment),
            ...$establishment->only([
                'state_registration',
                'municipal_registration',
                'address',
                'phone',
                'whatsapp',
                'email',
                'additional_phones',
                'additional_emails',
            ]),
        ];
    }

    /** @return array<string,mixed> */
    private function companyLabel(Company $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'trade_name' => $company->trade_name,
            'active' => $company->active,
            'person_type' => $company->person_type,
            'document_label' => $company->taxDocument()?->formatted(),
        ];
    }

    /** @return array<int, array<string,mixed>> */
    private function companyOptions(
        CompanyDirectoryQuery $companies,
        int $accountId,
        ?int $referencedCompanyId,
        AuthorizedNodes $nodes,
    ): array {
        $options = $companies->options($accountId, $nodes);

        if ($referencedCompanyId !== null) {
            abort_unless($options->contains($referencedCompanyId), 404);
        }

        return $options
            ->map(fn (Company $company) => $this->companyLabel($company))
            ->values()
            ->all();
    }
}
