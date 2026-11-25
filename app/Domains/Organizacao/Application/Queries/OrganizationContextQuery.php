<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Queries;

use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use App\Domains\Organizacao\Domain\ValueObjects\NavigableCompany;
use App\Domains\Organizacao\Domain\ValueObjects\NavigableEstablishment;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Resolve códigos de URL e enumera os contextos navegáveis, sempre dentro da conta informada.
 */
final class OrganizationContextQuery
{
    public function company(int $accountId, string $code): ?NavigableCompany
    {
        $row = DB::table('companies')
            ->where('account_id', $accountId)
            ->where('code', $code)
            ->first(['id', 'code', 'name', 'trade_name', 'active']);

        if ($row === null) {
            return null;
        }

        return new NavigableCompany(
            id: (int) $row->id,
            code: (string) $row->code,
            name: (string) ($row->trade_name ?: $row->name),
            active: (bool) $row->active,
        );
    }

    // Uma consulta resolve a unidade e a empresa dela; a FK composta garante o mesmo tenant.
    public function establishment(int $accountId, string $code): ?NavigableEstablishment
    {
        $row = $this->establishments($accountId)
            ->where('e.code', $code)
            ->first();

        return $row === null ? null : $this->toEstablishment($row);
    }

    /**
     * Unidades ativas de empresas ativas cobertas por alguma atribuição, numa única consulta.
     *
     * @return list<NavigableEstablishment>
     */
    public function navigable(int $accountId, AuthorizedNodes $nodes): array
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

    /**
     * Empresas ativas cobertas pelo descritor da permissão, para os módulos de empresa.
     *
     * @return list<NavigableCompany>
     */
    public function companies(int $accountId, AuthorizedNodes $nodes): array
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

    /**
     * @param  list<string>  $codes
     * @return list<int>
     */
    public function authorizedIds(int $accountId, AuthorizedNodes $nodes, array $codes): array
    {
        if ($nodes->isEmpty()) {
            return [];
        }

        $ids = $this->available($accountId, $nodes)
            ->whereIn('e.code', $codes)
            ->select('e.id')
            ->pluck('e.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values($ids);
    }

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

    /** @return list<string> */
    public function preferredCodes(int $accountId, int $userId): array
    {
        $value = DB::table('account_memberships')
            ->where('account_id', $accountId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->value('workspace_establishments');

        return $value === null ? [] : json_decode($value, true, flags: JSON_THROW_ON_ERROR);
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
}
