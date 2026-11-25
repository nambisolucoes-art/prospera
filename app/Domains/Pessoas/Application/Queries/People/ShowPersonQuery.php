<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Queries\People;

use App\Domains\Pessoas\Domain\Models\Person;

final readonly class ShowPersonQuery
{
    private const PERSON_RELATIONS = [
        'tags',
        'user:users.id',
    ];

    public function execute(int $accountId, int $id): Person
    {
        return Person::where('account_id', $accountId)
            ->with(self::PERSON_RELATIONS)
            ->findOrFail($id);
    }
}
