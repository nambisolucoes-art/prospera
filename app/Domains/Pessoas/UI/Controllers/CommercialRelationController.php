<?php

namespace App\Domains\Pessoas\UI\Controllers;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Application\Context\OrganizationContext;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Organizacao\Application\Queries\CompanyDirectoryQuery;
use App\Domains\Organizacao\Domain\DTOs\NavigableCompany;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Pessoas\Application\Actions\RegisterCommercialRelationAction;
use App\Domains\Pessoas\Application\Actions\SetCommercialRelationAction;
use App\Domains\Pessoas\Application\Actions\UpdateCommercialRelationAction;
use App\Domains\Pessoas\Application\Data\CommercialRelationFiltersData;
use App\Domains\Pessoas\Application\Data\CommercialRelationLinkData;
use App\Domains\Pessoas\Application\Data\CommercialTagsData;
use App\Domains\Pessoas\Application\Data\SavePersonData;
use App\Domains\Pessoas\Application\Queries\CommercialRelationDirectoryQuery;
use App\Domains\Pessoas\Application\Queries\PeopleDirectoryQuery;
use App\Domains\Pessoas\Application\Queries\PersonAccessQuery;
use App\Domains\Pessoas\Domain\Enums\CommercialRole;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Enums\AccountInspectionMode;
use App\Domains\Plataforma\Infrastructure\Session\AccountInspectionSession;
use App\Models\User;
use App\Support\ApiPage;
use App\Support\TaxDocument;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Clientes e Fornecedores como projeção das relações comerciais por empresa (E07).
 *
 * A listagem fica no grupo de estabelecimento com consolidado: considera as empresas das unidades da URL.
 * As escritas ficam no grupo de empresa, com a empresa responsável explícita na URL.
 */
final class CommercialRelationController
{
    public function index(
        Request $request,
        OrganizationContext $context,
        EffectivePermissions $permissions,
        CommercialRelationDirectoryQuery $relations,
        string $role,
    ): JsonResponse|View {
        $commercialRole = CommercialRole::from($role);
        $user = $request->user();
        $listable = $commercialRole->companies($permissions, $user, 'list');
        abort_if($listable->isEmpty(), 403);

        $contextCompanies = $this->contextCompanies($context, $user);

        $companies = array_filter(
            $contextCompanies,
            fn (NavigableCompany $company): bool => $listable->tenant || in_array($company->id, $listable->companies, true),
        );

        $filters = CommercialRelationFiltersData::fromInput($request->query());
        $companyIds = array_keys($companies);

        if ($filters->company !== null) {
            $chosen = array_filter($companies, fn (NavigableCompany $company): bool => $company->code === $filters->company);
            abort_if($chosen === [], 404);
            $companyIds = array_keys($chosen);
        }

        $page = $relations->list(
            $commercialRole,
            $context->accountId(),
            $companyIds,
            $filters,
            $request->url(),
        );

        $pageTags = $relations->tagsForPage(
            $commercialRole,
            $context->accountId(),
            $page->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->all(),
        );

        $can = fn (string $action, int $companyId): bool => $commercialRole->allows($permissions, $user, $action, $companyId);

        return ApiPage::respond([
            'role' => $commercialRole->value,
            'relations' => $page->through(fn ($row) => [
                'id' => (int) $row->id,
                'person_id' => (int) $row->person_id,
                'name' => $row->name,
                'email' => $row->email,
                'tags' => [
                    $this->roleTag($commercialRole),
                    ...$pageTags->get((int) $row->id, collect())->all(),
                ],
                'document' => $this->documentLabel($row->person_type, $row->document),
                'active' => (bool) $row->active,
                'company' => [
                    'id' => (int) $row->company_id,
                    'code' => $row->company_code,
                    'name' => $row->company_name,
                    'active' => (bool) $row->company_active,
                ],
                'can_activate' => $can('activate', (int) $row->company_id),
                'can_deactivate' => $can('deactivate', (int) $row->company_id),
                'can_view' => $can('view', (int) $row->company_id),
                'can_edit' => $can('update', (int) $row->company_id),
            ]),
            'companies' => array_values(array_map(fn (NavigableCompany $company) => [
                'id' => $company->id,
                'code' => $company->code,
                'name' => $company->name,
                'can_create' => $can('create', $company->id),
            ], $companies)),
            // Empresas da seleção sem permissão de listar: só a quantidade, para orientar, sem os nomes.
            'restrictedCompanies' => count($contextCompanies) - count($companies),
            'filters' => $filters->toArray(),
        ]);
    }

