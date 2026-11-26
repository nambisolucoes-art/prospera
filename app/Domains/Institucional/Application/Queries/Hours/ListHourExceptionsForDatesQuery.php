<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Queries\Hours;

use Illuminate\Support\Facades\DB;

/**
 * Exceções da empresa e das unidades informadas que cobrem algum dia do intervalo. Uma consulta para todas as unidades
 * da empresa: o resolvedor filtra cada unidade pela data local dela. O índice `(company_id, establishment_id,
 * starts_on)` atende o dono e o início do período.
 */
final readonly class ListHourExceptionsForDatesQuery
{
    /**
     * @param  list<int>  $establishmentIds
     * @return list<array{id: int, establishment_id: int|null, kind: string, starts_on: string, ends_on: string, shifts: list<array{start: string, end: string}>|null, note: string|null, applies_to_overrides: bool}>
     */
    public function execute(int $accountId, int $companyId, array $establishmentIds, string $from, string $to): array
    {
        $rows = DB::table('hour_exceptions')
            ->where('account_id', $accountId)
            ->where('company_id', $companyId)
            ->where(fn ($owner) => $owner->whereNull('establishment_id')->orWhereIn('establishment_id', $establishmentIds))
            ->where('starts_on', '<=', $to)
            ->where('ends_on', '>=', $from)
            ->get(['id', 'establishment_id', 'kind', 'starts_on', 'ends_on', 'shifts', 'note', 'applies_to_overrides']);

        $result = [];

        foreach ($rows as $row) {
            /** @var list<array{start: string, end: string}>|null $shifts */
            $shifts = $row->shifts === null ? null : json_decode((string) $row->shifts, true);

            $result[] = [
                'id' => (int) $row->id,
                'establishment_id' => $row->establishment_id === null ? null : (int) $row->establishment_id,
                'kind' => (string) $row->kind,
                'starts_on' => substr((string) $row->starts_on, 0, 10),
                'ends_on' => substr((string) $row->ends_on, 0, 10),
                'shifts' => $shifts,
                'note' => $row->note === null ? null : (string) $row->note,
                'applies_to_overrides' => (bool) $row->applies_to_overrides,
            ];
        }

        return $result;
    }
}
