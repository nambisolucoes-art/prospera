<?php

namespace App\Domains\Clientes\Application\Actions;

use App\Domains\Clientes\Domain\Models\Customer;
use App\Domains\Pessoas\Domain\Models\Person;
use Illuminate\Support\Facades\Gate;

final class SaveCustomerAction
{
    public function execute(Person $person, bool $selected): void
    {
        $customer = Customer::where('account_id', $person->account_id)
            ->where('person_id', $person->id)
            ->lockForUpdate()
            ->first();

        if (! $customer && ! $selected) {
            return;
        }

        if (! $customer) {
            Gate::authorize('account.customers.create');
            $customer = new Customer(['account_id' => $person->account_id, 'person_id' => $person->id]);
        } elseif ($customer->active !== $selected) {
            if ($selected) {
                Gate::authorize('account.customers.activate');
            } else {
                Gate::authorize('account.customers.deactivate');
            }
        }

        $customer->active = $selected;

        if ($customer->isDirty()) {
            $customer->saveOrFail();
        }
    }
}
