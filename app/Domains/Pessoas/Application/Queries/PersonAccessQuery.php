<?php

namespace App\Domains\Pessoas\Application\Queries;

use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Models\User;

final readonly class PersonAccessQuery
{
    public function __construct(private EffectivePermissions $permissions) {}

    public function canUpdate(User $user, Person $person): bool
    {
        $scope = $this->permissions->nodesFor($user, 'account.people.update');

        if ($scope->tenant) {
            return true;
        }

        if ($scope->isEmpty()) {
            return false;
        }

        if (
            ! $person->customer()->where('active', true)->exists()
            && ! $person->supplier()->where('active', true)->exists()
        ) {
            return true;
        }

        // Antes da E07 a relação comercial não aponta para uma empresa.
        $companies = Company::where('account_id', $person->account_id)->limit(2)->pluck('id');

        if ($companies->count() !== 1) {
            return false;
        }

        $companyId = (int) $companies->first();

        return in_array($companyId, $scope->companies, true)
            || Establishment::where('account_id', $person->account_id)
                ->where('company_id', $companyId)
                ->whereKey($scope->establishments)
                ->exists();
    }
}
