<?php

declare(strict_types=1);

namespace App\Domains\Etiquetas\Application\Queries\Tags;

use App\Domains\Etiquetas\Domain\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Etiquetas comuns de um módulo que um cadastro pode escolher: as ativas e as já escolhidas. */
final readonly class ListSelectableTagsQuery
{
    /**
     * @param  list<int>  $selected
     * @return Collection<int, Tag>
     */
    public function execute(int $accountId, string $module, array $selected = []): Collection
    {
        return Tag::where('account_id', $accountId)
            ->orderBy('name')
            ->where('module', $module)
            ->whereNull('system_key')
            ->where(fn (Builder $query) => $query->where('active', true)->orWhereIn('id', $selected))
            ->get(['id', 'name', 'color']);
    }
}
