<?php

declare(strict_types=1);

namespace App\Domains\Midia\Application\Actions;

use App\Domains\Midia\Domain\ValueObjects\MediaOwner;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Support\Exceptions\ResourceNotAvailable;

/** Trava a empresa e depois a unidade (a mesma ordem das demais Actions por dono) e recusa dono inativo. */
final readonly class LockMediaOwnerAction
{
    /**
     * @throws ResourceNotAvailable
     */
    public function execute(MediaOwner $owner): void
    {
        $lockedCompany = Company::where('account_id', $owner->accountId)
            ->whereKey($owner->companyId)
            ->lockForUpdate()
            ->firstOrFail();

        if (! $lockedCompany->active) {
            throw new ResourceNotAvailable;
        }

        if (! $owner->isEstablishment()) {
            return;
        }

        $lockedEstablishment = Establishment::where('account_id', $owner->accountId)
            ->where('company_id', $owner->companyId)
            ->lockForUpdate()
            ->findOrFail($owner->establishmentId);

        if (! $lockedEstablishment->active) {
            throw new ResourceNotAvailable;
        }
    }
}
