<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Queries\Context;

use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use App\Domains\Organizacao\Domain\ValueObjects\NavigableCompany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Resolve códigos de URL e enumera os contextos navegáveis, sempre dentro da conta informada.
 */
final readonly class ListNavigableCompaniesQuery
{
    /**
     * Empresas ativas cobertas pelo descritor da permissão, para os módulos de empresa.
     *
     * @return list<NavigableCompany>
     */
    public function execute(int $accountId, AuthorizedNodes $nodes): array
    {
        if ($nodes->tenant === false && $nodes->companies === []) {
            return [];
        }

        $rows = DB::table('companies')
            ->where('account_id', $accountId)
            ->where('active', true)
            ->when(! $nodes->tenant, fn (Builder $query) => $query->whereIn('id', $nodes->companies))
            ->orderByRaw('coalesce(trade_name, name)')
            ->orderBy('id')
            ->get(['id', 'code', 'name', 'trade_name', 'active']);

        return array_values($rows->map(fn (stdClass $row) => new NavigableCompany(
            id: (int) $row->id,
            code: (string) $row->code,
            name: (string) ($row->trade_name ?: $row->name),
            active: (bool) $row->active,
        ))->all());
    }
}
