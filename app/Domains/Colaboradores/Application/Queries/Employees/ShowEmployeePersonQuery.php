<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Queries\Employees;

use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use App\Domains\Colaboradores\Domain\Models\Employee;
use App\Domains\Pessoas\Domain\Models\Person;

/**
 * Lê a pessoa de uma ficha de colaborador. Só existe para o operador se houver vínculo numa empresa que o
 * descritor autoriza: sem ele, a resposta é a mesma de uma pessoa inexistente.
 */
final readonly class ShowEmployeePersonQuery
{
    public function execute(int $accountId, AuthorizedNodes $nodes, int $personId): ?Person
    {
        $links = Employee::query()
            ->select('employees.person_id')
            ->where('employees.account_id', $accountId)
            ->where('employees.person_id', $personId);

        EmployeeScope::apply($links, $nodes);

        return Person::query()
            ->where('people.account_id', $accountId)
            ->whereKey($personId)
            ->whereIn('people.id', $links)
            ->first();
    }
}
