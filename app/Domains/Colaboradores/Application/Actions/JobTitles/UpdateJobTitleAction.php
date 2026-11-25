<?php

declare(strict_types=1);

namespace App\Domains\Colaboradores\Application\Actions\JobTitles;

use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Colaboradores\Application\Data\JobTitles\UpdateJobTitleData;
use App\Domains\Colaboradores\Domain\DTOs\JobTitles\UpdateJobTitleDTO;
use App\Domains\Colaboradores\Domain\Models\JobTitle;
use App\Domains\Organizacao\Domain\Models\Company;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Altera o nome e a situação de uma função da própria empresa.
 *
 * Ativar e inativar têm permissão própria, conferida só quando a situação muda.
 */
final readonly class UpdateJobTitleAction
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(UpdateJobTitleData $data, JobTitle $jobTitle): void
    {
        AuditLog::transaction(function () use ($data, $jobTitle): void {
            // Serializa as escritas da empresa, de modo que a unicidade do nome seja decidida uma de cada vez.
            Company::where('account_id', $jobTitle->account_id)
                ->whereKey($jobTitle->company_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedJobTitle = JobTitle::where('account_id', $jobTitle->account_id)
                ->where('company_id', $jobTitle->company_id)
                ->lockForUpdate()
                ->findOrFail($jobTitle->id);

            $node = AccessNode::company($lockedJobTitle->account_id, $lockedJobTitle->company_id);

            Gate::authorize('account.job-titles.update', $node);

            $jobTitleDTO = new UpdateJobTitleDTO(
                name: $data->name,
                active: $data->active ?? $lockedJobTitle->active,
            );

            $lockedJobTitle->revise($jobTitleDTO);

            if ($lockedJobTitle->isDirty('active')) {
                if ($lockedJobTitle->active) {
                    Gate::authorize('account.job-titles.activate', $node);
                } else {
                    Gate::authorize('account.job-titles.deactivate', $node);
                }
            }

            if (! $lockedJobTitle->isDirty()) {
                return;
            }

            try {
                $lockedJobTitle->saveOrFail();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['name' => 'Já existe uma função com este nome nesta empresa.']);
            }
        });
    }
}
