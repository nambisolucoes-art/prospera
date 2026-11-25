<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Actions\JobTitles;

use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Colaboradores\Application\Data\JobTitles\CreateJobTitleData;
use App\Domains\Colaboradores\Domain\DTOs\JobTitles\CreateJobTitleDTO;
use App\Domains\Colaboradores\Domain\Models\JobTitle;
use App\Domains\Organizacao\Domain\DTOs\NavigableCompany;
use App\Domains\Organizacao\Domain\Models\Company;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Cadastra uma função em cada empresa escolhida, numa única transação.
 *
 * Cada empresa é autorizada de forma independente. Cadastrar já inativo exige também a permissão de inativar.
 */
final readonly class CreateJobTitleAction
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(CreateJobTitleData $data, int $accountId): void
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
                Gate::authorize('account.job-titles.create', $node);

                if (! $data->active) {
                    Gate::authorize('account.job-titles.deactivate', $node);
                }

                $jobTitleDTO = new CreateJobTitleDTO(
                    name: $data->name,
                    active: $data->active,
                );

                $jobTitle = JobTitle::register($jobTitleDTO, $accountId, $companyId);

                try {
                    $jobTitle->saveOrFail();
                } catch (UniqueConstraintViolationException) {
                    throw ValidationException::withMessages(['name' => 'Já existe uma função com este nome nesta empresa.']);
                }
            }
        });
    }
}
