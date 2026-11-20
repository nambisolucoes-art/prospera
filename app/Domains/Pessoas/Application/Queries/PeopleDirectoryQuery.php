<?php

namespace App\Domains\Pessoas\Application\Queries;

use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Pessoas\Domain\Models\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class PeopleDirectoryQuery
{
    /** @return LengthAwarePaginator<int, Person> */
    public function people(int $accountId, int $size, string $search, bool $suppliersOnly = false, bool $customersOnly = false): LengthAwarePaginator
    {
        return Person::where('account_id', $accountId)->when($customersOnly, fn ($query) => $query->whereHas('customer', fn ($customer) => $customer->where('active', true)))->when($suppliersOnly, fn ($query) => $query->whereHas('supplier', fn ($supplier) => $supplier->where('active', true)))->with(['customer', 'supplier', 'tags', 'user:id,person_id,is_account_admin,role_id', 'user.role:id,name'])->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))->orderBy('name')->orderBy('id')->paginate($size)->withQueryString();
    }

    /** @return Builder<Tag> */
    public function tags(int $accountId): Builder
    {
        return Tag::where('account_id', $accountId)->orderBy('name');
    }

    public function person(int $accountId, int $id): Person
    {
        return Person::where('account_id', $accountId)->with(['customer', 'supplier', 'tags', 'user:id,person_id,is_account_admin,role_id', 'user.role:id,name'])->findOrFail($id);
    }
}
