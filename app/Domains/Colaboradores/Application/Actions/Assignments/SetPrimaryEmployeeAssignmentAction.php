<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Actions\Assignments;

use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Colaboradores\Application\Queries\Assignments\OverlappingPrimaryIdsQuery;
use App\Domains\Colaboradores\Domain\Models\Employee;
use App\Domains\Colaboradores\Domain\Models\EmployeeAssignment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Define a lotação principal do colaborador. A marca é uma propriedade do período: as principais que se sobrepõem a
 * este período perdem a marca, na mesma operação, e cada alteração exige `assign` sobre a unidade da lotação.
 */
final readonly class SetPrimaryEmployeeAssignmentAction
{
    public function __construct(private OverlappingPrimaryIdsQuery $primaries) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(EmployeeAssignment $assignment): EmployeeAssignment
    {
        return AuditLog::transaction(function () use ($assignment): EmployeeAssignment {
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

            $previousIds = $this->primaries->execute($locked->employee_id, $startsOn, $endsOn, $locked->id);

            $previous = EmployeeAssignment::where('employee_id', $locked->employee_id)
                ->whereIn('id', $previousIds)
                ->lockForUpdate()
                ->get();

            foreach ($previous as $other) {
                Gate::authorize('account.employees.assign', AccessNode::establishment(
                    $other->account_id,
                    $other->company_id,
                    $other->establishment_id,
                ));

                $other->forceFill(['is_primary' => false])->saveOrFail();
            }

            if (! $locked->is_primary) {
                $locked->forceFill(['is_primary' => true])->saveOrFail();
            }

            return $locked;
        });
    }
}
