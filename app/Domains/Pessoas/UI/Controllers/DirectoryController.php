<?php

namespace App\Domains\Pessoas\UI\Controllers;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Application\Queries\MembershipDirectoryQuery;
use App\Domains\Acessos\Domain\DTOs\AuthorizedNodes;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Etiquetas\Application\Actions\DeleteTagAction;
use App\Domains\Etiquetas\Application\Actions\SaveTagAction;
use App\Domains\Etiquetas\Application\Data\SaveTagData;
use App\Domains\Etiquetas\Application\Data\TagFiltersData;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Organizacao\Application\Queries\CompanyDirectoryQuery;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Pessoas\Application\Actions\SavePersonAction;
use App\Domains\Pessoas\Application\Data\SavePersonData;
use App\Domains\Pessoas\Application\Queries\PeopleDirectoryQuery;
use App\Domains\Pessoas\Application\Queries\PersonAccessQuery;
use App\Domains\Pessoas\Application\Queries\PersonCommercialRelationsQuery;
use App\Domains\Pessoas\Domain\Enums\CommercialRole;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Usuarios\Application\Actions\SendUserInvitationAction;
use App\Domains\Usuarios\Domain\Enums\PasswordSetup;
use App\Domains\Usuarios\Domain\Exceptions\IdentityCreationUnavailable;
use App\Models\User;
use App\Support\ApiPage;
use App\Support\PageSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final class DirectoryController
{
    public function people(
        Request $request,
        PeopleDirectoryQuery $directory,
        CompanyDirectoryQuery $companies,
        EffectivePermissions $permissions,
    ): JsonResponse|View {
        $operator = $request->user();
        abort_if($permissions->nodesFor($operator, 'account.people.list')->isEmpty(), 403);
        $accountId = app(AccountContext::class)->requireId();
        $canSeeUsers = ! $permissions->nodesFor($operator, 'account.users.list')->isEmpty();

        // Etiquetas comerciais derivadas: só nas empresas em que o leitor visualiza a relação (seção 10.2).
        $customerView = CommercialRole::Customer->companies($permissions, $operator, 'view');
        $supplierView = CommercialRole::Supplier->companies($permissions, $operator, 'view');

        $customersOnly = $request->boolean('customers');
        $suppliersOnly = $request->boolean('suppliers');
        $filteredRole = null;

        if ($customersOnly) {
            $filteredRole = CommercialRole::Customer;
        } elseif ($suppliersOnly) {
            $filteredRole = CommercialRole::Supplier;
        }

        $customerList = $this->commercialFilterNodes(
            CommercialRole::Customer->companies($permissions, $operator, 'list'),
            $customerView,
        );
        $supplierList = $this->commercialFilterNodes(
            CommercialRole::Supplier->companies($permissions, $operator, 'list'),
            $supplierView,
        );
        $filterNodes = match ($filteredRole) {
            CommercialRole::Customer => $customerList,
            CommercialRole::Supplier => $supplierList,
            null => new AuthorizedNodes,
        };

        abort_if($filteredRole !== null && $filterNodes->isEmpty(), 403);

        // O filtro de empresa só restringe dentro das empresas autorizadas; fora delas responde 404.
        $companyId = $request->integer('empresa') ?: null;

        if ($companyId !== null) {
            abort_if($filteredRole === null, 404);
            abort_unless($filterNodes->tenant || in_array($companyId, $filterNodes->companies, true), 404);
            abort_if($companies->findMany($accountId, [$companyId])->isEmpty(), 404);
            $filterNodes = new AuthorizedNodes(companies: [$companyId]);
        }

        $search = mb_substr((string) $request->query('search', ''), 0, 255);
        $commercialTags = $directory->commercialTags($accountId);

        $people = $directory
            ->people(
                $accountId,
                PageSize::fromInput($request->query('per_page')),
                $search,
                $customerView,
                $supplierView,
                $filteredRole,
                $filterNodes,
            )
            ->through(fn (Person $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'email' => $person->email,
                'tags' => $this->visibleTags($person, $canSeeUsers, $commercialTags),
            ]);

        $filterCompanies = $companies->covered($accountId, $customerList->with($supplierList));

        return ApiPage::respond([
            'customersOnly' => $customersOnly,
            'suppliersOnly' => $suppliersOnly && ! $customersOnly,
            'company' => $companyId,
            'canFilterCustomers' => ! $customerList->isEmpty(),
            'canFilterSuppliers' => ! $supplierList->isEmpty(),
            // Com uma empresa só, o filtro de empresa fica oculto.
            'companies' => $filterCompanies->count() > 1
                ? $filterCompanies->map(fn (Company $company) => [
                    'id' => $company->id,
                    'name' => $company->trade_name ?: $company->name,
                    'customers' => $customerList->tenant || in_array($company->id, $customerList->companies, true),
                    'suppliers' => $supplierList->tenant || in_array($company->id, $supplierList->companies, true),
                ])->values()
                : [],
            'people' => $people,
            'search' => $request->query('search', ''),
        ]);
    }

    private function commercialFilterNodes(AuthorizedNodes $list, AuthorizedNodes $view): AuthorizedNodes
    {
        if ($list->tenant) {
            return $view;
        }

        if ($view->tenant) {
            return $list;
        }

        return new AuthorizedNodes(companies: array_values(array_intersect($list->companies, $view->companies)));
    }

    /**
     * Etiquetas convencionais da pessoa, Usuário conforme a permissão e Cliente e Fornecedor derivados.
     *
     * @param  Collection<string, Tag>  $commercialTags
     * @return list<Tag>
     */
    private function visibleTags(Person $person, bool $canSeeUsers, Collection $commercialTags): array
    {
        $tags = $person->tags
            ->filter(fn (Tag $tag) => match ($tag->system_key) {
                null => true,
                'user' => $canSeeUsers,
                default => false,
            })
            ->values()
            ->all();

        // Atributos calculados em lote na listagem ou pela leitura das relações na ficha.
        $customerTag = $commercialTags->get('customer');
        $supplierTag = $commercialTags->get('supplier');

        if ($person->getAttribute('is_customer') && $customerTag !== null) {
            $tags[] = $customerTag;
        }

        if ($person->getAttribute('is_supplier') && $supplierTag !== null) {
            $tags[] = $supplierTag;
        }

        return array_values($tags);
    }

    public function previewPerson(
        Request $request,
        PeopleDirectoryQuery $directory,
        EffectivePermissions $permissions,
        int $personId,
    ): JsonResponse {
        abort_if($permissions->nodesFor($request->user(), 'account.people.view')->isEmpty(), 403);
        $person = $directory->person(app(AccountContext::class)->requireId(), $personId);

        return response()->json($this->personPayload($request, $person));
    }

    /** @return array<string,mixed> */
    private function personPayload(Request $request, Person $person, bool $forForm = false): array
    {
        $operator = $request->user();
        $mayEditLink = $forForm && $operator->can('account.users.update');
        $showsUser = $operator->can('account.users.view')
            || $mayEditLink
            || ($forForm && $operator->can('account.users.assign-role'));

        $memberships = app(MembershipDirectoryQuery::class);
        $permissions = app(EffectivePermissions::class);
        $view = $permissions->nodesFor($operator, 'account.users.view');
        $nodes = $forForm && $view->isEmpty()
            ? ($mayEditLink ? new AuthorizedNodes(true)
                : $permissions->nodesFor($operator, 'account.users.assign-role'))
            : $view;
        $member = $nodes->isEmpty() ? null : $memberships->visible($person->account_id, $nodes)
            ->where('person_id', $person->id)
            ->with('identity:id,email')
            ->first();

        $customerView = CommercialRole::Customer->companies($permissions, $operator, 'view');
        $supplierView = CommercialRole::Supplier->companies($permissions, $operator, 'view');
        $relations = app(PersonCommercialRelationsQuery::class)
            ->visible($person->account_id, $person->id, $customerView, $supplierView);

        $person->setAttribute('is_customer', $relations->contains(
            fn ($relation) => $relation->role === 'customer' && (bool) $relation->active,
        ));
        $person->setAttribute('is_supplier', $relations->contains(
            fn ($relation) => $relation->role === 'supplier' && (bool) $relation->active,
        ));

        $canSeeUsers = $showsUser || ! $permissions->nodesFor($operator, 'account.users.list')->isEmpty();
        $commercialTags = app(PeopleDirectoryQuery::class)->commercialTags($person->account_id);

        return [
            ...$person->only([
                'id',
                'name',
                'email',
                'phone',
                'person_type',
                'document',
                'trade_name',
                'state_registration',
                'municipal_registration',
                'contact_name',
                'whatsapp',
                'website',
                'address',
                'additional_phones',
                'additional_emails',
            ]),
            'tags' => $this->visibleTags($person, $canSeeUsers, $commercialTags),
            'has_user' => ($showsUser && $person->user !== null) || $member !== null,
            'membership' => $member === null ? null : [
                'id' => $member->id,
                'email' => $view->isEmpty() ? null : $member->identity->email,
                'status' => $member->status,
                'assignments' => $memberships->assignmentRows($member, $nodes),
                'can_manage' => $permissions->nodesFor($operator, 'account.users.assign-role')->tenant
                    || $memberships->visible($person->account_id, $permissions->nodesFor($operator, 'account.users.assign-role'))
                        ->whereKey($member->id)->exists(),
            ],
            'relations' => $this->relationRows($relations, $customerView, $supplierView),
        ];
    }

    /**
     * Uma linha por empresa com relação visível; o papel que o leitor não pode ver aparece como restrito.
     *
     * @param  Collection<int, \stdClass>  $relations
     * @return list<array<string,mixed>>
     */
    private function relationRows(Collection $relations, AuthorizedNodes $customers, AuthorizedNodes $suppliers): array
    {
        $rows = [];

        foreach ($relations->sortBy('company_name') as $relation) {
            $companyId = (int) $relation->company_id;
            $rows[$companyId] ??= [
                'company_id' => $companyId,
                'company_name' => $relation->company_name,
                'company_active' => (bool) $relation->company_active,
                'customer' => $customers->tenant || in_array($companyId, $customers->companies, true)
                    ? 'none' : 'restricted',
                'supplier' => $suppliers->tenant || in_array($companyId, $suppliers->companies, true)
                    ? 'none' : 'restricted',
            ];
            $rows[$companyId][$relation->role] = (bool) $relation->active ? 'active' : 'inactive';
        }

        return array_values($rows);
    }

    public function personForm(
        Request $request,
        PeopleDirectoryQuery $directory,
        PersonAccessQuery $access,
        CompanyDirectoryQuery $companies,
        EffectivePermissions $permissions,
        ?int $personId = null,
    ): JsonResponse|View {
        $accountId = app(AccountContext::class)->requireId();
        $operator = $request->user();

        $roles = $operator->can('account.users.assign-role')
            ? Role::where('account_id', $accountId)->where('availability', 'tenant')
                ->whereDoesntHave('permissions', fn ($permission) => $permission
                    ->whereNotIn('permissions.slug', $operator->permissionSlugs()))
                ->orderBy('name')->get(['id', 'name', 'system_key'])
            : collect();

        $record = $personId ? $directory->person($accountId, $personId) : null;

        if ($record === null) {
            abort_if($permissions->nodesFor($operator, 'account.people.create')->isEmpty(), 403);
        } else {
            abort_if($permissions->nodesFor($operator, 'account.people.update')->isEmpty(), 403);
        }

        $person = $record === null ? null : $this->personPayload($request, $record, forForm: true);
        $authority = $record === null ? null : $access->authority($operator, $record);

        // Etiquetas inativas continuam disponíveis para a pessoa que já as possui.
        $tags = $directory->tags($accountId)
            ->where('module', 'people')
            ->where(fn ($query) => $query
                ->where('active', true)
                ->orWhereHas('people', fn ($people) => $people->where('people.id', $personId)))
            ->get();

        $canSelectUser = $operator->can('account.users.create')
            || $operator->can('account.users.view')
            || $operator->can('account.users.assign-role');

        $selectableTags = $tags->filter(fn (Tag $tag) => match ($tag->system_key) {
            null => true,
            'user' => $canSelectUser,
            default => false,
        })->values();

        if ($person !== null) {
            $selectableIds = $selectableTags->modelKeys();
            $person['tags'] = array_values(array_filter(
                $person['tags'],
                fn (Tag $tag) => in_array($tag->id, $selectableIds, true),
            ));
        }

        return ApiPage::respond([
            'person' => $person,
            'tags' => $selectableTags,
            'roles' => $roles,
            'canEditIdentification' => $authority === null || $authority->identification,
            'canEditContacts' => $authority === null || $authority->contacts,
            'commercial' => $this->commercialOptions($operator, $permissions, $companies, $accountId, $person),
        ]);
    }

    /**
     * Empresas em que o operador tem alguma ação comercial, com a situação visível e as ações permitidas.
     *
     * @param  array<string,mixed>|null  $person
     * @return array<string,mixed>
     */
    private function commercialOptions(
        User $operator,
        EffectivePermissions $permissions,
        CompanyDirectoryQuery $companies,
        int $accountId,
        ?array $person,
    ): array {
        $scope = new AuthorizedNodes;

        foreach (CommercialRole::cases() as $role) {
            foreach (['view', 'create', 'activate', 'deactivate'] as $action) {
                $scope = $scope->with($role->companies($permissions, $operator, $action));
            }
        }

        $states = [];

        foreach ($person['relations'] ?? [] as $relation) {
            $states[$relation['company_id']] = $relation;
        }

        $options = $scope->isEmpty() ? collect() : $companies->covered($accountId, $scope);

        return [
            'single_company' => $companies->hasSingleCompany($accountId),
            'companies' => $options->map(function (Company $company) use ($operator, $permissions, $states) {
                $row = [
                    'id' => $company->id,
                    'name' => $company->trade_name ?: $company->name,
                    'active' => $company->active,
                ];

                foreach (CommercialRole::cases() as $role) {
                    $visible = $role->allows($permissions, $operator, 'view', $company->id);

                    $row[$role->value] = [
                        'status' => $visible ? ($states[$company->id][$role->value] ?? 'none') : null,
                        'can_create' => $role->allows($permissions, $operator, 'create', $company->id),
                        'can_activate' => $role->allows($permissions, $operator, 'activate', $company->id),
                        'can_deactivate' => $role->allows($permissions, $operator, 'deactivate', $company->id),
                    ];
                }

                return $row;
            })->values(),
        ];
    }

    public function savePerson(
        Request $request,
        PeopleDirectoryQuery $directory,
        SavePersonAction $save,
        ?int $personId = null,
    ): RedirectResponse {
        $account = app(AccountContext::class)->requireAccount(404);
        $person = $personId ? $directory->person($account->id, $personId) : null;

        $input = $request->all();
        $operator = $request->user();
        $seesUserTag = $operator->can('account.users.create')
            || $operator->can('account.users.view')
            || $operator->can('account.users.assign-role');

        // A etiqueta Usuário oculta para o operador é preservada; Cliente e Fornecedor vêm das relações.
        $hiddenUserTag = $seesUserTag ? [] : ($person?->tags->where('system_key', 'user')->pluck('id')->all() ?? []);

        if (is_array($input['tag_ids'] ?? []) && $hiddenUserTag !== []) {
            $input['tag_ids'] = array_values(array_unique([...($input['tag_ids'] ?? []), ...$hiddenUserTag]));
        }

        $data = SavePersonData::fromInput(
            $input,
            $account->id,
            $person?->user !== null,
            $person?->tags->pluck('id')->all() ?? [],
            $person,
        );

        try {
            $saved = $save->execute($account, $person, $data);

            if ($data->access?->user->setup === PasswordSetup::Invite) {
                app(SendUserInvitationAction::class)->execute($saved->user()->firstOrFail());
            }
        } catch (IdentityCreationUnavailable) {
            // A resposta não confirma se há uma identidade para o e-mail informado.
        }

        ApiPage::toast($data->access === null ? 'Pessoa salva.' : 'Solicitação de acesso registrada.');

        if ($request->user()->can('account.people.list')) {
            return redirect('/conta/pessoas');
        }

        return redirect($personId ? '/conta/pessoas/'.$personId.'/editar' : '/conta/pessoas/cadastrar');
    }

    public function deleteTag(DeleteTagAction $delete, int $tagId): RedirectResponse
    {
        $delete->execute(app(AccountContext::class)->requireId(), $tagId);
        ApiPage::toast('Etiqueta excluída.');

        return redirect('/conta/etiquetas');
    }

    public function tags(Request $request, PeopleDirectoryQuery $directory): JsonResponse|View
    {
        return ApiPage::respond($this->tagsPayload($request, $directory));
    }

    /** @return array<string,mixed> */
    private function tagsPayload(Request $request, PeopleDirectoryQuery $directory): array
    {
        $canList = $request->user()->can('account.tags.list');
        $filters = TagFiltersData::fromInput($request->query());

        $tags = $directory->tagList(
            app(AccountContext::class)->requireId(),
            $filters,
            $canList,
            $canList ? '/conta/etiquetas' : $request->url(),
        );

        return [
            'tags' => $tags,
            'filters' => $filters->toArray(),
            'canList' => $canList,
            'formOpen' => false,
            'tag' => null,
        ];
    }

    public function previewTag(PeopleDirectoryQuery $directory, int $tagId): JsonResponse
    {
        return response()->json($directory->tags(app(AccountContext::class)->requireId())->findOrFail($tagId));
    }

    public function tagForm(Request $request, ?int $tagId = null): JsonResponse|View
    {
        $tag = $tagId
            ? Tag::where('account_id', app(AccountContext::class)->requireId())->findOrFail($tagId)
            : null;

        return ApiPage::respond([
            ...$this->tagsPayload($request, app(PeopleDirectoryQuery::class)),
            'formOpen' => true,
            'tag' => $tag,
        ]);
    }

    public function saveTag(Request $request, SaveTagAction $save, ?int $tagId = null): RedirectResponse
    {
        $accountId = app(AccountContext::class)->requireId();
        $tag = $tagId ? Tag::where('account_id', $accountId)->findOrFail($tagId) : null;

        abort_if($tag?->system_key !== null, 403);

        $save->execute($accountId, $tag, SaveTagData::fromInput($request->all(), $accountId, $tagId));
        ApiPage::toast('Etiqueta salva.');

        if ($request->user()->can('account.tags.list')) {
            return redirect('/conta/etiquetas');
        }

        return redirect($tagId ? '/conta/etiquetas/'.$tagId.'/editar' : '/conta/etiquetas/cadastrar');
    }
}
