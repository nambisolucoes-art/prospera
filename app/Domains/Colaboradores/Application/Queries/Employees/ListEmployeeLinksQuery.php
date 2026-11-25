<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Queries\Employees;

use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use App\Domains\Colaboradores\Domain\Models\Employee;
use Illuminate\Database\Eloquent\Collection;

/**
 * Vínculos de colaborador das pessoas informadas, só nas empresas que o descritor autoriza.
 *
 * Uma única consulta atende a página inteira da listagem, sem N+1.
 */
final readonly class ListEmployeeLinksQuery
{
    /**
     * @param  list<int>  $personIds
     * @return Collection<int, Employee>
     */
    public function execute(int $accountId, AuthorizedNodes $nodes, array $personIds): Collection
    {
        if ($personIds === []) {
            return new Collection;
        }

        $query = Employee::query()
            ->join('companies', function ($join): void {
                $join->on('companies.id', '=', 'employees.company_id')
                    ->on('companies.account_id', '=', 'employees.account_id');
            })
            ->where('employees.account_id', $accountId)
            ->whereIntegerInRaw('employees.person_id', $personIds)
            ->select([
                'employees.id',
                'employees.account_id',
                'employees.company_id',
                'employees.person_id',
                'employees.active',
                'employees.work_email',
                'employees.work_phone',
                'employees.work_whatsapp',
                'companies.code as company_code',
                'companies.active as company_active',
            ])
            ->selectRaw('coalesce(companies.trade_name, companies.name) as company_name');

        EmployeeScope::apply($query, $nodes);

        return $query
            ->orderBy('company_name')
            ->orderBy('employees.id')
            ->get();
    }
}
