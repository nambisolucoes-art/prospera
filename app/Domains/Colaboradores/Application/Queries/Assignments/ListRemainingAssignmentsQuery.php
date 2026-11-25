<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Queries\Assignments;

use App\Domains\Colaboradores\Domain\Models\EmployeeAssignment;
use Illuminate\Database\Eloquent\Collection;

/** Outras lotações do colaborador que continuam valendo depois da data (sem data final ou com fim posterior). */
final readonly class ListRemainingAssignmentsQuery
{
    /** @return Collection<int, EmployeeAssignment> */
    public function execute(int $employeeId, int $exceptId, string $afterDate): Collection
    {
        return EmployeeAssignment::query()
            ->where('employee_id', $employeeId)
            ->where('id', '!=', $exceptId)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>', $afterDate))
            ->orderBy('starts_on')
            ->orderBy('id')
            ->get();
    }
}
