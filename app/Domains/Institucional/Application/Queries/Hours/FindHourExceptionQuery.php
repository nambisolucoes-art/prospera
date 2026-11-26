<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Queries\Hours;

use App\Domains\Institucional\Domain\Models\HourException;
use App\Domains\Institucional\Domain\ValueObjects\ProfileOwner;

/** Uma exceção do dono informado, dentro da conta. O identificador de outro dono ou conta não existe para quem pede. */
final readonly class FindHourExceptionQuery
{
    public function execute(ProfileOwner $owner, int $id): ?HourException
    {
        return HourException::where('account_id', $owner->accountId)
            ->where('company_id', $owner->companyId)
            ->when(
                $owner->isEstablishment(),
                fn ($query) => $query->where('establishment_id', $owner->establishmentId),
                fn ($query) => $query->whereNull('establishment_id'),
            )
            ->whereKey($id)
            ->first();
    }
}
