<?php

declare(strict_types=1);

namespace App\Domains\Fornecedores\Application\Actions;

use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Fornecedores\Domain\Models\Supplier;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Pessoas\Domain\Models\Person;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Cria, ativa ou inativa a relação de fornecedor da pessoa com uma empresa (E07).
 *
 * O chamador trava a pessoa e confere que a empresa pertence à conta dela. Relações de outras empresas
 * e a situação da pessoa nunca são alteradas aqui.
 */
final class SaveSupplierAction
{
    public function execute(Person $person, Company $company, bool $selected): void
    {
        $node = AccessNode::company($person->account_id, $company->id);

        $lockedSupplier = Supplier::where('account_id', $person->account_id)
            ->where('company_id', $company->id)
            ->where('person_id', $person->id)
            ->lockForUpdate()
            ->first();

        if ($lockedSupplier === null && ! $selected) {
            return;
        }

        if ($lockedSupplier === null) {
            Gate::authorize('account.suppliers.create', $node);
            $lockedSupplier = Supplier::register($person->account_id, $company->id, $person->id);
        } elseif ($lockedSupplier->active !== $selected) {
            if ($selected) {
                Gate::authorize('account.suppliers.activate', $node);
                $lockedSupplier->activate();
            } else {
                Gate::authorize('account.suppliers.deactivate', $node);
                $lockedSupplier->deactivate();
            }
        }

        if (! $lockedSupplier->isDirty()) {
            return;
        }

        if (! $company->active) {
            throw ValidationException::withMessages([
                '_form' => 'A empresa '.($company->trade_name ?: $company->name).' está inativa. '
                    .'Reative a empresa antes de alterar suas relações comerciais.',
            ]);
        }

        $lockedSupplier->saveOrFail();
    }
}
