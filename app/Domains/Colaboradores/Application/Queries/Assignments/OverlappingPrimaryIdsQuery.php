<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Queries\Assignments;

use Illuminate\Support\Facades\DB;

/** Lotações principais do colaborador, em qualquer unidade, cujo período se sobrepõe ao informado. */
final readonly class OverlappingPrimaryIdsQuery
{
    /** @return list<int> */
    public function execute(int $employeeId, string $startsOn, ?string $endsOn, ?int $ignoreId): array
    {
        $ids = DB::table('employee_assignments')
            ->where('employee_id', $employeeId)
            ->where('is_primary', true)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->when($endsOn !== null, fn ($query) => $query->where('starts_on', '<=', $endsOn))
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $startsOn))
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return array_values($ids);
    }
}
