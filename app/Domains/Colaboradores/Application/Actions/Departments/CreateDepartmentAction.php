<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Actions\Departments;

use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Colaboradores\Application\Data\Departments\CreateDepartmentData;
use App\Domains\Colaboradores\Domain\DTOs\Departments\CreateDepartmentDTO;
use App\Domains\Colaboradores\Domain\Models\Department;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\ValueObjects\NavigableCompany;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Cadastra um departamento em cada empresa escolhida, numa única transação.
 *
 * Cada empresa é autorizada de forma independente. Cadastrar já inativo exige também a permissão de inativar.
 */
final readonly class CreateDepartmentAction
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(CreateDepartmentData $data, int $accountId): void
    {
        $companyIds = array_map(fn (NavigableCompany $company) => $company->id, $data->companies);
        sort($companyIds);

        AuditLog::transaction(function () use ($data, $accountId, $companyIds): void {
            foreach ($companyIds as $companyId) {
                // Serializa as escritas da empresa, de modo que a unicidade do nome seja decidida uma de cada vez.
                Company::where('account_id', $accountId)
                    ->whereKey($companyId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $node = AccessNode::company($accountId, $companyId);

                // Cada slug fica literal na chamada para que permissions:sync o descubra.
                Gate::authorize('account.departments.create', $node);

                if (! $data->active) {
                    Gate::authorize('account.departments.deactivate', $node);
                }

                $departmentDTO = new CreateDepartmentDTO(
                    name: $data->name,
                    active: $data->active,
                );

                $department = Department::register($departmentDTO, $accountId, $companyId);

                try {
                    $department->saveOrFail();
                } catch (UniqueConstraintViolationException) {
                    throw ValidationException::withMessages(['name' => 'Já existe um departamento com este nome nesta empresa.']);
                }
            }
        });
    }
}
