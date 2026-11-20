<?php

namespace App\Domains\Organizacao\UI\Controllers;

use App\Domains\Acessos\Application\Context\AccountContext;
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
    ): JsonResponse|View {
        $accountId = app(AccountContext::class)->requireId();
        $filters = EstablishmentFiltersData::fromInput($request->query());
        $options = $this->companyOptions($companies, $accountId, $filters->companyId);

        $list = $establishments->list($accountId, $filters, '/conta/estabelecimentos');

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
        ?int $establishmentId = null,
    ): JsonResponse|View {
        $accountId = app(AccountContext::class)->requireId();
        $establishment = $establishmentId === null ? null : $establishments->find($accountId, $establishmentId);
        $selectedCompany = $establishment === null ? ($request->integer('empresa') ?: null) : null;

        return ApiPage::respond([
            'establishment' => $establishment === null ? null : [
                ...$this->details($establishment),
                'company' => $this->companyLabel($establishment->company),
            ],
            'companies' => $this->companyOptions($companies, $accountId, $selectedCompany),
            'selectedCompany' => $selectedCompany,
        ]);
    }

    public function preview(EstablishmentDirectoryQuery $establishments, int $establishmentId): JsonResponse
    {
        $establishment = $establishments->find(app(AccountContext::class)->requireId(), $establishmentId);

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
            $company = $companies->find($accountId, $companyId);
        } else {
            // A empresa proprietária é fixa: a edição nunca move a unidade para outra pessoa jurídica.
            $establishment = $establishments->find($accountId, $establishmentId);
            $company = $establishment->company;
        }

        $data = SaveEstablishmentData::fromInput($request->all(), $company, $establishment);
        $saved = $save->execute($company, $establishment, $data);

        ApiPage::toast('Estabelecimento salvo.');

        if ($request->query('origem') === 'organizacao') {
            return to_route('account.organization', ['empresa' => $company->id]);
        }

        if ($request->user()->can('account.establishments.list')) {
            return redirect('/conta/estabelecimentos');
        }

        return redirect('/conta/estabelecimentos/'.$saved->id.'/editar');
    }

    public function promote(
        Request $request,
        EstablishmentDirectoryQuery $establishments,
        PromotePrimaryEstablishmentAction $promote,
        int $establishmentId,
    ): RedirectResponse {
        $establishment = $establishments->find(app(AccountContext::class)->requireId(), $establishmentId);

        $promote->execute($establishment);
        ApiPage::toast('Estabelecimento principal alterado.');

        if ($request->query('origem') === 'organizacao') {
            return to_route('account.organization', ['empresa' => $establishment->company_id]);
        }

        return redirect('/conta/estabelecimentos');
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
    ): array {
        $options = $companies->options($accountId);

        if ($referencedCompanyId !== null) {
            abort_unless($options->contains($referencedCompanyId), 404);
        }

        return $options
            ->map(fn (Company $company) => $this->companyLabel($company))
            ->values()
            ->all();
    }
}
