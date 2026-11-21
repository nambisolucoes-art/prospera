<?php

namespace App\Domains\Pessoas\Application\Queries;

use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Domains\Pessoas\Domain\DTOs\PersonEditAuthority;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Models\User;

final readonly class PersonAccessQuery
{
    public function __construct(
        private EffectivePermissions $permissions,
        private PersonCommercialRelationsQuery $relations,
    ) {}

    /**
     * Alvo person: cobrir a empresa C é ter atribuição de conta, da empresa C ou de unidade de C (seção 8.2).
     */
    public function authority(User $user, Person $person): PersonEditAuthority
    {
        $scope = $this->permissions->nodesFor($user, 'account.people.update');

        if ($scope->isEmpty()) {
            return PersonEditAuthority::denied();
        }

        if ($scope->tenant) {
            return new PersonEditAuthority(true, true, true);
        }

        $related = $this->relations->activeCompanyIds($person->account_id, $person->id);

        if ($related === []) {
            return new PersonEditAuthority(true, true, true);
        }

        $covered = $scope->companies;

        if ($scope->establishments !== []) {
            $unitCompanies = Establishment::where('account_id', $person->account_id)
                ->whereKey($scope->establishments)
                ->distinct()
                ->pluck('company_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $covered = [...$covered, ...$unitCompanies];
        }

        $coveredRelated = array_intersect($related, $covered);

        return new PersonEditAuthority(
            allowed: true,
            identification: count($coveredRelated) === count($related),
            contacts: $coveredRelated !== [],
        );
    }
}
