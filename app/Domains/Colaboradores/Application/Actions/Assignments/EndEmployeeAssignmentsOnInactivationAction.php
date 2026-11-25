<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Actions\Assignments;

use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Colaboradores\Application\Queries\Assignments\ListOpenAssignmentsQuery;
use App\Domains\Colaboradores\Domain\Models\Employee;
use App\Support\BusinessDate;

/**
 * Inativar o colaborador encerra, em `ends_on = hoje`, as lotações já iniciadas e ainda vigentes. Lotações futuras
 * permanecem, inertes enquanto o vínculo estiver inativo, e o histórico não é apagado. Reativar não reabre nada.
 */
final readonly class EndEmployeeAssignmentsOnInactivationAction
{
    public function __construct(private ListOpenAssignmentsQuery $open) {}

    /** Devolve quantas lotações foram encerradas. */
    public function execute(Employee $employee): int
    {
        return AuditLog::transaction(function () use ($employee): int {
            $today = BusinessDate::today();
            $assignments = $this->open->execute($employee->id, $today);

            foreach ($assignments as $assignment) {
                $assignment->forceFill(['ends_on' => $today])->saveOrFail();
            }

            return $assignments->count();
        });
    }
}
