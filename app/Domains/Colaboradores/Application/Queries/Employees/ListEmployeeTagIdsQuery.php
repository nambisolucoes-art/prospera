<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Queries\Employees;

use Illuminate\Support\Facades\DB;

/** Etiquetas convencionais escolhidas em cada vínculo de colaborador, numa única consulta. */
final readonly class ListEmployeeTagIdsQuery
{
    /**
     * @param  list<int>  $employeeIds
     * @return array<int, list<int>>
     */
    public function execute(int $accountId, array $employeeIds): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $result = [];

        $rows = DB::table('employees_tags')
            ->where('account_id', $accountId)
            ->whereIntegerInRaw('relation_id', $employeeIds)
            ->orderBy('tag_id')
            ->get(['relation_id', 'tag_id']);

        foreach ($rows as $row) {
            $result[(int) $row->relation_id][] = (int) $row->tag_id;
        }

        return $result;
    }
}
