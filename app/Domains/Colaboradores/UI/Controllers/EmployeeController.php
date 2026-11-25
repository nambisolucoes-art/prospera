<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\UI\Controllers;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use App\Domains\Colaboradores\Application\Actions\Employees\CreateEmployeeAction;
use App\Domains\Colaboradores\Application\Actions\Employees\UpdateEmployeeAction;
use App\Domains\Colaboradores\Application\Actions\Employees\UpdateEmployeePersonAction;
use App\Domains\Colaboradores\Application\Data\Employees\CreateEmployeeData;
use App\Domains\Colaboradores\Application\Data\Employees\ListEmployeesData;
use App\Domains\Colaboradores\Application\Data\Employees\UpdateEmployeeData;
use App\Domains\Colaboradores\Application\Queries\Employees\FindEmployeePersonQuery;
use App\Domains\Colaboradores\Application\Queries\Employees\ListEmployeeLinksQuery;
use App\Domains\Colaboradores\Application\Queries\Employees\ListEmployeesQuery;
use App\Domains\Colaboradores\Application\Queries\Employees\ListEmployeeTagIdsQuery;
use App\Domains\Colaboradores\Application\Queries\Employees\PersonHasAccountAccessQuery;
use App\Domains\Colaboradores\Application\Queries\Employees\ShowEmployeeLinkQuery;
use App\Domains\Colaboradores\Application\Queries\Employees\ShowEmployeePersonQuery;
use App\Domains\Colaboradores\Domain\Models\Employee;
use App\Domains\Etiquetas\Application\Queries\Tags\ListSelectableTagsQuery;
use App\Domains\Organizacao\Application\Queries\Context\ListNavigableCompaniesQuery;
use App\Domains\Organizacao\Domain\ValueObjects\NavigableCompany;
use App\Domains\Pessoas\Application\Data\CommercialRelations\CommercialPersonChangesData;
use App\Domains\Pessoas\Application\Data\CommercialRelations\CommercialRelationLinkData;
use App\Domains\Pessoas\Application\Queries\ListDerivedPersonTagsQuery;
use App\Domains\Pessoas\Application\Queries\PersonAccessQuery;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Enums\AccountInspectionMode;
use App\Domains\Plataforma\Infrastructure\Session\AccountInspectionSession;
use App\Support\ApiPage;
use App\Support\TaxDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Colaboradores de todas as empresas que o acesso alcança, numa única tela (DA2-24). A listagem traz uma linha por
 * Pessoa e junta só os vínculos que o operador está autorizado a ver. A empresa de cada operação vem do descritor
 * da permissão e das empresas escolhidas, validadas no servidor.
 */