    public function show(
        Request $request,
        OrganizationContext $context,
        EffectivePermissions $permissions,
        CommercialRelationDirectoryQuery $relations,
        int $relationId,
        string $role,
    ): JsonResponse {
        $commercialRole = CommercialRole::from($role);
        $company = $context->company();
        abort_if($company === null, 404);
        abort_unless($commercialRole->allows($permissions, $request->user(), 'view', $company->id), 403);

        $accountId = $context->accountId();
        $personId = $relations->relation($commercialRole, $accountId, $company->id, $relationId);
        abort_if($personId === null, 404);
        $person = $relations->centralPerson($accountId, $personId);
        $canViewCentral = ! $permissions->nodesFor($request->user(), 'account.people.view')->isEmpty();

        return response()->json([
            ...$person->only($canViewCentral
                ? [...Person::IDENTIFICATION_FIELDS, ...Person::CONTACT_FIELDS]
                : ['name', 'person_type']),
            'tags' => [
                $this->roleTag($commercialRole),
                ...$relations->tagsForPage($commercialRole, $accountId, [$relationId])
                    ->get($relationId, collect())->all(),
            ],
        ], headers: ['Cache-Control' => 'private, no-store']);
    }

    /** @return array{id: int, name: string, color: string} */
    private function roleTag(CommercialRole $role): array
    {
        return ['id' => 0, 'name' => $role->label(), 'color' => $role->tagColor()];
    }

    public function create(
        Request $request,
        OrganizationContext $context,
        EffectivePermissions $permissions,
        PeopleDirectoryQuery $people,
        string $role,
    ): JsonResponse|View {
        $inspection = AccountInspectionSession::current($request->session());
        abort_if($inspection?->mode === AccountInspectionMode::ReadOnly, 403);

        $commercialRole = CommercialRole::from($role);
        $user = $request->user();
        $writable = $commercialRole->companies($permissions, $user, 'create');
        $listable = $commercialRole->companies($permissions, $user, 'list');
        $companies = array_filter(
            $this->contextCompanies($context, $user),
            fn (NavigableCompany $company): bool => ($writable->tenant
                || in_array($company->id, $writable->companies, true))
                && ($listable->tenant || in_array($company->id, $listable->companies, true)),
        );

        abort_if($companies === [], 403);

        return ApiPage::respond([
            'role' => $commercialRole->value,
            'companies' => array_values(array_map(fn (NavigableCompany $company) => [
                'code' => $company->code,
                'name' => $company->name,
            ], $companies)),
            'canCreatePeople' => ! $permissions->nodesFor($user, 'account.people.create')->isEmpty(),
            'registrationTags' => $this->registrationTags($people, $context->accountId(), $commercialRole),
            'roleTagColor' => $commercialRole->tagColor(),
        ]);
    }

    public function edit(
        Request $request,
        OrganizationContext $context,
        EffectivePermissions $permissions,
        CommercialRelationDirectoryQuery $relations,
        PeopleDirectoryQuery $people,
        PersonAccessQuery $access,
        string $companyCode,
        int $relationId,
        string $role,
    ): JsonResponse|View {
        $inspection = AccountInspectionSession::current($request->session());
        abort_if($inspection?->mode === AccountInspectionMode::ReadOnly, 403);

        $commercialRole = CommercialRole::from($role);
        $user = $request->user();
        $company = collect($this->contextCompanies($context, $user))->firstWhere('code', $companyCode);
        abort_if($company === null || ! $company->active, 404);
        abort_unless($commercialRole->allows($permissions, $user, 'update', $company->id), 403);

        $accountId = $context->accountId();
        $personId = $relations->relation($commercialRole, $accountId, $company->id, $relationId);
        abort_if($personId === null, 404);

        $person = $relations->centralPerson($accountId, $personId);
        $canView = ! $permissions->nodesFor($user, 'account.people.view')->isEmpty();
        $authority = $access->authority($user, $person);
        $tagIds = array_values($relations->tagsForPage($commercialRole, $accountId, [$relationId])
            ->get($relationId, collect())->pluck('id')->map(fn ($id): int => (int) $id)->all());

        return ApiPage::respond([
            'role' => $commercialRole->value,
            'companies' => [['code' => $company->code, 'name' => $company->name]],
            'canCreatePeople' => false,
            'registrationTags' => $this->registrationTags($people, $accountId, $commercialRole, $tagIds),
            'roleTagColor' => $commercialRole->tagColor(),
            'editing' => [
                'id' => $relationId,
                'name' => $person->name,
                'central' => $canView ? $person->only([
                    'id', ...Person::IDENTIFICATION_FIELDS, ...Person::CONTACT_FIELDS,
                ]) : null,
                'can_edit_identification' => $canView && $authority->identification,
                'can_edit_contacts' => $canView && $authority->contacts,
                'tag_ids' => $tagIds,
            ],
        ]);
    }

