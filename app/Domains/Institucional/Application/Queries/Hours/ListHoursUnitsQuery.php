<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Queries\Hours;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** As unidades da empresa que o resolvedor de horários calcula, com o fuso e a situação delas e da empresa. */
final readonly class ListHoursUnitsQuery
{
    /**
     * @param  list<int>|null  $establishmentIds  nulo para todas as unidades da empresa
     * @return Collection<int, \stdClass> com `id`, `code`, `name`, `timezone`, `unit_active` e `company_active`
     */
    public function execute(int $accountId, int $companyId, ?array $establishmentIds): Collection
    {
        return DB::table('establishments')
            ->join('companies', function ($join): void {
                $join->on('companies.id', '=', 'establishments.company_id')
                    ->on('companies.account_id', '=', 'establishments.account_id');
            })
            ->where('establishments.account_id', $accountId)
            ->where('establishments.company_id', $companyId)
            ->when($establishmentIds !== null, fn ($query) => $query->whereIntegerInRaw('establishments.id', $establishmentIds))
            ->orderBy('establishments.id')
            ->get([
                'establishments.id',
                'establishments.code',
                'establishments.name',
                'establishments.timezone',
                'establishments.active as unit_active',
                'companies.active as company_active',
            ])
            ->keyBy('id');
    }
}
