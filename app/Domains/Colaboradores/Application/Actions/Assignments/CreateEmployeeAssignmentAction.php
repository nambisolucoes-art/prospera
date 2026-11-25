<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Actions\Assignments;

use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Colaboradores\Application\Data\Assignments\CreateEmployeeAssignmentData;
use App\Domains\Colaboradores\Domain\DTOs\Assignments\CreateEmployeeAssignmentDTO;
use App\Domains\Colaboradores\Domain\Models\Employee;
use App\Domains\Colaboradores\Domain\Models\EmployeeAssignment;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Support\Exceptions\ResourceNotAvailable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Lota o colaborador num estabelecimento por um período. Exige `account.employees.assign` sobre a unidade e um
 * colaborador ativo: reativar não reabre períodos antigos, então a lotação nova é sempre explícita.
 */
final readonly class CreateEmployeeAssignmentAction
{
    public function __construct(private GuardEmployeeAssignmentPeriodAction $guard) {}

    /**
     * @throws AuthorizationException
     * @throws ResourceNotAvailable
     * @throws ValidationException
     */
    public function execute(CreateEmployeeAssignmentData $data, Employee $employee): EmployeeAssignment
    {
        return AuditLog::transaction(function () use ($data, $employee): EmployeeAssignment {
            $company = Company::where('account_id', $employee->account_id)
                ->whereKey($employee->company_id)
                ->lockForUpdate()
                ->firstOrFail();

            // A trava do colaborador serializa as escritas de lotação dele, inclusive na suíte SQLite.
            $locked = Employee::where('account_id', $employee->account_id)
                ->where('company_id', $employee->company_id)
                ->lockForUpdate()
                ->findOrFail($employee->id);

            if (! $company->active) {
                throw new ResourceNotAvailable;
            }

            Gate::authorize('account.employees.assign', AccessNode::establishment(
                $locked->account_id,
                $locked->company_id,
                $data->establishmentId,
            ));

            if (! $locked->active) {
                throw ValidationException::withMessages([
                    'employee' => 'O vínculo está inativo. Reative o vínculo antes de criar uma lotação.',
                ]);
            }

            $this->guard->execute(
                $locked->id,
                $data->establishmentId,
                $data->startsOn,
                $data->endsOn,
                $data->isPrimary,
                null,
            );

            $assignmentDTO = new CreateEmployeeAssignmentDTO(
                establishmentId: $data->establishmentId,
                departmentId: $data->departmentId,
                jobTitleId: $data->jobTitleId,
                startsOn: $data->startsOn,
                endsOn: $data->endsOn,
                isPrimary: $data->isPrimary,
                contactMode: $data->contacts->mode,
                workContacts: $data->contacts->contacts,
            );

            $assignment = EmployeeAssignment::register($assignmentDTO, $locked->account_id, $locked->company_id, $locked->id);
            $assignment->saveOrFail();

            return $assignment;
        });
    }
}
