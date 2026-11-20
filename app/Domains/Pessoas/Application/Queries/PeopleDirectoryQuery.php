<?php

namespace App\Domains\Pessoas\Application\Queries;

use App\Domains\Etiquetas\Application\Data\TagFiltersData;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Pessoas\Domain\Models\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class PeopleDirectoryQuery
{
    private const PERSON_RELATIONS = [
        'customer',
        'supplier',
        'tags',
        'user:users.id',
    ];

    /** @return LengthAwarePaginator<int, Person> */
    public function people(
        int $accountId,
        int $size,
        string $search,
        bool $suppliersOnly = false,
        bool $customersOnly = false,
    ): LengthAwarePaginator {
        return Person::where('account_id', $accountId)
            ->when($customersOnly, fn ($query) => $query
                ->whereHas('customer', fn ($customer) => $customer->where('active', true)))
            ->when($suppliersOnly, fn ($query) => $query
                ->whereHas('supplier', fn ($supplier) => $supplier->where('active', true)))
            ->with(self::PERSON_RELATIONS)
            ->when($search !== '', fn ($query) => $query
                ->where(fn ($match) => $match
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($size)
            ->withQueryString();
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
            ->withCount('people')
            ->when(! $canList, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($filters->search !== '', fn ($query) => $query->whereLike('name', $needle))
            ->when($filters->module !== '', fn ($query) => $query->where('module', $filters->module))
            ->when($filters->active !== null, fn ($query) => $query->where('active', $filters->active))
            ->paginate($filters->pageSize)
            ->withPath($path)
            ->withQueryString();
    }

    public function person(int $accountId, int $id): Person
    {
        return Person::where('account_id', $accountId)
            ->with(self::PERSON_RELATIONS)
            ->findOrFail($id);
    }
}
