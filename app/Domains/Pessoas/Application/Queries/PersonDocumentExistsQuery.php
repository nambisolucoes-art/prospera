<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Queries;

use App\Domains\Pessoas\Domain\Models\Person;

final class PersonDocumentExistsQuery
{
    public function execute(int $accountId, string $document, ?int $ignoreId = null): bool
    {
        return Person::where('account_id', $accountId)
            ->where('document', $document)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