final class EmployeeController
{
    public function index(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        ListNavigableCompaniesQuery $listNavigableCompanies,
        ListEmployeesQuery $listEmployees,
        ListEmployeeLinksQuery $listLinks,
    ): JsonResponse|View {
        $user = $request->user();
        $accountId = $account->requireId();

        $nodes = $permissions->nodesFor($user, 'account.employees.list');
        abort_unless($nodes->reachesCompanies(), 403);

        $canViewPeople = ! $permissions->nodesFor($user, 'account.people.view')->isEmpty();
        $canSeeAccess = ! $permissions->nodesFor($user, 'account.users.list')->isEmpty();

        $filters = ListEmployeesData::fromInput($request->query(), $canSeeAccess);

        $path = $account->path('colaboradores');
        $page = $listEmployees->execute($accountId, $nodes, $filters, $canViewPeople, $canSeeAccess, $path);

        $pageIds = array_values($page->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->all());
        $links = $listLinks->execute($accountId, $nodes, $pageIds)->groupBy('person_id');

        $viewable = $permissions->nodesFor($user, 'account.employees.view');
        $updatable = $permissions->nodesFor($user, 'account.employees.update');
        $activatable = $permissions->nodesFor($user, 'account.employees.activate');
        $deactivatable = $permissions->nodesFor($user, 'account.employees.deactivate');
        $creatable = $permissions->nodesFor($user, 'account.employees.create');

        return ApiPage::respond([
            'employees' => $page->through(function (Person $person) use (
                $links,
                $viewable,
                $updatable,
                $activatable,
                $deactivatable,
                $canViewPeople,
                $canSeeAccess,
            ) {
                $own = $links->get($person->id, collect());

                $row = [
                    'id' => $person->id,
                    'name' => $person->name,
                    'document' => $canViewPeople ? $this->documentLabel($person->person_type, $person->document) : null,
                    'links' => $own->map(fn (Employee $link) => [
                        'id' => $link->id,
                        'company_code' => (string) $link->getAttribute('company_code'),
                        'company_name' => (string) $link->getAttribute('company_name'),
                        'company_active' => (bool) $link->getAttribute('company_active'),
                        'active' => $link->active,
                    ])->values()->all(),
                    'links_count' => $own->count(),
                    'active_count' => $own->where('active', true)->count(),
                    'can_view' => $own->contains(fn (Employee $link) => $this->covers($viewable, $link->company_id)),
                    'can_edit' => $own->contains(fn (Employee $link) => $this->covers($viewable, $link->company_id)
                        && ($this->covers($updatable, $link->company_id)
                            || $this->covers($activatable, $link->company_id)
                            || $this->covers($deactivatable, $link->company_id))),
                ];

                // O indicador só existe na resposta de quem lista usuários: sem a chave, sem diferença visível.
                if ($canSeeAccess) {
                    $row['has_access'] = (bool) $person->getAttribute('has_access');
                }

                return $row;
            }),
            'filters' => $filters->toArray(),
            'companies' => $this->options($listNavigableCompanies->execute($accountId, $nodes)),
            'canCreate' => $creatable->reachesCompanies(),
            'canSeeAccess' => $canSeeAccess,
            'canViewPeople' => $canViewPeople,
        ]);
    }

    public function create(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        ListNavigableCompaniesQuery $listNavigableCompanies,
        ListSelectableTagsQuery $selectableTags,
    ): JsonResponse|View {
        abort_if(AccountInspectionSession::current($request->session())?->mode === AccountInspectionMode::ReadOnly, 403);

        $nodes = $permissions->nodesFor($request->user(), 'account.employees.create');
        abort_unless($nodes->reachesCompanies(), 403);

        $companies = $listNavigableCompanies->execute($account->requireId(), $nodes);
        abort_if($companies === [], 403);

        return ApiPage::respond([
            'companies' => $this->options($companies),
            'canCreatePeople' => ! $permissions->nodesFor($request->user(), 'account.people.create')->isEmpty(),
            'registrationTags' => $selectableTags->execute($account->requireId(), 'employees'),
        ]);
    }

    /**
     * Descoberta de pessoa existente pelo CPF ou CNPJ completo, para vincular sem duplicar o cadastro. Fica
     * restrita à conta e só informa a situação dos vínculos nas empresas em que o operador pode cadastrar.
     */
    public function findPerson(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        ListNavigableCompaniesQuery $listNavigableCompanies,
        FindEmployeePersonQuery $findPerson,
    ): JsonResponse {
        $accountId = $account->requireId();

        $nodes = $permissions->nodesFor($request->user(), 'account.employees.create');
        abort_unless($nodes->reachesCompanies(), 403);

        $lookup = CommercialRelationLinkData::discovery($request->post('documento'), $request->post('person_type'));

        $companyIds = array_map(
            fn (NavigableCompany $company) => $company->id,
            $listNavigableCompanies->execute($accountId, $nodes),
        );

        $person = $findPerson->execute($accountId, $lookup['document'], $lookup['person_type'], $companyIds);

        return response()->json(['person' => $person], headers: ['Cache-Control' => 'private, no-store']);
    }

    public function store(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        ListNavigableCompaniesQuery $listNavigableCompanies,
        CreateEmployeeAction $action,
    ): Response {
        $nodes = $permissions->nodesFor($request->user(), 'account.employees.create');
        abort_unless($nodes->reachesCompanies(), 403);

        $allowed = $listNavigableCompanies->execute($account->requireId(), $nodes);
        abort_if($allowed === [], 403);

        $data = CreateEmployeeData::fromInput($request->all(), $allowed, $account->requireId());

        $action->execute($data, $account->requireAccount(404));
        ApiPage::toast(count($data->links) > 1 ? 'Colaboradores cadastrados.' : 'Colaborador cadastrado.');

        return response()->noContent();
    }

