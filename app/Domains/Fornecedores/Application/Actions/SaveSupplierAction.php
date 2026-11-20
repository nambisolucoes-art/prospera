<?php

namespace App\Domains\Fornecedores\Application\Actions;

use App\Domains\Fornecedores\Domain\Models\Supplier;
use App\Domains\Pessoas\Domain\Models\Person;
use Illuminate\Support\Facades\Gate;

final class SaveSupplierAction
{
    public function execute(Person $person, bool $selected): void
    {
        $supplier = Supplier::where('account_id', $person->account_id)->where('person_id', $person->id)->lockForUpdate()->first();
        if (! $supplier && ! $selected) {
            return;
        }
        if (! $supplier) {
            Gate::authorize('account.suppliers.create');
            $supplier = new Supplier(['account_id' => $person->account_id, 'person_id' => $person->id]);
        } elseif ($supplier->active !== $selected) {
            if ($selected) {
                Gate::authorize('account.suppliers.activate');
            } else {
                Gate::authorize('account.suppliers.deactivate');
            }
        }
        $supplier->active = $selected;
        if ($supplier->isDirty()) {
            $supplier->saveOrFail();
        }
    }
}
