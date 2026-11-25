<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Queries\Employees;

use Illuminate\Support\Facades\DB;

/**
 * Descoberta por CPF ou CNPJ completo, sempre dentro da conta: devolve só o nome e o tipo e, para as empresas
 * informadas (as que o operador pode cadastrar), a situação do vínculo. Nada de outras empresas.
 */
final readonly class FindEmployeePersonQuery
{
    /**
     * @param  list<int>  $companyIds
     * @return array{id: int, name: string, person_type: string, links: list<array{code: string, active: bool}>}|null
     */
    public function execute(
        int $accountId,
        string $document,
        string $personType,
        array $companyIds,
    ): ?array {
        $person = DB::table('people')
            ->where('account_id', $accountId)
            ->where('document', $document)
            ->where('person_type', $personType)
            ->first(['id', 'name', 'person_type']);

        if ($person === null) {
            return null;
        }

        $links = DB::table('employees')
            ->join('companies', function ($join): void {
                $join->on('companies.id', '=', 'employees.company_id')
                    ->on('companies.account_id', '=', 'employees.account_id');
            })
            ->where('employees.account_id', $accountId)
            ->where('employees.person_id', $person->id)
            ->whereIntegerInRaw('employees.company_id', $companyIds)
            ->orderBy('companies.code')
            ->get(['companies.code', 'employees.active']);

        return [
            'id' => (int) $person->id,
            'name' => (string) $person->name,
            'person_type' => (string) $person->person_type,
            'links' => array_values($links
                ->map(fn (object $link) => ['code' => (string) $link->code, 'active' => (bool) $link->active])
                ->all()),
        ];
    }
}
