<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Actions\Hours;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Institucional\Application\Actions\Profile\LockProfileOwnerAction;
use App\Domains\Institucional\Application\Data\Hours\CreateHourExceptionData;
use App\Domains\Institucional\Domain\DTOs\Hours\CreateHourExceptionDTO;
use App\Domains\Institucional\Domain\Exceptions\InvalidHourException;
use App\Domains\Institucional\Domain\Models\HourException;
use App\Domains\Institucional\Domain\ValueObjects\ProfileOwner;
use App\Support\Exceptions\ResourceNotAvailable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Cadastra uma exceção de horário da empresa ou do estabelecimento. É dado operacional: vale ao salvar.
 */
final readonly class CreateHourExceptionAction
{
    public function __construct(
        private LockProfileOwnerAction $lockOwner,
        private AssertHourExceptionPeriodFreeAction $assertPeriodFree,
        private AssertHourExceptionImpactAction $assertImpact,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws InvalidHourException
     * @throws ResourceNotAvailable
     */
    public function execute(CreateHourExceptionData $data, ProfileOwner $owner): HourException
    {
        return AuditLog::transaction(function () use ($data, $owner): HourException {
            Gate::authorize('account.institutional.update', $owner->node());

            $this->lockOwner->execute($owner);

            $exceptionDTO = new CreateHourExceptionDTO(
                kind: $data->kind,
                startsOn: $data->startsOn,
                endsOn: $data->endsOn,
                shifts: $data->shifts,
                note: $data->note,
                appliesToOverrides: $data->appliesToOverrides,
            );

            $exception = HourException::register(
                $exceptionDTO,
                $owner->accountId,
                $owner->companyId,
                $owner->establishmentId,
            );

            $this->assertPeriodFree->execute($owner, $data->startsOn, $data->endsOn, null);

            $this->assertImpact->execute(
                $owner,
                $data->kind,
                $data->startsOn,
                $data->endsOn,
                $data->appliesToOverrides,
                $data->impactToken,
            );

            $exception->saveOrFail();

            return $exception;
        });
    }
}
