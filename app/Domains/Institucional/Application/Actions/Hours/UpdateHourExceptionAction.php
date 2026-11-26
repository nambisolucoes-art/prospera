<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Actions\Hours;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Institucional\Application\Actions\Profile\LockProfileOwnerAction;
use App\Domains\Institucional\Application\Data\Hours\UpdateHourExceptionData;
use App\Domains\Institucional\Domain\DTOs\Hours\UpdateHourExceptionDTO;
use App\Domains\Institucional\Domain\Exceptions\InvalidHourException;
use App\Domains\Institucional\Domain\Models\HourException;
use App\Support\Exceptions\ResourceNotAvailable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

/**
 * Altera uma exceção de horário do próprio dono. Sem mudança, nada é gravado nem auditado.
 */
final readonly class UpdateHourExceptionAction
{
    public function __construct(
        private LockProfileOwnerAction $lockOwner,
        private AssertHourExceptionPeriodFreeAction $assertPeriodFree,
        private AssertHourExceptionImpactAction $assertImpact,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws InvalidHourException
     * @throws ModelNotFoundException
     * @throws ResourceNotAvailable
     */
    public function execute(UpdateHourExceptionData $data, HourException $exception): HourException
    {
        return AuditLog::transaction(function () use ($data, $exception): HourException {
            $owner = $exception->owner();

            Gate::authorize('account.institutional.update', $owner->node());

            $this->lockOwner->execute($owner);

            $lockedException = HourException::where('account_id', $exception->account_id)
                ->where('company_id', $exception->company_id)
                ->lockForUpdate()
                ->findOrFail($exception->id);

            $exceptionDTO = new UpdateHourExceptionDTO(
                kind: $data->kind,
                startsOn: $data->startsOn,
                endsOn: $data->endsOn,
                shifts: $data->shifts,
                note: $data->note,
                appliesToOverrides: $data->appliesToOverrides,
            );

            $lockedException->revise($exceptionDTO);

            $this->assertPeriodFree->execute($owner, $data->startsOn, $data->endsOn, $lockedException->id);

            $this->assertImpact->execute(
                $owner,
                $data->kind,
                $data->startsOn,
                $data->endsOn,
                $data->appliesToOverrides,
                $data->impactToken,
            );

            if ($lockedException->isDirty()) {
                $lockedException->saveOrFail();
            }

            return $lockedException;
        });
    }
}
