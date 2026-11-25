<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Queries\People;

use App\Domains\Pessoas\Domain\Models\Person;
use Illuminate\Database\Eloquent\Builder;

final readonly class ListPeopleWithoutAccessQuery
{
    /** @return Builder<Person> */
    public function execute(int $accountId): Builder
    {
        return Person::where('account_id', $accountId)->whereDoesntHave('user');
    }
}
