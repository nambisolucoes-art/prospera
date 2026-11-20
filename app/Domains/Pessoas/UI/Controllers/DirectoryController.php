<?php

namespace App\Domains\Pessoas\UI\Controllers;

use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Etiquetas\Application\Actions\DeleteTagAction;
use App\Domains\Etiquetas\Application\Actions\SaveTagAction;
use App\Domains\Etiquetas\Application\Data\SaveTagData;
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
    public function people(Request $r, PeopleDirectoryQuery $q): JsonResponse|View
    {
        $customersOnly = $r->boolean('customers');
        if ($customersOnly) {
            Gate::authorize('account.customers.list');
        }
        $suppliersOnly = $r->boolean('suppliers');
        if ($suppliersOnly) {
            Gate::authorize('account.suppliers.list');
        }

        return ApiPage::respond(['customersOnly' => $customersOnly, 'suppliersOnly' => $suppliersOnly, 'people' => $q->people($r->user()->account_id, PageSize::fromInput($r->query('per_page')), mb_substr((string) $r->query('search', ''), 0, 255), $suppliersOnly, $customersOnly)->through(fn ($person) => ['id' => $person->id, 'name' => $person->name, 'email' => $person->email, 'tags' => $person->tags]), 'search' => $r->query('search', '')]);
    }

    public function previewPerson(Request $r, PeopleDirectoryQuery $q, int $personId): JsonResponse
    {
        return response()->json($this->personPayload($r, $q->person($r->user()->account_id, $personId)));
    }

    /** @return array<string,mixed> */
    private function personPayload(Request $r, Person $person, bool $forForm = false): array
    {
        return [...$person->only(['id', 'name', 'email', 'phone', 'person_type', 'document', 'trade_name', 'state_registration', 'municipal_registration', 'contact_name', 'whatsapp', 'website', 'address', 'additional_phones', 'additional_emails']), 'tags' => $person->tags, 'has_user' => $person->user !== null, 'has_supplier' => $person->supplier !== null,
            'has_customer' => $person->customer !== null,
            'customer' => $r->user()->can('account.customers.view') ? $person->customer : null,
            'supplier' => ($r->user()->can('account.suppliers.view')) ? $person->supplier : null,
            'user' => ($r->user()->can('account.users.view') || ($forForm && $r->user()->can('account.users.assign-role'))) ? $person->user : null];
    }

    public function personForm(Request $r, PeopleDirectoryQuery $q, ?int $personId = null): JsonResponse|View
    {
        $roles = $r->user()->can('account.users.assign-role') ? Role::where('account_id', $r->user()->account_id)->orderBy('name')->get(['id', 'name', 'system_key']) : collect();

        return ApiPage::respond(['person' => $personId ? $this->personPayload($r, $q->person($r->user()->account_id, $personId), true) : null, 'tags' => $q->tags($r->user()->account_id)->where(fn ($query) => $query->where('active', true)->orWhereHas('people', fn ($people) => $people->where('people.id', $personId)))->get(), 'roles' => $roles]);
    }

    public function savePerson(Request $r, PeopleDirectoryQuery $q, SavePersonAction $save, ?int $personId = null): RedirectResponse
    {
        $account = $r->user()->account()->firstOrFail();
        $person = $personId ? $q->person($account->id, $personId) : null;
        $data = SavePersonData::fromInput($r->all(), $account->id, $person?->user !== null, $person?->tags->pluck('id')->all() ?? [], $person);
        $saved = $save->execute($account, $person, $data);
        $message = 'Pessoa salva.';
        if ($data->access?->user->setup === PasswordSetup::Invite) {
            $message = app(SendUserInvitationAction::class)->execute($saved->user()->firstOrFail()) ? 'Pessoa salva. Convite enviado.' : 'Pessoa salva. O link pode ser solicitado em Esqueceu a senha.';
        }
        ApiPage::toast($message);

        return redirect($r->user()->can('account.people.list') ? '/conta/pessoas' : ($personId ? '/conta/pessoas/'.$personId.'/editar' : '/conta/pessoas/cadastrar'));
    }

    public function deleteTag(Request $r, DeleteTagAction $delete, int $tagId): RedirectResponse
    {
        $delete->execute($r->user()->account_id, $tagId);
        ApiPage::toast('Etiqueta excluída.');

        return redirect('/conta/etiquetas');
    }

    public function tags(Request $r, PeopleDirectoryQuery $q): JsonResponse|View
    {
        return ApiPage::respond($this->tagsPayload($r, $q));
    }

    /** @return array<string,mixed> */
    private function tagsPayload(Request $r, PeopleDirectoryQuery $q): array
    {
        $canList = $r->user()->can('account.tags.list');
        $tags = $q->tags($r->user()->account_id)->withCount('people')->when(! $canList, fn ($query) => $query->whereRaw('1 = 0'))->when($r->filled('module'), fn ($query) => $query->where('module', $r->query('module')))->paginate(PageSize::fromInput($r->query('per_page')))->withPath($canList ? '/conta/etiquetas' : $r->url())->withQueryString();

        return ['tags' => $tags, 'module' => $r->query('module', ''), 'canList' => $canList, 'formOpen' => false, 'tag' => null];
    }

    public function previewTag(Request $r, PeopleDirectoryQuery $q, int $tagId): JsonResponse
    {
        return response()->json($q->tags($r->user()->account_id)->findOrFail($tagId));
    }

    public function tagForm(Request $r, ?int $tagId = null): JsonResponse|View
    {
        return ApiPage::respond([...$this->tagsPayload($r, app(PeopleDirectoryQuery::class)), 'formOpen' => true, 'tag' => $tagId ? Tag::where('account_id', $r->user()->account_id)->findOrFail($tagId) : null]);
    }

    public function saveTag(Request $r, SaveTagAction $save, ?int $tagId = null): RedirectResponse
    {
        $id = $r->user()->account_id;
        $tag = $tagId ? Tag::where('account_id', $id)->findOrFail($tagId) : null;
        abort_if($tag?->system_key !== null, 403);
        $save->execute($id, $tag, SaveTagData::fromInput($r->all(), $id, $tagId));
        ApiPage::toast('Etiqueta salva.');

        return redirect($r->user()->can('account.tags.list') ? '/conta/etiquetas' : ($tagId ? '/conta/etiquetas/'.$tagId.'/editar' : '/conta/etiquetas/cadastrar'));
    }
}