    /**
     * @param  list<int>  $selected
     * @return Collection<int, Tag>
     */
    private function registrationTags(
        PeopleDirectoryQuery $people,
        int $accountId,
        CommercialRole $role,
        array $selected = [],
    ): Collection {
        return $people->tags($accountId)
            ->where('module', $role->table())
            ->whereNull('system_key')
            ->where(fn ($query) => $query->where('active', true)->orWhereIn('id', $selected))
            ->get(['id', 'name', 'color']);
    }

    /** @return array<int, NavigableCompany> */
    private function contextCompanies(OrganizationContext $context, User $user): array
    {
        // Empresas das unidades da URL, uma vez cada, mesmo com várias unidades da mesma empresa.
        $contextCompanies = [];
        $establishment = $context->establishment();

        if ($establishment !== null) {
            $contextCompanies[$establishment->company->id] = $establishment->company;
        } else {
            $selected = array_flip($context->selectedCodes());

            foreach ($context->navigable($user) as $unit) {
                if (isset($selected[$unit->code])) {
                    $contextCompanies[$unit->company->id] = $unit->company;
                }
            }
        }

        return $contextCompanies;
    }

    /**
     * Descoberta de pessoa existente pelo CPF ou CNPJ completo, para vincular sem duplicar o cadastro.
     *
     * Exige poder cadastrar a relação nesta empresa e não revela relações de outras empresas.
     */
    public function findPerson(
        Request $request,
        OrganizationContext $context,
        EffectivePermissions $permissions,
        CommercialRelationDirectoryQuery $relations,
        PersonAccessQuery $access,
        string $role,
    ): JsonResponse {
        $commercialRole = CommercialRole::from($role);
        $company = $context->company();
        abort_if($company === null, 404);
        abort_unless($commercialRole->allows($permissions, $request->user(), 'create', $company->id), 403);

        $lookup = CommercialRelationLinkData::discovery($request->post('documento'), $request->post('person_type'));
        $accountId = $context->accountId();
        $headers = ['Cache-Control' => 'private, no-store'];

        try {
            $person = $relations->findByDocument(
                $commercialRole,
                $accountId,
                $company->id,
                $lookup['document'],
                $lookup['person_type'],
            );
        } catch (QueryException) {
            // A exceção do banco pode incluir o documento nos parâmetros da consulta.
            Log::error('Falha na consulta de pessoa para vínculo comercial.', [
                'account_id' => $accountId,
                'company_id' => $company->id,
                'role' => $commercialRole->value,
            ]);

            return response()->json(['message' => 'Não foi possível buscar a pessoa.'], 503, $headers);
        }

        if ($person !== null) {
            $opposite = $commercialRole === CommercialRole::Customer ? CommercialRole::Supplier : CommercialRole::Customer;
            if ($opposite->allows($permissions, $request->user(), 'view', $company->id)
                && $relations->hasActiveRole($opposite, $accountId, $company->id, $person['id'])) {
                $person['other_role'] = $opposite->label();
            }

            if (! $permissions->nodesFor($request->user(), 'account.people.view')->isEmpty()) {
                $central = $relations->centralPerson($accountId, $person['id']);
                $authority = $access->authority($request->user(), $central);
                $person['central'] = $central->only([
                    'id', ...Person::IDENTIFICATION_FIELDS, ...Person::CONTACT_FIELDS,
                ]);
                $person['can_edit_identification'] = $authority->identification;
                $person['can_edit_contacts'] = $authority->contacts;
            }
        }

        return response()->json(['person' => $person], headers: $headers);
    }

