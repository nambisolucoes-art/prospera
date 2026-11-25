<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Queries\Assignments;

use App\Domains\Colaboradores\Domain\Models\EmployeeAssignment;
use Illuminate\Database\Eloquent\Collection;

/** Lotações do colaborador já iniciadas e ainda vigentes na data, sem data final ou com fim posterior. */
final readonly class ListOpenAssignmentsQuery
{
    /** @return Collection<int, EmployeeAssignment> */
    public function execute(int $employeeId, string $today): Collection
    {
        return EmployeeAssignment::query()
            ->where('employee_id', $employeeId)
            ->where('starts_on', '<=', $today)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>', $today))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }
}
