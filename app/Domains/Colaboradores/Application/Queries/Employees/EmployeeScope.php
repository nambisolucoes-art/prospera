<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Queries\Employees;

use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use App\Domains\Colaboradores\Domain\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Escopo de visibilidade de vínculos de colaborador (DA2-18). Quem alcança a empresa vê todos os vínculos dela.
 * Quem alcança só uma unidade vê os vínculos que têm lotação, vigente ou histórica, nessa unidade.
 */
final class EmployeeScope
{
    /** @param Builder<Employee> $query */
    public static function apply(Builder $query, AuthorizedNodes $nodes, string $table = 'employees'): void
    {
        if ($nodes->tenant) {
            return;
        }

        $query->where(function (Builder $scope) use ($nodes, $table): void {
            $scope->whereIn($table.'.company_id', $nodes->companies);

            if ($nodes->establishments === []) {
                return;
            }

            $scope->orWhereExists(
                DB::table('employee_assignments as scope_assignments')
                    ->whereColumn('scope_assignments.employee_id', $table.'.id')
                    ->whereColumn('scope_assignments.company_id', $table.'.company_id')
                    ->whereIn('scope_assignments.establishment_id', $nodes->establishments)
                    ->select(DB::raw('1')),
            );
        });
    }
}
