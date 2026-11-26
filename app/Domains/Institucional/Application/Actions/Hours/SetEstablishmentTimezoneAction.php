<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Actions\Hours;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Institucional\Application\Actions\Profile\LockProfileOwnerAction;
use App\Domains\Institucional\Application\Data\Hours\SetEstablishmentTimezoneData;
use App\Domains\Institucional\Domain\ValueObjects\ProfileOwner;
use App\Domains\Organizacao\Domain\Exceptions\InvalidTimezone;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Support\Exceptions\ResourceNotAvailable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Define o fuso IANA do estabelecimento (PD2-08). Autoriza pela permissão de edição do perfil institucional sobre o
 * próprio nó da unidade. Mesmo fuso não grava nem audita.
 */
final readonly class SetEstablishmentTimezoneAction
{
    public function __construct(
        private LockProfileOwnerAction $lockOwner,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws InvalidTimezone
     * @throws ResourceNotAvailable
     */
    public function execute(SetEstablishmentTimezoneData $data, ProfileOwner $owner): Establishment
    {
        return AuditLog::transaction(function () use ($data, $owner): Establishment {
            if (! $owner->isEstablishment()) {
                throw new ResourceNotAvailable;
            }

            Gate::authorize('account.institutional.update', $owner->node());

            $lockedEstablishment = $this->lockOwner->execute($owner);

            $lockedEstablishment->useTimezone($data->timezone);

            if ($lockedEstablishment->isDirty('timezone')) {
                $lockedEstablishment->saveOrFail();
            }

            return $lockedEstablishment;
        });
    }
}
