<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Queries\Assignments;

use Illuminate\Support\Facades\DB;

/**
 * Há outra lotação do mesmo colaborador no mesmo estabelecimento que se sobreponha ao período, inclusive futura?
 * Os dois extremos são inclusivos e `ends_on` nulo significa sem data final definida.
 */
final readonly class AssignmentOverlapExistsQuery
{
    public function execute(
        int $employeeId,
        int $establishmentId,
        string $startsOn,
        ?string $endsOn,
        ?int $ignoreId,
    ): bool {
        return DB::table('employee_assignments')
            ->where('employee_id', $employeeId)
            ->where('establishment_id', $establishmentId)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->when($endsOn !== null, fn ($query) => $query->where('starts_on', '<=', $endsOn))
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $startsOn))
            ->exists();
    }
}
