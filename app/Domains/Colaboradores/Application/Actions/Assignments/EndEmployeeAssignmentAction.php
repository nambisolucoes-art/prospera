<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Actions\Assignments;

use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Colaboradores\Application\Data\Assignments\EndEmployeeAssignmentData;
use App\Domains\Colaboradores\Application\Queries\Assignments\ListRemainingAssignmentsQuery;
use App\Domains\Colaboradores\Domain\Models\Employee;
use App\Domains\Colaboradores\Domain\Models\EmployeeAssignment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Encerra uma lotação (`ends_on` é o último dia de vigência). Encerrar a principal enquanto houver outra lotação que
 * continua valendo exige indicar a nova principal na mesma operação. A nova principal substitui a marca das
 * principais que se sobrepõem ao período dela (`SetPrimaryEmployeeAssignmentAction`).
 */
final readonly class EndEmployeeAssignmentAction
{
    public function __construct(
        private ListRemainingAssignmentsQuery $remaining,
        private SetPrimaryEmployeeAssignmentAction $setPrimary,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(EndEmployeeAssignmentData $data, EmployeeAssignment $assignment): EmployeeAssignment
    {
        return AuditLog::transaction(function () use ($data, $assignment): EmployeeAssignment {
            Employee::where('account_id', $assignment->account_id)
                ->where('company_id', $assignment->company_id)
                ->lockForUpdate()
                ->findOrFail($assignment->employee_id);

            $locked = EmployeeAssignment::where('account_id', $assignment->account_id)
                ->where('company_id', $assignment->company_id)
                ->lockForUpdate()
                ->findOrFail($assignment->id);

            Gate::authorize('account.employees.assign', AccessNode::establishment(
                $locked->account_id,
                $locked->company_id,
                $locked->establishment_id,
            ));

            $startsOn = substr((string) $locked->starts_on, 0, 10);
            $currentEnd = $locked->ends_on === null ? null : substr((string) $locked->ends_on, 0, 10);

            if ($data->endsOn < $startsOn) {
                throw ValidationException::withMessages([
                    'ends_on' => 'A data final não pode ser anterior ao início da lotação.',
                ]);
            }

            if ($currentEnd !== null && $data->endsOn > $currentEnd) {
                throw ValidationException::withMessages([
                    'ends_on' => 'A lotação já termina antes desta data.',
                ]);
            }

            // Só entram como candidatas as lotações que quem encerra pode gerenciar: o escopo da unidade não revela as
            // lotações de outras unidades, e a empresa define a principal delas depois, se for o caso.
            $others = $this->remaining->execute($locked->employee_id, $locked->id, $data->endsOn)
                ->filter(fn (EmployeeAssignment $other) => Gate::allows('account.employees.assign', AccessNode::establishment(
                    $other->account_id,
                    $other->company_id,
                    $other->establishment_id,
                )));

            $endsPrimary = $locked->is_primary;

            if ($endsPrimary && $others->isNotEmpty() && $data->newPrimaryId === null) {
                throw ValidationException::withMessages([
                    'new_primary_id' => 'Indique a nova lotação principal antes de encerrar a principal.',
                ]);
            }

            $heir = null;

            if ($endsPrimary && $data->newPrimaryId !== null) {
                $heir = $others->firstWhere('id', $data->newPrimaryId);

                if ($heir === null) {
                    throw ValidationException::withMessages([
                        'new_primary_id' => 'Escolha uma lotação que continue valendo depois do encerramento.',
                    ]);
                }
            }

            $locked->forceFill(['ends_on' => $data->endsOn])->saveOrFail();

            if ($heir !== null) {
                $this->setPrimary->execute($heir);
            }

            return $locked;
        });
    }
}
