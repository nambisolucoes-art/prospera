<?php

declare(strict_types=1);

namespace App\Domains\Etiquetas\Application\Queries\Tags;

use App\Domains\Etiquetas\Domain\Models\Tag;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/** Lê uma etiqueta da conta. Outra conta responde como inexistente. */
final readonly class ShowTagQuery
{
    /** @throws ModelNotFoundException<Tag> */
    public function execute(int $accountId, int $id): Tag
    {
        return Tag::where('account_id', $accountId)->findOrFail($id);
    }
}
