<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Queries\Hours;

use App\Domains\Institucional\Domain\ValueObjects\ProfileOwner;
use Illuminate\Support\Facades\DB;

/**
 * Exceções de horário de um dono (empresa ou estabelecimento) que ainda valem ou valerão: `ends_on >= desde`. Sempre
 * dentro da conta e do dono, para que o identificador de outro escopo nunca apareça.
 */
final readonly class ListHourExceptionsQuery
{
    /** @return list<array<string, mixed>> */
    public function execute(ProfileOwner $owner, string $since): array
    {
        $rows = DB::table('hour_exceptions')
            ->where('account_id', $owner->accountId)
            ->where('company_id', $owner->companyId)
            ->when(
                $owner->isEstablishment(),
                fn ($query) => $query->where('establishment_id', $owner->establishmentId),
                fn ($query) => $query->whereNull('establishment_id'),
            )
            ->where('ends_on', '>=', $since)
            ->orderBy('starts_on')
            ->orderBy('id')
            ->limit(200)
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $result[] = [
                'id' => (int) $row->id,
                'kind' => (string) $row->kind,
                'starts_on' => substr((string) $row->starts_on, 0, 10),
                'ends_on' => substr((string) $row->ends_on, 0, 10),
                'shifts' => $row->shifts === null ? null : json_decode((string) $row->shifts, true),
                'note' => $row->note,
                'applies_to_overrides' => (bool) $row->applies_to_overrides,
            ];
        }

        return $result;
    }
}
