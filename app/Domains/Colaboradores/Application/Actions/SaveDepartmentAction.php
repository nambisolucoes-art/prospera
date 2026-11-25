<?php

namespace App\Domains\Colaboradores\Application\Actions;

use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Colaboradores\Application\Data\SaveDepartmentData;
use App\Domains\Colaboradores\Domain\DTOs\CatalogItemDTO;
use App\Domains\Colaboradores\Domain\Models\Department;
use App\Domains\Organizacao\Domain\Models\Company;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Cadastra um departamento em cada empresa escolhida ou altera o de uma empresa, inclusive a situação.
 *
 * Cada empresa é autorizada de forma independente: a permissão de criar ou editar, e a de ativar ou inativar
 * quando a situação muda, são conferidas sobre o nó da empresa. Tudo grava numa única transação.
 */
final class SaveDepartmentAction
{
    /** @param list<int> $companyIds */
    public function execute(int $accountId, array $companyIds, ?Department $department, SaveDepartmentData $data): void
    {
        sort($companyIds);

        AuditLog::transaction(function () use ($accountId, $companyIds, $department, $data): void {
            foreach ($companyIds as $companyId) {
                // Serializa as escritas da empresa, de modo que a unicidade do nome seja decidida uma de cada vez.
                Company::where('account_id', $accountId)
                    ->whereKey($companyId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedDepartment = $department === null
                    ? null
                    : Department::where('account_id', $accountId)
                        ->where('company_id', $companyId)
                        ->lockForUpdate()
                        ->findOrFail($department->id);

                $currentActive = $department === null ? true : $lockedDepartment->active;

                $itemDTO = new CatalogItemDTO(
                    name: $data->values['name'],
                    active: (bool) ($data->values['active'] ?? $currentActive),
                );

                $node = AccessNode::company($accountId, $companyId);

                // Cada slug fica literal na chamada para que permissions:sync o descubra.
                if ($lockedDepartment === null) {
                    Gate::authorize('account.departments.create', $node);
                    $lockedDepartment = Department::register($itemDTO, $accountId, $companyId);
                } else {
                    Gate::authorize('account.departments.update', $node);
                    $lockedDepartment->revise($itemDTO);
                }

                if (
                    ($lockedDepartment->exists && $lockedDepartment->isDirty('active'))
                    || (! $lockedDepartment->exists && ! $lockedDepartment->active)
                ) {
                    if ($lockedDepartment->active) {
                        Gate::authorize('account.departments.activate', $node);
                    } else {
                        Gate::authorize('account.departments.deactivate', $node);
                    }
                }

                if (! $lockedDepartment->isDirty()) {
                    continue;
                }

                try {
                    $lockedDepartment->saveOrFail();
                } catch (UniqueConstraintViolationException) {
                    throw ValidationException::withMessages([
                        'name' => 'Já existe um departamento com este nome nesta empresa.',
                    ]);
                }
            }
        });
    }
}
