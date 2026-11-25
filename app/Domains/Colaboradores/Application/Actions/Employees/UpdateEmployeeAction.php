<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Actions\Employees;

use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Colaboradores\Application\Data\Employees\UpdateEmployeeData;
use App\Domains\Colaboradores\Domain\DTOs\Employees\UpdateEmployeeDTO;
use App\Domains\Colaboradores\Domain\Models\Employee;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Pessoas\Application\Actions\SyncCommercialTagsAction;
use App\Support\Exceptions\ResourceNotAvailable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Altera os contatos profissionais e a situação de um único vínculo, sem tocar nos outros vínculos da pessoa.
 *
 * Cada mudança confere a própria permissão: contatos exigem editar, e a situação exige ativar ou inativar.
 * Inativar o vínculo não remove o acesso à conta nem as permissões da pessoa.
 */
final readonly class UpdateEmployeeAction
{
    public function __construct(private SyncCommercialTagsAction $syncTags) {}

    /**
     * @throws AuthorizationException
     * @throws ResourceNotAvailable
     */
    public function execute(UpdateEmployeeData $data, Employee $employee): void
    {
        AuditLog::transaction(function () use ($data, $employee): void {
            $company = Company::where('account_id', $employee->account_id)
                ->whereKey($employee->company_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $company->active) {
                throw new ResourceNotAvailable;
            }

            $lockedEmployee = Employee::where('account_id', $employee->account_id)
                ->where('company_id', $employee->company_id)
                ->lockForUpdate()
                ->findOrFail($employee->id);

            $node = AccessNode::company($lockedEmployee->account_id, $lockedEmployee->company_id);

            $employeeDTO = new UpdateEmployeeDTO(
                active: $data->active ?? $lockedEmployee->active,
                workEmail: $data->editsContacts ? $data->workEmail : $lockedEmployee->work_email,
                workPhone: $data->editsContacts ? $data->workPhone : $lockedEmployee->work_phone,
                workWhatsapp: $data->editsContacts ? $data->workWhatsapp : $lockedEmployee->work_whatsapp,
            );

            $lockedEmployee->revise($employeeDTO);

            if ($lockedEmployee->isDirty(['work_email', 'work_phone', 'work_whatsapp'])) {
                Gate::authorize('account.employees.update', $node);
            }

            if ($lockedEmployee->isDirty('active')) {
                if ($lockedEmployee->active) {
                    Gate::authorize('account.employees.activate', $node);
                } else {
                    Gate::authorize('account.employees.deactivate', $node);
                }
            }

            if ($data->tags !== null) {
                Gate::authorize('account.employees.update', $node);
                $this->syncTags->execute($data->tags, 'employees', $lockedEmployee);
            }

            if (! $lockedEmployee->isDirty()) {
                return;
            }

            $lockedEmployee->saveOrFail();
        });
    }
}
