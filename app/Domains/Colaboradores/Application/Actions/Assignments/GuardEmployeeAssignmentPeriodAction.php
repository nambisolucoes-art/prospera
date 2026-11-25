<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Actions\Assignments;

use App\Domains\Colaboradores\Application\Queries\Assignments\AssignmentOverlapExistsQuery;
use App\Domains\Colaboradores\Application\Queries\Assignments\OverlappingPrimaryIdsQuery;
use Illuminate\Validation\ValidationException;

/**
 * Confere as regras que dependem de várias linhas: sem sobreposição no mesmo estabelecimento e sem duas principais
 * em períodos que se cruzam. Deve rodar dentro da transação, com a linha do colaborador travada.
 */
final readonly class GuardEmployeeAssignmentPeriodAction
{
    public function __construct(
        private AssignmentOverlapExistsQuery $overlaps,
        private OverlappingPrimaryIdsQuery $primaries,
    ) {}

    /** @throws ValidationException */
    public function execute(
        int $employeeId,
        int $establishmentId,
        string $startsOn,
        ?string $endsOn,
        bool $isPrimary,
        ?int $ignoreId,
    ): void {
        if ($this->overlaps->execute($employeeId, $establishmentId, $startsOn, $endsOn, $ignoreId)) {
            throw ValidationException::withMessages([
                'starts_on' => 'O período se sobrepõe a outra lotação deste colaborador nesta unidade.',
            ]);
        }

        if ($isPrimary && $this->primaries->execute($employeeId, $startsOn, $endsOn, $ignoreId) !== []) {
            throw ValidationException::withMessages([
                'is_primary' => 'Já existe outra lotação principal neste período. Use Tornar principal para transferir.',
            ]);
        }
    }
}
