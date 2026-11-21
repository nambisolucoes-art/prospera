<?php

namespace App\Domains\Pessoas\Application\Queries;

use App\Domains\Acessos\Domain\DTOs\AuthorizedNodes;
use App\Domains\Etiquetas\Application\Data\TagFiltersData;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Pessoas\Domain\Enums\CommercialRole;
use App\Domains\Pessoas\Domain\Models\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PeopleDirectoryQuery
{
    private const PERSON_RELATIONS = [
        'tags',
        'user:users.id',
    ];

    /**
     * Cliente e Fornecedor são derivados em lote para a página (is_customer e is_supplier), só com relações
     * ativas em empresas que o leitor pode visualizar. O filtro por papel também exige permissão de listar na empresa.
     *
     * @return LengthAwarePaginator<int, Person>
     */
    public function people(
        int $accountId,
        int $size,
        string $search,
        AuthorizedNodes $customerView,
        AuthorizedNodes $supplierView,
        ?CommercialRole $role = null,
        ?AuthorizedNodes $roleCompanies = null,
    ): LengthAwarePaginator {
        $query = Person::where('account_id', $accountId);

        if ($role === CommercialRole::Customer && $roleCompanies !== null) {
            $query->whereHas('customers', fn (Builder $relation) => $this->activeIn($relation, $roleCompanies));
        }

        if ($role === CommercialRole::Supplier && $roleCompanies !== null) {
            $query->whereHas('suppliers', fn (Builder $relation) => $this->activeIn($relation, $roleCompanies));
        }

        $people = $query->with(self::PERSON_RELATIONS)
            ->when($search !== '', fn ($query) => $query
                ->where(fn ($match) => $match
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($size)
            ->withQueryString();

        $rows = $people->getCollection();
        $personIds = array_values($rows->map(fn (Person $person): int => $person->id)->all());
        $roles = $this->activeRoles($accountId, $personIds, $customerView, $supplierView);

        foreach ($rows as $person) {
            $person->setAttribute('is_customer', isset($roles[$person->id]['customer']));
            $person->setAttribute('is_supplier', isset($roles[$person->id]['supplier']));
        }

        return $people;
    }

    /**
     * @param  list<int>  $personIds
     * @return array<int, array<string, true>>
     */
    private function activeRoles(
        int $accountId,
        array $personIds,
        AuthorizedNodes $customers,
        AuthorizedNodes $suppliers,
    ): array {
        if ($personIds === [] || ($customers->isEmpty() && $suppliers->isEmpty())) {
            return [];
        }

        $query = null;

        foreach ([[$customers, CommercialRole::Customer], [$suppliers, CommercialRole::Supplier]] as [$nodes, $role]) {
            if ($nodes->isEmpty()) {
                continue;
            }

            $branch = DB::table($role->table())
                ->where('account_id', $accountId)
                ->whereIn('person_id', $personIds)
                ->where('active', true)
                ->when(! $nodes->tenant, fn ($rows) => $rows->whereIn('company_id', $nodes->companies))
                ->select('person_id')
                ->selectRaw('? as role', [$role->value])
                ->distinct();

            $query = $query === null ? $branch : $query->unionAll($branch);
        }

        $result = [];

        foreach ($query->get() as $row) {
            $result[(int) $row->person_id][(string) $row->role] = true;
        }

        return $result;

    }

    /** @param Builder<covariant Model> $relation */
    private function activeIn(Builder $relation, AuthorizedNodes $companies): void
    {
        $relation->where('active', true);

        // Descritor comercial: conta inteira ou empresas, nunca unidades (alvo company).
        if (! $companies->tenant) {
            $relation->whereIn('company_id', $companies->companies);
        }
    }

    /**
     * Definições de nome e cor de Cliente e Fornecedor, exibidas como etiquetas derivadas.
     *
     * @return Collection<string, Tag>
     */
    public function commercialTags(int $accountId): Collection
    {
        return Tag::where('account_id', $accountId)
            ->where('module', 'people')
            ->whereIn('system_key', ['customer', 'supplier'])
            ->get()
            ->keyBy('system_key');
    }

    /** @return Builder<Tag> */
    public function tags(int $accountId): Builder
    {
        return Tag::where('account_id', $accountId)->orderBy('name');
    }

    /** @return LengthAwarePaginator<int, Tag> */
    public function tagList(int $accountId, TagFiltersData $filters, bool $canList, string $path): LengthAwarePaginator
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters->search);
        $needle = '%'.$escaped.'%';

        return $this->tags($accountId)
            ->select('tags.*')
            ->selectRaw(
                '(exists (select 1 from customers_tags where tag_id = tags.id) '
                .'or exists (select 1 from suppliers_tags where tag_id = tags.id)) as commercial_assigned',
            )
            ->withCount('people')
            ->when(! $canList, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($filters->search !== '', fn ($query) => $query->whereLike('name', $needle))
            ->when($filters->module !== '', fn ($query) => $query->where('module', $filters->module))
            ->when($filters->active !== null, fn ($query) => $query->where('active', $filters->active))
            ->paginate($filters->pageSize)
            ->withPath($path)
            ->withQueryString();
    }

    /** @return Builder<Person> */
    public function withoutAccess(int $accountId): Builder
    {
        return Person::where('account_id', $accountId)->whereDoesntHave('user');
    }

    /** @return Collection<int, Person> */
    public function candidatesForAccess(int $accountId, string $search): Collection
    {
        $pattern = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

        return $this->withoutAccess($accountId)
            ->where(fn ($match) => $match->whereLike('name', $pattern)->orWhereLike('email', $pattern))
            ->orderBy('name')->orderBy('id')->limit(25)->get(['id', 'name', 'email']);
    }

    public function person(int $accountId, int $id): Person
    {
        return Person::where('account_id', $accountId)
            ->with(self::PERSON_RELATIONS)
            ->findOrFail($id);
    }
}
