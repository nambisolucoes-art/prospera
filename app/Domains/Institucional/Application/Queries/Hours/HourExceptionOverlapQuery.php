<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Queries\Hours;

use App\Domains\Institucional\Domain\ValueObjects\ProfileOwner;
use Illuminate\Support\Facades\DB;

/** Outra exceção do mesmo dono cujo período cruza o intervalo informado (as pontas contam). */
final readonly class HourExceptionOverlapQuery
{
    public function execute(ProfileOwner $owner, string $startsOn, string $endsOn, ?int $ignoreId): bool
    {
        return DB::table('hour_exceptions')
            ->where('account_id', $owner->accountId)
            ->where('company_id', $owner->companyId)
            ->when(
                $owner->isEstablishment(),
                fn ($query) => $query->where('establishment_id', $owner->establishmentId),
                fn ($query) => $query->whereNull('establishment_id'),
            )
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('starts_on', '<=', $endsOn)
            ->where('ends_on', '>=', $startsOn)
            ->exists();
    }
}
