<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Queries;

use App\Domains\Pessoas\Domain\Models\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class PeopleDirectoryQuery
{
    private const PERSON_RELATIONS = [
        'tags',
        'user:users.id',
    ];

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
