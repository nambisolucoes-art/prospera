<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Queries\Assignments;

use App\Domains\Colaboradores\Domain\Models\EmployeeAssignment;
use App\Domains\Organizacao\Application\Queries\Establishments\ListTimezoneDatesQuery;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lotações do colaborador já iniciadas e ainda vigentes no dia civil da própria unidade (PD2-08): sem data final ou com
 * fim posterior ao "hoje" do fuso dela. Trava só as lotações, porque a unidade é filtrada por subconsulta.
 */
final readonly class ListOpenAssignmentsQuery
{
    public function __construct(private ListTimezoneDatesQuery $timezoneDates) {}

    /** @return Collection<int, EmployeeAssignment> */
    public function execute(int $accountId, int $employeeId): Collection
    {
        $query = EmployeeAssignment::query()
            ->where('account_id', $accountId)
            ->where('employee_id', $employeeId);

        LocalDatePredicate::apply($query, LocalDatePredicate::OPEN, $this->timezoneDates->execute($accountId));

        return $query->orderBy('id')->lockForUpdate()->get();
    }
}
