<?php

namespace App\Domains\Pessoas\Application\Queries;

use App\Domains\Etiquetas\Application\Data\TagFiltersData;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Pessoas\Domain\Models\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class PeopleDirectoryQuery
{
    private const PERSON_RELATIONS = [
        'tags',
        'user:users.id',
    ];

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