    public function show(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        ShowEmployeePersonQuery $showPerson,
        ListDerivedPersonTagsQuery $derivedTags,
        int $personId,
    ): JsonResponse {
        $user = $request->user();
        $accountId = $account->requireId();

        $nodes = $permissions->nodesFor($user, 'account.employees.view');
        abort_unless($nodes->reachesCompanies(), 403);

        $person = $showPerson->execute($accountId, $nodes, $personId);
        abort_if($person === null, 404);

        $canViewPeople = ! $permissions->nodesFor($user, 'account.people.view')->isEmpty();

        return response()->json([
            ...$person->only($canViewPeople
                ? [...Person::IDENTIFICATION_FIELDS, ...Person::CONTACT_FIELDS]
                : ['name', 'person_type']),
            'tags' => $derivedTags->execute($accountId, [$person->id], $user)[$person->id] ?? [],
        ], headers: ['Cache-Control' => 'private, no-store']);
    }

    public function edit(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        ListNavigableCompaniesQuery $listNavigableCompanies,
        ShowEmployeePersonQuery $showPerson,
        ListEmployeeLinksQuery $listLinks,
        FindEmployeePersonQuery $findPerson,
        PersonHasAccountAccessQuery $hasAccess,
        ListDerivedPersonTagsQuery $derivedTags,
        PersonAccessQuery $personAccess,
        ListEmployeeTagIdsQuery $linkTagIds,
        ListSelectableTagsQuery $selectableTags,
        int $personId,
    ): JsonResponse|View {
        $user = $request->user();
        $accountId = $account->requireId();

        $viewable = $permissions->nodesFor($user, 'account.employees.view');
        abort_unless($viewable->reachesCompanies(), 403);

        $person = $showPerson->execute($accountId, $viewable, $personId);
        abort_if($person === null, 404);

        $updatable = $permissions->nodesFor($user, 'account.employees.update');
        $activatable = $permissions->nodesFor($user, 'account.employees.activate');
        $deactivatable = $permissions->nodesFor($user, 'account.employees.deactivate');
        $creatable = $permissions->nodesFor($user, 'account.employees.create');

        $links = $listLinks->execute($accountId, $viewable, [$person->id]);

        $canViewPeople = ! $permissions->nodesFor($user, 'account.people.view')->isEmpty();
        $authority = $personAccess->authority($user, $person);
        $canEditCentral = $canViewPeople
            && $updatable->reachesCompanies()
            && $links->contains(fn (Employee $link) => $this->covers($updatable, $link->company_id));

        $linkedCodes = $links->pluck('company_code')->all();
        $linkable = array_values(array_filter(
            $listNavigableCompanies->execute($accountId, $creatable),
            fn (NavigableCompany $company) => ! in_array($company->code, $linkedCodes, true),
        ));

        // Empresas em que o operador pode cadastrar e o vínculo já existe, ainda que não possa vê-lo.
        $hidden = $findPerson->execute(
            $accountId,
            (string) $person->document,
            $person->person_type,
            array_map(fn (NavigableCompany $company) => $company->id, $linkable),
        );
        $takenCodes = array_column($hidden['links'] ?? [], 'code');
        $linkable = array_values(array_filter(
            $linkable,
            fn (NavigableCompany $company) => ! in_array($company->code, $takenCodes, true),
        ));

        $canSeeAccess = ! $permissions->nodesFor($user, 'account.users.list')->isEmpty();

        $tagIds = $linkTagIds->execute($accountId, array_values($links->pluck('id')->map(fn ($id) => (int) $id)->all()));

        return ApiPage::respond([
            'registrationTags' => $selectableTags->execute($accountId, 'employees', array_merge([], ...array_values($tagIds))),
            'person' => [
                'id' => $person->id,
                'name' => $person->name,
                'central' => $canViewPeople
                    ? $person->only(['id', ...Person::IDENTIFICATION_FIELDS, ...Person::CONTACT_FIELDS])
                    : null,
                'can_edit_identification' => $canEditCentral && $authority->identification,
                'can_edit_contacts' => $canEditCentral && $authority->contacts,
                'tags' => $derivedTags->execute($accountId, [$person->id], $user)[$person->id] ?? [],
            ],
            'links' => $links->map(fn (Employee $link) => [
                'id' => $link->id,
                'company_code' => (string) $link->getAttribute('company_code'),
                'company_name' => (string) $link->getAttribute('company_name'),
                'company_active' => (bool) $link->getAttribute('company_active'),
                'active' => $link->active,
                'work_email' => $link->work_email,
                'work_phone' => $link->work_phone,
                'work_whatsapp' => $link->work_whatsapp,
                'tag_ids' => $tagIds[$link->id] ?? [],
                'can_update' => $this->covers($updatable, $link->company_id),
                'can_change_status' => $link->active
                    ? $this->covers($deactivatable, $link->company_id)
                    : $this->covers($activatable, $link->company_id),
            ])->values()->all(),
            'linkable' => $this->options($linkable),
            'access' => $canSeeAccess ? $hasAccess->execute($accountId, $person->id) : null,
            'canSeeAccess' => $canSeeAccess,
        ]);
    }

