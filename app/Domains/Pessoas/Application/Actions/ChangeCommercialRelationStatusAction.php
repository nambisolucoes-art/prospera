<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Application\Actions;

use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Pessoas\Application\Queries\CommercialRelations\FindCommercialRelationIdQuery;
use App\Domains\Pessoas\Domain\Enums\CommercialRole;
use App\Models\User;
use App\Support\Exceptions\OperationNotAllowed;
use App\Support\Exceptions\ResourceNotAvailable;

/** Ativa ou inativa a relação comercial de uma pessoa com a empresa do contexto. */
final readonly class ChangeCommercialRelationStatusAction
{
    public function __construct(
        private FindCommercialRelationIdQuery $findRelationId,
        private SetCommercialRelationAction $set,
        private EffectivePermissions $permissions,
    ) {}

    /**
     * @throws OperationNotAllowed
     * @throws ResourceNotAvailable
     */
    public function execute(CommercialRole $role, Company $company, int $relationId, bool $active, User $operator): void
    {
        $action = $active ? 'activate' : 'deactivate';

        if (! $role->allows($this->permissions, $operator, $action, $company->id)) {
            throw new OperationNotAllowed;
        }

        // Relação de outra empresa ou de outra conta responde 404, como um ID inexistente.
        $personId = $this->findRelationId->execute($role, $company->account_id, $company->id, $relationId);

        if ($personId === null) {
            throw new ResourceNotAvailable;
        }

        $this->set->execute($role, $company, $personId, active: $active, link: false);
    }
}
