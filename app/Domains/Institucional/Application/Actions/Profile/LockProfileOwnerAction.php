<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Actions\Profile;

use App\Domains\Institucional\Domain\ValueObjects\ProfileOwner;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Support\Exceptions\ResourceNotAvailable;

/**
 * Trava o dono do perfil (horários, exceções e seções) dentro da transação do chamador: a empresa primeiro e a unidade depois, a mesma ordem das
 * seções do perfil. Isso serializa exceções, mudanças de herança e de fuso da mesma empresa. Dono inativo não é editável.
 */
final readonly class LockProfileOwnerAction
{
    /**
     * Devolve o estabelecimento travado, ou nulo quando o dono é a empresa.
     *
     * @throws ResourceNotAvailable
     */
    public function execute(ProfileOwner $owner): ?Establishment
    {
        $lockedCompany = Company::where('account_id', $owner->accountId)
            ->whereKey($owner->companyId)
            ->lockForUpdate()
            ->firstOrFail();

        if (! $lockedCompany->active) {
            throw new ResourceNotAvailable;
        }

        if (! $owner->isEstablishment()) {
            return null;
        }

        $lockedEstablishment = Establishment::where('account_id', $owner->accountId)
            ->where('company_id', $owner->companyId)
            ->lockForUpdate()
            ->findOrFail($owner->establishmentId);

        if (! $lockedEstablishment->active) {
            throw new ResourceNotAvailable;
        }

        return $lockedEstablishment;
    }
}