    public function store(
        Request $request,
        OrganizationContext $context,
        AccountContext $accountContext,
        EffectivePermissions $permissions,
        CompanyDirectoryQuery $companies,
        RegisterCommercialRelationAction $register,
        string $role,
    ): Response {
        $commercialRole = CommercialRole::from($role);
        $company = $this->contextCompany($context, $companies);

        abort_unless($commercialRole->allows($permissions, $request->user(), 'create', $company->id), 403);

        $account = $accountContext->requireAccount(404);
        $tags = CommercialTagsData::fromInput($request->input('tag_ids'));

        $centralChanges = [];

        if ($request->has('person_id')) {
            $person = CommercialRelationLinkData::personId($request->input('person_id'));
            $centralChanges = $request->only([...Person::IDENTIFICATION_FIELDS, ...Person::CONTACT_FIELDS]);
        } else {
            abort_if($permissions->nodesFor($request->user(), 'account.people.create')->isEmpty(), 403);

            $person = SavePersonData::fromInput(
                $request->only([...Person::IDENTIFICATION_FIELDS, ...Person::CONTACT_FIELDS]),
                $account->id,
            );
        }

        $register->execute($commercialRole, $account, $company, $person, $tags, $centralChanges);
        ApiPage::toast($commercialRole->label().' cadastrado.');

        return response()->noContent();
    }

    public function update(
        Request $request,
        OrganizationContext $context,
        AccountContext $accountContext,
        EffectivePermissions $permissions,
        CompanyDirectoryQuery $companies,
        UpdateCommercialRelationAction $update,
        int $relationId,
        string $role,
    ): Response {
        $commercialRole = CommercialRole::from($role);
        $company = $this->contextCompany($context, $companies);
        abort_unless($commercialRole->allows($permissions, $request->user(), 'update', $company->id), 403);

        $centralChanges = $request->only([...Person::IDENTIFICATION_FIELDS, ...Person::CONTACT_FIELDS]);
        if ($centralChanges !== []) {
            abort_if($permissions->nodesFor($request->user(), 'account.people.view')->isEmpty(), 403);
        }

        $tags = $request->has('tag_ids') || $request->boolean('edit_tags')
            ? CommercialTagsData::fromInput($request->input('tag_ids'))
            : null;

        $update->execute(
            $commercialRole,
            $accountContext->requireAccount(404),
            $company,
            $relationId,
            $tags,
            $centralChanges,
        );
        ApiPage::toast($commercialRole->label().' atualizado.');

        return response()->noContent();
    }

    // O parâmetro da URL chega antes do papel, que é o padrão declarado na rota.
    public function activate(
        Request $request,
        OrganizationContext $context,
        EffectivePermissions $permissions,
        CompanyDirectoryQuery $companies,
        CommercialRelationDirectoryQuery $relations,
        SetCommercialRelationAction $set,
        int $relationId,
        string $role,
    ): Response {
        return $this->changeStatus($context, $companies, $relations, $set, $role, $relationId, true, $request, $permissions);
    }

    // O parâmetro da URL chega antes do papel, que é o padrão declarado na rota.
    public function deactivate(
        Request $request,
        OrganizationContext $context,
        EffectivePermissions $permissions,
        CompanyDirectoryQuery $companies,
        CommercialRelationDirectoryQuery $relations,
        SetCommercialRelationAction $set,
        int $relationId,
        string $role,
    ): Response {
        return $this->changeStatus($context, $companies, $relations, $set, $role, $relationId, false, $request, $permissions);
    }

    private function changeStatus(
        OrganizationContext $context,
        CompanyDirectoryQuery $companies,
        CommercialRelationDirectoryQuery $relations,
        SetCommercialRelationAction $set,
        string $role,
        int $relationId,
        bool $active,
        Request $request,
        EffectivePermissions $permissions,
    ): Response {
        $commercialRole = CommercialRole::from($role);
        $company = $this->contextCompany($context, $companies);

        $action = $active ? 'activate' : 'deactivate';
        abort_unless($commercialRole->allows($permissions, $request->user(), $action, $company->id), 403);

        // Relação de outra empresa ou de outra conta responde 404, como um ID inexistente.
        $personId = $relations->relation($commercialRole, $company->account_id, $company->id, $relationId);
        abort_if($personId === null, 404);

        $set->execute($commercialRole, $company, $personId, active: $active, link: false);
        ApiPage::toast($active ? 'Relação ativada.' : 'Relação inativada.');

        return response()->noContent();
    }

    private function contextCompany(OrganizationContext $context, CompanyDirectoryQuery $companies): Company
    {
        $company = $context->company();
        abort_if($company === null, 404);

        return $companies->find($context->accountId(), $company->id);
    }

    private function documentLabel(?string $personType, ?string $document): string
    {
        $taxDocument = $personType === 'company' ? TaxDocument::cnpj($document) : TaxDocument::cpf($document);

        return $taxDocument?->formatted() ?? 'Não informado';
    }
}
