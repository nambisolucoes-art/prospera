<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Queries\Assignments;

use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use App\Domains\Colaboradores\Domain\Models\EmployeeAssignment;

/** Lê uma lotação do vínculo e da pessoa, dentro da conta e das unidades que o descritor alcança. */
final readonly class ShowEmployeeAssignmentQuery
{
    public function execute(
        int $accountId,
        AuthorizedNodes $nodes,
        int $employeeId,
        int $assignmentId,
    ): ?EmployeeAssignment {
        $query = EmployeeAssignment::query()
            ->where('employee_assignments.account_id', $accountId)
            ->where('employee_assignments.employee_id', $employeeId)
            ->whereKey($assignmentId);

        $nodes->apply($query, 'employee_assignments.company_id', 'employee_assignments.establishment_id');

        return $query->first();
    }
}