    public function updatePerson(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        ShowEmployeePersonQuery $showPerson,
        UpdateEmployeePersonAction $action,
        int $personId,
    ): Response {
        $viewable = $permissions->nodesFor($request->user(), 'account.employees.view');
        abort_unless($viewable->reachesCompanies(), 403);

        $accountId = $account->requireId();
        abort_if($showPerson->execute($accountId, $viewable, $personId) === null, 404);

        $changes = CommercialPersonChangesData::fromInput($request->all(), allowIdentificationChange: true);

        $action->execute($changes, $personId, $account->requireAccount(404));
        ApiPage::toast('Dados da pessoa salvos.');

        return response()->noContent();
    }

    public function addLinks(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        ListNavigableCompaniesQuery $listNavigableCompanies,
        ShowEmployeePersonQuery $showPerson,
        CreateEmployeeAction $action,
        int $personId,
    ): Response {
        $user = $request->user();
        $accountId = $account->requireId();

        $viewable = $permissions->nodesFor($user, 'account.employees.view');
        abort_unless($viewable->reachesCompanies(), 403);
        abort_if($showPerson->execute($accountId, $viewable, $personId) === null, 404);

        $nodes = $permissions->nodesFor($user, 'account.employees.create');
        abort_unless($nodes->reachesCompanies(), 403);

        $allowed = $listNavigableCompanies->execute($accountId, $nodes);

        $data = CreateEmployeeData::fromInput([...$request->all(), 'person_id' => $personId], $allowed, $accountId);

        $action->execute($data, $account->requireAccount(404));
        ApiPage::toast('Vínculo cadastrado.');

        return response()->noContent();
    }

    public function updateLink(
        Request $request,
        AccountContext $account,
        EffectivePermissions $permissions,
        ShowEmployeeLinkQuery $showLink,
        UpdateEmployeeAction $action,
        int $personId,
        int $employeeId,
    ): Response {
        $user = $request->user();

        $viewable = $permissions->nodesFor($user, 'account.employees.view');
        abort_unless($viewable->reachesCompanies(), 403);

        $link = $showLink->execute($account->requireId(), $viewable, $personId, $employeeId);
        abort_if($link === null, 404);

        $data = UpdateEmployeeData::fromInput($request->all());

        $action->execute($data, $link);
        ApiPage::toast('Vínculo salvo.');

        return response()->noContent();
    }

    private function covers(AuthorizedNodes $nodes, int $companyId): bool
    {
        return $nodes->tenant || in_array($companyId, $nodes->companies, true);
    }

    /**
     * @param  list<NavigableCompany>  $companies
     * @return list<array{code: string, name: string}>
     */
    private function options(array $companies): array
    {
        return array_map(fn (NavigableCompany $company) => $company->toArray(), $companies);
    }

    private function documentLabel(?string $personType, ?string $document): string
    {
        $taxDocument = $personType === 'company' ? TaxDocument::cnpj($document) : TaxDocument::cpf($document);

        return $taxDocument?->formatted() ?? 'Não informado';
    }
}
