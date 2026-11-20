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
use App\Domains\Pessoas\Application\Actions\SavePersonAction;
use App\Domains\Pessoas\Application\Data\SavePersonData;
use App\Domains\Pessoas\Application\Queries\PeopleDirectoryQuery;
use App\Domains\Pessoas\Application\Queries\PersonAccessQuery;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Usuarios\Application\Actions\SendUserInvitationAction;
use App\Domains\Usuarios\Domain\Enums\PasswordSetup;
use App\Domains\Usuarios\Domain\Exceptions\IdentityCreationUnavailable;
use App\Support\ApiPage;
use App\Support\PageSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class DirectoryController
{
    public function people(Request $request, PeopleDirectoryQuery $directory, EffectivePermissions $permissions): JsonResponse|View
    {
        $operator = $request->user();
        abort_if($permissions->nodesFor($operator, 'account.people.list')->isEmpty(), 403);
        $canSeeUsers = ! $permissions->nodesFor($operator, 'account.users.list')->isEmpty();
        $canSeeCustomers = $operator->can('account.customers.view');
        $canSeeSuppliers = $operator->can('account.suppliers.view');

        $customersOnly = $request->boolean('customers');

        if ($customersOnly) {
            Gate::authorize('account.customers.list');
        }

        $suppliersOnly = $request->boolean('suppliers');

        if ($suppliersOnly) {
            Gate::authorize('account.suppliers.list');
        }

        $search = mb_substr((string) $request->query('search', ''), 0, 255);

        $people = $directory
            ->people(
                app(AccountContext::class)->requireId(),
                PageSize::fromInput($request->query('per_page')),
                $search,
                $suppliersOnly,
                $customersOnly,
            )
            ->through(fn ($person) => [
                'id' => $person->id,
                'name' => $person->name,
                'email' => $person->email,
                'tags' => $person->tags->filter(fn (Tag $tag) => match ($tag->system_key) {
                    'user' => $canSeeUsers,
                    'customer' => $canSeeCustomers,
                    'supplier' => $canSeeSuppliers,
                    default => true,
                })->values(),
            ]);

        return ApiPage::respond([
            'customersOnly' => $customersOnly,
            'suppliersOnly' => $suppliersOnly,
            'people' => $people,
            'search' => $request->query('search', ''),
        ]);
    }

    public function previewPerson(Request $request, PeopleDirectoryQuery $directory, EffectivePermissions $permissions, int $personId): JsonResponse
    {
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
            'tags' => $person->tags->filter(fn (Tag $tag) => match ($tag->system_key) {
                'user' => $showsUser || ! $permissions->nodesFor($operator, 'account.users.list')->isEmpty(),
                'customer' => $operator->can('account.customers.view') || $operator->can('account.customers.create')
                    || $operator->can('account.customers.activate') || $operator->can('account.customers.deactivate'),
                'supplier' => $operator->can('account.suppliers.view') || $operator->can('account.suppliers.create')
                    || $operator->can('account.suppliers.activate') || $operator->can('account.suppliers.deactivate'),
                default => true,
            })->values(),
            'has_user' => ($showsUser && $person->user !== null) || $member !== null,
            'has_supplier' => $person->supplier !== null && $operator->can('account.suppliers.view'),
            'has_customer' => $person->customer !== null && $operator->can('account.customers.view'),
            'membership' => $member === null ? null : [
                'id' => $member->id,
                'email' => $view->isEmpty() ? null : $member->identity->email,
                'status' => $member->status,
                'assignments' => $memberships->assignmentRows($member, $nodes),
                'can_manage' => $permissions->nodesFor($operator, 'account.users.assign-role')->tenant
                    || $memberships->visible($person->account_id, $permissions->nodesFor($operator, 'account.users.assign-role'))
                        ->whereKey($member->id)->exists(),
            ],
            'customer' => $operator->can('account.customers.view') ? $person->customer : null,
            'supplier' => $operator->can('account.suppliers.view') ? $person->supplier : null,
        ];
    }

    public function personForm(
        Request $request,
        PeopleDirectoryQuery $directory,
        PersonAccessQuery $access,
        EffectivePermissions $permissions,
        ?int $personId = null,
    ): JsonResponse|View {
        $accountId = app(AccountContext::class)->requireId();

        $roles = $request->user()->can('account.users.assign-role')
            ? Role::where('account_id', $accountId)->where('availability', 'tenant')
                ->whereDoesntHave('permissions', fn ($permission) => $permission
                    ->whereNotIn('permissions.slug', $request->user()->permissionSlugs()))
                ->orderBy('name')->get(['id', 'name', 'system_key'])
            : collect();

        $record = $personId ? $directory->person($accountId, $personId) : null;

        if ($record === null) {
            abort_if($permissions->nodesFor($request->user(), 'account.people.create')->isEmpty(), 403);
        } else {
            abort_if($permissions->nodesFor($request->user(), 'account.people.update')->isEmpty(), 403);
        }

        $person = $record === null ? null : $this->personPayload($request, $record, forForm: true);

        // Etiquetas inativas continuam disponíveis para a pessoa que já as possui.
        $tags = $directory->tags($accountId)
            ->where(fn ($query) => $query
                ->where('active', true)
                ->orWhereHas('people', fn ($people) => $people->where('people.id', $personId)))
            ->get();

        return ApiPage::respond([
            'person' => $person,
            'tags' => $tags->filter(fn (Tag $tag) => match ($tag->system_key) {
                'user' => $request->user()->can('account.users.create') || $request->user()->can('account.users.view')
                    || $request->user()->can('account.users.assign-role'),
                'customer' => $request->user()->can('account.customers.create') || $request->user()->can('account.customers.view')
                    || $request->user()->can('account.customers.activate') || $request->user()->can('account.customers.deactivate'),
                'supplier' => $request->user()->can('account.suppliers.create') || $request->user()->can('account.suppliers.view')
                    || $request->user()->can('account.suppliers.activate') || $request->user()->can('account.suppliers.deactivate'),
                default => true,
            })->values(),
            'roles' => $roles,
            'canEditCentralData' => $record === null || $access->canUpdate($request->user(), $record),
        ]);
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
        $protected = collect([
            'user' => $operator->can('account.users.create') || $operator->can('account.users.view')
                || $operator->can('account.users.assign-role'),
            'customer' => $operator->can('account.customers.create') || $operator->can('account.customers.view')
                || $operator->can('account.customers.activate') || $operator->can('account.customers.deactivate'),
            'supplier' => $operator->can('account.suppliers.create') || $operator->can('account.suppliers.view')
                || $operator->can('account.suppliers.activate') || $operator->can('account.suppliers.deactivate'),
        ])->filter(fn (bool $visible) => ! $visible)->keys();
        $existingProtected = $person?->tags->whereIn('system_key', $protected)->pluck('id')->all() ?? [];

        if (is_array($input['tag_ids'] ?? []) && $existingProtected !== []) {
            $input['tag_ids'] = array_values(array_unique([...($input['tag_ids'] ?? []), ...$existingProtected]));
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
