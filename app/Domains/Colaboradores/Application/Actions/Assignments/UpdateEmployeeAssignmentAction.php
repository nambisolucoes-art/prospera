<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Actions\Assignments;

use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Colaboradores\Application\Data\Assignments\UpdateEmployeeAssignmentData;
use App\Domains\Colaboradores\Domain\DTOs\Assignments\UpdateEmployeeAssignmentDTO;
use App\Domains\Colaboradores\Domain\Models\Employee;
use App\Domains\Colaboradores\Domain\Models\EmployeeAssignment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Corrige uma lotação (função, departamento e datas), sem criar período novo. É a regra do mesmo dia: trocar a função
 * de um período que começa hoje é correção auditada, e não uma lotação nova.
 */
final readonly class UpdateEmployeeAssignmentAction
{
    public function __construct(private GuardEmployeeAssignmentPeriodAction $guard) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(UpdateEmployeeAssignmentData $data, EmployeeAssignment $assignment): EmployeeAssignment
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

            $this->guard->execute(
                $locked->employee_id,
                $locked->establishment_id,
                $data->startsOn,
                $data->endsOn,
                $locked->is_primary,
                $locked->id,
            );

            $assignmentDTO = new UpdateEmployeeAssignmentDTO(
                departmentId: $data->departmentId,
                jobTitleId: $data->jobTitleId,
                startsOn: $data->startsOn,
                endsOn: $data->endsOn,
            );

            $locked->revise($assignmentDTO);

            if ($locked->isDirty()) {
                $locked->saveOrFail();
            }

            return $locked;
        });
    }
}
