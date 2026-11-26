<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Queries\Establishments;

use App\Support\BusinessDate;
use Illuminate\Support\Facades\DB;

/** A data civil de hoje de cada estabelecimento informado, no fuso IANA dele (PD2-08). Uma consulta, sem N+1. */
final readonly class ListEstablishmentDatesQuery
{
    /**
     * @param  list<int>  $establishmentIds
     * @return array<int, string> id do estabelecimento para data `Y-m-d`
     */
    public function execute(int $accountId, array $establishmentIds): array
    {
        if ($establishmentIds === []) {
            return [];
        }

        $dates = [];

        $rows = DB::table('establishments')
            ->where('account_id', $accountId)
            ->whereIntegerInRaw('id', $establishmentIds)
            ->get(['id', 'timezone']);

        foreach ($rows as $row) {
            $dates[(int) $row->id] = BusinessDate::todayIn((string) $row->timezone);
        }

        return $dates;
    }
}
