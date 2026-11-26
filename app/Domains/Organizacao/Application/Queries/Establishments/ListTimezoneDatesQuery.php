<?php

declare(strict_types=1);

namespace App\Domains\Organizacao\Application\Queries\Establishments;

use App\Support\BusinessDate;
use Illuminate\Support\Facades\DB;

/**
 * A data civil de hoje em cada fuso que algum estabelecimento usa (PD2-08). Poucos fusos cobrem a conta inteira, então
 * a data de negócio de uma consulta por vigência vira uma condição por fuso, resolvida no banco e sem N+1.
 */
final readonly class ListTimezoneDatesQuery
{
    /** @return array<string, string> fuso IANA para data `Y-m-d`; sem conta, considera todas (verificações globais) */
    public function execute(?int $accountId = null): array
    {
        $zones = DB::table('establishments')
            ->when($accountId !== null, fn ($query) => $query->where('account_id', $accountId))
            ->distinct()
            ->orderBy('timezone')
            ->pluck('timezone');

        $dates = [];

        foreach ($zones as $zone) {
            $dates[(string) $zone] = BusinessDate::todayIn((string) $zone);
        }

        return $dates;
    }
}
