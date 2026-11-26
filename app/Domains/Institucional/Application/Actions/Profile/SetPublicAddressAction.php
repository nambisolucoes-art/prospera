<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Actions\Profile;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Institucional\Application\Data\Profile\SetPublicAddressData;
use App\Domains\Institucional\Domain\ValueObjects\ProfileOwner;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Support\Exceptions\ResourceNotAvailable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Liga ou desliga "Exibir endereço" do estabelecimento. É operacional: vale ao salvar. O endereço continua em
 * `establishments.address`, sem cópia, e nada é publicado por padrão.
 */
final readonly class SetPublicAddressAction
{
    /**
     * @throws AuthorizationException
     * @throws ResourceNotAvailable
     */
    public function execute(SetPublicAddressData $data, ProfileOwner $owner): Establishment
    {
        return AuditLog::transaction(function () use ($data, $owner): Establishment {
            if (! $owner->isEstablishment()) {
                throw new ResourceNotAvailable;
            }

            Gate::authorize('account.institutional.update', $owner->node());

            $company = Company::where('account_id', $owner->accountId)
                ->whereKey($owner->companyId)
                ->lockForUpdate()
                ->firstOrFail();

            $establishment = Establishment::where('account_id', $owner->accountId)
                ->where('company_id', $owner->companyId)
                ->lockForUpdate()
                ->findOrFail($owner->establishmentId);

            if (! $company->active || ! $establishment->active) {
                throw new ResourceNotAvailable;
            }

            $establishment->show_public_address = $data->show;

            if ($establishment->isDirty('show_public_address')) {
                $establishment->saveOrFail();
            }

            return $establishment;
        });
    }
}
