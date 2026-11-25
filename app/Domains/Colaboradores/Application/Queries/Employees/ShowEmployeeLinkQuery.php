<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Queries\Employees;

use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use App\Domains\Colaboradores\Domain\Models\Employee;

/** Lê um vínculo de colaborador dentro da conta, da pessoa e das empresas que o descritor autoriza. */
final readonly class ShowEmployeeLinkQuery
{
    public function execute(int $accountId, AuthorizedNodes $nodes, int $personId, int $employeeId): ?Employee
    {
        $query = Employee::query()
            ->where('employees.account_id', $accountId)
            ->where('employees.person_id', $personId)
            ->whereKey($employeeId);

        EmployeeScope::apply($query, $nodes);

        return $query->first();
    }
}
