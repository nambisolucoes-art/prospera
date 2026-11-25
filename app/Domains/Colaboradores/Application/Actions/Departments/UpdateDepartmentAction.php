<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Actions\Departments;

use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Colaboradores\Application\Data\Departments\UpdateDepartmentData;
use App\Domains\Colaboradores\Domain\DTOs\Departments\UpdateDepartmentDTO;
use App\Domains\Colaboradores\Domain\Models\Department;
use App\Domains\Organizacao\Domain\Models\Company;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Altera o nome e a situação de um departamento da própria empresa.
 *
 * Ativar e inativar têm permissão própria, conferida só quando a situação muda.
 */
final readonly class UpdateDepartmentAction
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(UpdateDepartmentData $data, Department $department): void
    {
        AuditLog::transaction(function () use ($data, $department): void {
            // Serializa as escritas da empresa, de modo que a unicidade do nome seja decidida uma de cada vez.
            Company::where('account_id', $department->account_id)
                ->whereKey($department->company_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedDepartment = Department::where('account_id', $department->account_id)
                ->where('company_id', $department->company_id)
                ->lockForUpdate()
                ->findOrFail($department->id);

            $node = AccessNode::company($lockedDepartment->account_id, $lockedDepartment->company_id);

            Gate::authorize('account.departments.update', $node);

            $departmentDTO = new UpdateDepartmentDTO(
                name: $data->name,
                active: $data->active ?? $lockedDepartment->active,
            );

            $lockedDepartment->revise($departmentDTO);

            if ($lockedDepartment->isDirty('active')) {
                if ($lockedDepartment->active) {
                    Gate::authorize('account.departments.activate', $node);
                } else {
                    Gate::authorize('account.departments.deactivate', $node);
                }
            }

            if (! $lockedDepartment->isDirty()) {
                return;
            }

            try {
                $lockedDepartment->saveOrFail();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['name' => 'Já existe um departamento com este nome nesta empresa.']);
            }
        });
    }
}
