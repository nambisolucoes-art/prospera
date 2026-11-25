<?php

namespace App\Domains\Colaboradores\Application\Actions;

use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Colaboradores\Application\Data\SaveJobTitleData;
use App\Domains\Colaboradores\Domain\DTOs\CatalogItemDTO;
use App\Domains\Colaboradores\Domain\Models\JobTitle;
use App\Domains\Organizacao\Domain\Models\Company;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Cadastra uma função em cada empresa escolhida ou altera a de uma empresa, inclusive a situação.
 *
 * Cada empresa é autorizada de forma independente: a permissão de criar ou editar, e a de ativar ou inativar
 * quando a situação muda, são conferidas sobre o nó da empresa. Tudo grava numa única transação.
 */
final class SaveJobTitleAction
{
    /** @param list<int> $companyIds */
    public function execute(int $accountId, array $companyIds, ?JobTitle $jobTitle, SaveJobTitleData $data): void
    {
        sort($companyIds);

        AuditLog::transaction(function () use ($accountId, $companyIds, $jobTitle, $data): void {
            foreach ($companyIds as $companyId) {
                // Serializa as escritas da empresa, de modo que a unicidade do nome seja decidida uma de cada vez.
                Company::where('account_id', $accountId)
                    ->whereKey($companyId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedJobTitle = $jobTitle === null
                    ? null
                    : JobTitle::where('account_id', $accountId)
                        ->where('company_id', $companyId)
                        ->lockForUpdate()
                        ->findOrFail($jobTitle->id);

                $currentActive = $jobTitle === null ? true : $lockedJobTitle->active;

                $itemDTO = new CatalogItemDTO(
                    name: $data->values['name'],
                    active: (bool) ($data->values['active'] ?? $currentActive),
                );

                $node = AccessNode::company($accountId, $companyId);

                // Cada slug fica literal na chamada para que permissions:sync o descubra.
                if ($lockedJobTitle === null) {
                    Gate::authorize('account.job-titles.create', $node);
                    $lockedJobTitle = JobTitle::register($itemDTO, $accountId, $companyId);
                } else {
                    Gate::authorize('account.job-titles.update', $node);
                    $lockedJobTitle->revise($itemDTO);
                }

                if (
                    ($lockedJobTitle->exists && $lockedJobTitle->isDirty('active'))
                    || (! $lockedJobTitle->exists && ! $lockedJobTitle->active)
                ) {
                    if ($lockedJobTitle->active) {
                        Gate::authorize('account.job-titles.activate', $node);
                    } else {
                        Gate::authorize('account.job-titles.deactivate', $node);
                    }
                }

                if (! $lockedJobTitle->isDirty()) {
                    continue;
                }

                try {
                    $lockedJobTitle->saveOrFail();
                } catch (UniqueConstraintViolationException) {
                    throw ValidationException::withMessages([
                        'name' => 'Já existe uma função com este nome nesta empresa.',
                    ]);
                }
            }
        });
    }
}
