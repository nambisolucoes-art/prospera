<?php

declare(strict_types=1);

namespace App\Domains\Etiquetas\Domain\Concerns;

use App\Domains\Etiquetas\Domain\Models\Tag;

/** Nomeia as etiquetas de `tag_ids` no histórico, sempre dentro da conta do registro auditado. */
trait NamesAuditedTags
{
    /**
     * @param  list<int>  $ids
     * @return list<string>|null
     */
    public function auditRelationNames(string $field, array $ids): ?array
    {
        if ($field !== 'tag_ids') {
            return null;
        }

        $names = Tag::where('account_id', $this->auditAccountId())
            ->whereIn('id', $ids)
            ->pluck('name', 'id');

        return array_map(fn (int $id): string => $names[$id] ?? 'Etiqueta #'.$id, $ids);
    }
}
