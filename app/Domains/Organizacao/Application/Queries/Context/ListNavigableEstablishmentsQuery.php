<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Queries\Context;

use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use App\Domains\Organizacao\Domain\ValueObjects\NavigableCompany;
use App\Domains\Organizacao\Domain\ValueObjects\NavigableEstablishment;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Resolve códigos de URL e enumera os contextos navegáveis, sempre dentro da conta informada.
 */
final readonly class ListNavigableEstablishmentsQuery
{
    private function available(int $accountId, AuthorizedNodes $nodes): Builder
    {
        $query = $this->establishments($accountId)
            ->where('e.active', true)
            ->where('c.active', true);

        if (! $nodes->tenant) {
            $query->where(fn (Builder $covered) => $covered
                ->whereIn('e.company_id', $nodes->companies)
                ->orWhereIn('e.id', $nodes->establishments));
        }

        return $query;
    }

    private function toEstablishment(stdClass $row): NavigableEstablishment
    {
        $classification = 'unregistered';

        if ((bool) $row->is_headquarters) {
            $classification = 'headquarters';
        } elseif ((bool) $row->registered) {
            $classification = 'branch';
        }

        $company = new NavigableCompany(
            id: (int) $row->company_id,
            code: (string) $row->company_code,
            name: (string) ($row->company_trade_name ?: $row->company_name),
            active: (bool) $row->company_active,
        );

        return new NavigableEstablishment(
            id: (int) $row->id,
            code: (string) $row->code,
            name: (string) $row->name,
            active: (bool) $row->active,
            primary: (bool) $row->is_primary,
            classification: $classification,
            company: $company,
        );
    }

    private function establishments(int $accountId): Builder
    {
        return DB::table('establishments as e')
            ->join('companies as c', function ($join): void {
                $join->on('c.id', '=', 'e.company_id')->on('c.account_id', '=', 'e.account_id');
            })
            ->where('e.account_id', $accountId)
            ->select([
                'e.id',
                'e.code',
                'e.name',
                'e.active',
                'e.is_primary',
                'e.is_headquarters',
                DB::raw('case when e.tax_document is null then 0 else 1 end as registered'),
                'c.id as company_id',
                'c.code as company_code',
                'c.name as company_name',
                'c.trade_name as company_trade_name',
                'c.active as company_active',
            ]);
    }

    /**
     * Unidades ativas de empresas ativas cobertas por alguma atribuição, numa única consulta.
     *
     * @return list<NavigableEstablishment>
     */
    public function execute(int $accountId, AuthorizedNodes $nodes): array
    {
        if ($nodes->isEmpty()) {
            return [];
        }

        $rows = $this->available($accountId, $nodes)
            ->orderByRaw('coalesce(c.trade_name, c.name)')
            ->orderBy('c.id')
            ->orderByDesc('e.is_primary')
            ->orderBy('e.name')
            ->orderBy('e.id')
            ->get();

        return array_values($rows->map(fn (stdClass $row) => $this->toEstablishment($row))->all());
    }
}
