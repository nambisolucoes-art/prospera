<?php

declare(strict_types=1);

namespace App\Domains\Institucional\Application\Actions\Hours;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Institucional\Application\Actions\Profile\LockProfileOwnerAction;
use App\Domains\Institucional\Domain\Models\HourException;
use App\Support\Exceptions\ResourceNotAvailable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

/**
 * Exclui uma exceção de horário. A exclusão é auditada com o conteúdo que existia, no nó da exceção, e a unidade volta
 * a seguir o horário semanal.
 */
final readonly class DeleteHourExceptionAction
{
    public function __construct(
        private LockProfileOwnerAction $lockOwner,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     * @throws ResourceNotAvailable
     */
    public function execute(HourException $exception): void
    {
        AuditLog::transaction(function () use ($exception): void {
            $owner = $exception->owner();

            Gate::authorize('account.institutional.update', $owner->node());

            $this->lockOwner->execute($owner);

            HourException::where('account_id', $exception->account_id)
                ->where('company_id', $exception->company_id)
                ->lockForUpdate()
                ->findOrFail($exception->id)
                ->delete();
        });
    }
}
