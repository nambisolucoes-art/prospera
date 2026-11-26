<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Queries\Assignments;

use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use App\Domains\Colaboradores\Domain\Models\EmployeeAssignment;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lotações dos vínculos informados, só nas unidades que o descritor alcança (empresa inteira ou a própria unidade).
 * Uma consulta atende a página inteira, com unidade, departamento e função no mesmo resultado, sem N+1.
 */
final readonly class ListEmployeeAssignmentsQuery
{
    /**
     * @param  list<int>  $employeeIds
     * @return Collection<int, EmployeeAssignment>
     */
    public function execute(int $accountId, AuthorizedNodes $nodes, array $employeeIds): Collection
    {
        if ($employeeIds === []) {
            return new Collection;
        }

        $query = EmployeeAssignment::query()
            ->join('establishments', function ($join): void {
                $join->on('establishments.id', '=', 'employee_assignments.establishment_id')
                    ->on('establishments.company_id', '=', 'employee_assignments.company_id');
            })
            ->leftJoin('departments', function ($join): void {
                $join->on('departments.id', '=', 'employee_assignments.department_id')
                    ->on('departments.company_id', '=', 'employee_assignments.company_id');
            })
            ->leftJoin('job_titles', function ($join): void {
                $join->on('job_titles.id', '=', 'employee_assignments.job_title_id')
                    ->on('job_titles.company_id', '=', 'employee_assignments.company_id');
            })
            ->where('employee_assignments.account_id', $accountId)
            ->whereIntegerInRaw('employee_assignments.employee_id', $employeeIds)
            ->select('employee_assignments.*')
            ->selectRaw('establishments.code as establishment_code')
            ->selectRaw('establishments.name as establishment_name')
            ->selectRaw('establishments.timezone as establishment_timezone')
            ->selectRaw('departments.name as department_name')
            ->selectRaw('job_titles.name as job_title_name');

        $nodes->apply($query, 'employee_assignments.company_id', 'employee_assignments.establishment_id');

        return $query
            ->orderByDesc('employee_assignments.starts_on')
            ->orderByDesc('employee_assignments.id')
            ->get();
    }
}
