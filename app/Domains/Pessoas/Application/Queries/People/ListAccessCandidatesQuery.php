<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Queries\People;

use App\Domains\Pessoas\Domain\Models\Person;
use Illuminate\Support\Collection;

final readonly class ListAccessCandidatesQuery
{
    public function __construct(
        private ListPeopleWithoutAccessQuery $listPeopleWithoutAccess,
    ) {}

    /** @return Collection<int, Person> */
    public function execute(int $accountId, string $search): Collection
    {
        $pattern = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

        return $this->listPeopleWithoutAccess->execute($accountId)
            ->where(fn ($match) => $match->whereLike('name', $pattern)->orWhereLike('email', $pattern))
            ->orderBy('name')->orderBy('id')->limit(25)->get(['id', 'name', 'email']);
    }
}
