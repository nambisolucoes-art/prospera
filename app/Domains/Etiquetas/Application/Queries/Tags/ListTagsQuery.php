<?php

declare(strict_types=1);

namespace App\Domains\Etiquetas\Application\Queries\Tags;

use App\Domains\Etiquetas\Application\Data\Tags\ListTagsData;
use App\Domains\Etiquetas\Domain\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/** Lista as etiquetas da conta. Sem a permissão de listar, a consulta não devolve linhas. */
final readonly class ListTagsQuery
{
    /** @return LengthAwarePaginator<int, Tag> */
    public function execute(int $accountId, ListTagsData $filters, bool $canList, string $path): LengthAwarePaginator
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters->search);
        $needle = '%'.$escaped.'%';

        return Tag::where('account_id', $accountId)
            ->orderBy('name')
            ->select('tags.*')
            ->selectRaw(
                '(exists (select 1 from customers_tags where tag_id = tags.id) '
                .'or exists (select 1 from suppliers_tags where tag_id = tags.id) '
                .'or exists (select 1 from employees_tags where tag_id = tags.id)) as commercial_assigned',
            )
            ->withCount('people')
            ->when(! $canList, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($filters->search !== '', fn (Builder $query) => $query->whereLike('name', $needle))
            ->when($filters->module !== '', fn (Builder $query) => $query->where('module', $filters->module))
            ->when($filters->active !== null, fn (Builder $query) => $query->where('active', $filters->active))
            ->paginate($filters->pageSize)
            ->withPath($path)
            ->withQueryString();
    }
}
