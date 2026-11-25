<?php

declare(strict_types=1);

namespace App\Domains\Clientes\Application\Actions;

use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Clientes\Domain\Models\Customer;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Pessoas\Domain\Models\Person;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Cria, ativa ou inativa a relação de cliente da pessoa com uma empresa (E07).
 *
 * O chamador trava a pessoa e confere que a empresa pertence à conta dela. Relações de outras empresas
 * e a situação da pessoa nunca são alteradas aqui.
 */
final readonly class ChangeCustomerRelationAction
{
    public function execute(Person $person, Company $company, bool $selected): void
    {
        $node = AccessNode::company($person->account_id, $company->id);

        $lockedCustomer = Customer::where('account_id', $person->account_id)
            ->where('company_id', $company->id)
            ->where('person_id', $person->id)
            ->lockForUpdate()
            ->first();

        if ($lockedCustomer === null && ! $selected) {
            return;
        }

        if ($lockedCustomer === null) {
            Gate::authorize('account.customers.create', $node);
            $lockedCustomer = Customer::register($person->account_id, $company->id, $person->id);
        } elseif ($lockedCustomer->active !== $selected) {
            if ($selected) {
                Gate::authorize('account.customers.activate', $node);
                $lockedCustomer->activate();
            } else {
                Gate::authorize('account.customers.deactivate', $node);
                $lockedCustomer->deactivate();
            }
        }

        if (! $lockedCustomer->isDirty()) {
            return;
        }

        if (! $company->active) {
            throw ValidationException::withMessages([
                '_form' => 'A empresa '.($company->trade_name ?: $company->name).' está inativa. '
                    .'Reative a empresa antes de alterar suas relações comerciais.',
            ]);
        }

        $lockedCustomer->saveOrFail();
    }
}
