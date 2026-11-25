<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Actions\Assignments;

use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Colaboradores\Application\Data\Assignments\ChangeEmployeeAssignmentData;
use App\Domains\Colaboradores\Application\Data\Assignments\UpdateEmployeeAssignmentData;
use App\Domains\Colaboradores\Domain\DTOs\Assignments\CreateEmployeeAssignmentDTO;
use App\Domains\Colaboradores\Domain\Models\Employee;
use App\Domains\Colaboradores\Domain\Models\EmployeeAssignment;
use App\Support\BusinessDate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Troca função ou departamento a partir de uma data preservando o histórico: encerra o período em D - 1 e abre outro
 * em D, na mesma operação. Se o período começa em D, a mudança é correção do próprio período.
 */
final readonly class ChangeEmployeeAssignmentAction
{
    public function __construct(
        private UpdateEmployeeAssignmentAction $correct,
        private GuardEmployeeAssignmentPeriodAction $guard,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(ChangeEmployeeAssignmentData $data, EmployeeAssignment $assignment): EmployeeAssignment
    {
        return AuditLog::transaction(function () use ($data, $assignment): EmployeeAssignment {
            Employee::where('account_id', $assignment->account_id)
                ->where('company_id', $assignment->company_id)
                ->lockForUpdate()
                ->findOrFail($assignment->employee_id);

            $locked = EmployeeAssignment::where('account_id', $assignment->account_id)
                ->where('company_id', $assignment->company_id)
                ->lockForUpdate()
                ->findOrFail($assignment->id);

            Gate::authorize('account.employees.assign', AccessNode::establishment(
                $locked->account_id,
                $locked->company_id,
                $locked->establishment_id,
            ));

            $startsOn = substr((string) $locked->starts_on, 0, 10);
            $endsOn = $locked->ends_on === null ? null : substr((string) $locked->ends_on, 0, 10);

            if ($data->effectiveOn < $startsOn) {
                throw ValidationException::withMessages([
                    'effective_on' => 'A mudança não pode começar antes do início desta lotação. Corrija a lotação.',
                ]);
            }

            if ($endsOn !== null && $data->effectiveOn > $endsOn) {
                throw ValidationException::withMessages([
                    'effective_on' => 'A mudança está depois do fim desta lotação.',
                ]);
            }

            if ($data->effectiveOn === $startsOn) {
                $correction = new UpdateEmployeeAssignmentData(
                    departmentId: $data->departmentId,
                    jobTitleId: $data->jobTitleId,
                    startsOn: $startsOn,
                    endsOn: $endsOn,
                    contacts: null,
                );

                return $this->correct->execute($correction, $locked);
            }

            $locked->forceFill(['ends_on' => BusinessDate::previousDay($data->effectiveOn)])->saveOrFail();

            $this->guard->execute(
                $locked->employee_id,
                $locked->establishment_id,
                $data->effectiveOn,
                $endsOn,
                $locked->is_primary,
                null,
            );

            $next = EmployeeAssignment::register(
                new CreateEmployeeAssignmentDTO(
                    establishmentId: $locked->establishment_id,
                    departmentId: $data->departmentId,
                    jobTitleId: $data->jobTitleId,
                    startsOn: $data->effectiveOn,
                    endsOn: $endsOn,
                    isPrimary: $locked->is_primary,
                    contactMode: $locked->contact_mode,
                    workContacts: [
                        'email' => $locked->work_contacts['email'] ?? null,
                        'phone' => $locked->work_contacts['phone'] ?? null,
                        'whatsapp' => $locked->work_contacts['whatsapp'] ?? null,
                    ],
                ),
                $locked->account_id,
                $locked->company_id,
                $locked->employee_id,
            );
            $next->saveOrFail();

            return $next;
        });
    }
}
