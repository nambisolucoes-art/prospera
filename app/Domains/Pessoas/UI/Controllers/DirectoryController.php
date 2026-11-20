<?php

namespace App\Domains\Pessoas\UI\Controllers;

use App\Domains\Acessos\Application\Context\AccountContext;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Etiquetas\Application\Actions\DeleteTagAction;
use App\Domains\Etiquetas\Application\Actions\SaveTagAction;
use App\Domains\Etiquetas\Application\Data\SaveTagData;
use App\Domains\Etiquetas\Application\Data\TagFiltersData;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Pessoas\Application\Actions\SavePersonAction;
use App\Domains\Pessoas\Application\Data\SavePersonData;
use App\Domains\Pessoas\Application\Queries\PeopleDirectoryQuery;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Usuarios\Application\Actions\SendUserInvitationAction;
use App\Domains\Usuarios\Domain\Enums\PasswordSetup;
use App\Support\ApiPage;
use App\Support\PageSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class DirectoryController
{
    public function people(Request $request, PeopleDirectoryQuery $directory): JsonResponse|View
    {
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
                'tags' => $person->tags,
            ]);

        return ApiPage::respond([
            'customersOnly' => $customersOnly,
            'suppliersOnly' => $suppliersOnly,
            'people' => $people,
            'search' => $request->query('search', ''),
        ]);
    }

    public function previewPerson(Request $request, PeopleDirectoryQuery $directory, int $personId): JsonResponse
    {
        $person = $directory->person(app(AccountContext::class)->requireId(), $personId);

        return response()->json($this->personPayload($request, $person));
    }

    /** @return array<string,mixed> */
    private function personPayload(Request $request, Person $person, bool $forForm = false): array
    {
        $operator = $request->user();
        $showsUser = $operator->can('account.users.view')
            || ($forForm && $operator->can('account.users.assign-role'));

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
            'tags' => $person->tags,
            'has_user' => $person->user !== null,
            'has_supplier' => $person->supplier !== null,
            'has_customer' => $person->customer !== null,
            'customer' => $operator->can('account.customers.view') ? $person->customer : null,
            'supplier' => $operator->can('account.suppliers.view') ? $person->supplier : null,
            'user' => $showsUser ? $person->user : null,
        ];
    }

    public function personForm(
        Request $request,
        PeopleDirectoryQuery $directory,
        ?int $personId = null,
    ): JsonResponse|View {
        $accountId = app(AccountContext::class)->requireId();

        $roles = $request->user()->can('account.users.assign-role')
            ? Role::where('account_id', $accountId)->orderBy('name')->get(['id', 'name', 'system_key'])
            : collect();

        $person = $personId
            ? $this->personPayload($request, $directory->person($accountId, $personId), forForm: true)
            : null;

        // Etiquetas inativas continuam disponíveis para a pessoa que já as possui.
        $tags = $directory->tags($accountId)
            ->where(fn ($query) => $query
                ->where('active', true)
                ->orWhereHas('people', fn ($people) => $people->where('people.id', $personId)))
            ->get();

        return ApiPage::respond([
            'person' => $person,
            'tags' => $tags,
            'roles' => $roles,
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

        $data = SavePersonData::fromInput(
            $request->all(),
            $account->id,
            $person?->user !== null,
            $person?->tags->pluck('id')->all() ?? [],
            $person,
        );

        $saved = $save->execute($account, $person, $data);

        $message = 'Pessoa salva.';

        if ($data->access?->user->setup === PasswordSetup::Invite) {
            $invited = app(SendUserInvitationAction::class)->execute($saved->user()->firstOrFail());
            $message = $invited
                ? 'Pessoa salva. Convite enviado.'
                : 'Pessoa salva. O link pode ser solicitado em Esqueceu a senha.';
        }

        ApiPage::toast($message);

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
