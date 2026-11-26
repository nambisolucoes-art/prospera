<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Actions\Assignments;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Colaboradores\Application\Queries\Assignments\ListOpenAssignmentsQuery;
use App\Domains\Colaboradores\Domain\Models\Employee;
use App\Domains\Organizacao\Application\Queries\Establishments\ListEstablishmentDatesQuery;

/**
 * Inativar o colaborador encerra, em `ends_on = hoje`, as lotações já iniciadas e ainda vigentes. "Hoje" é a data civil
 * do fuso de cada unidade (PD2-08). Lotações futuras permanecem, inertes enquanto o vínculo estiver inativo, e o
 * histórico não é apagado. Reativar não reabre nada.
 */
final readonly class EndEmployeeAssignmentsOnInactivationAction
{
    public function __construct(
        private ListOpenAssignmentsQuery $open,
        private ListEstablishmentDatesQuery $unitDates,
    ) {}

    /** Devolve quantas lotações foram encerradas. */
    public function execute(Employee $employee): int
    {
        return AuditLog::transaction(function () use ($employee): int {
            $assignments = $this->open->execute($employee->account_id, $employee->id);
            $todays = $this->unitDates->execute(
                $employee->account_id,
                array_values($assignments->pluck('establishment_id')->map(fn ($id) => (int) $id)->all()),
            );

            foreach ($assignments as $assignment) {
                $assignment->forceFill(['ends_on' => $todays[$assignment->establishment_id]])->saveOrFail();
            }

            return $assignments->count();
        });
    }
}
