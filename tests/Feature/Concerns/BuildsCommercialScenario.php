<?php

namespace Tests\Feature\Concerns;

use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Acessos\Domain\Models\RoleAssignment;
use App\Domains\Organizacao\Application\Actions\CreateCompanyAction;
use App\Domains\Organizacao\Application\Actions\SaveEstablishmentAction;
use App\Domains\Organizacao\Application\Data\SaveCompanyData;
use App\Domains\Organizacao\Application\Data\SaveEstablishmentData;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;

/**
 * Cenário comercial da E07: conta, empresas, unidades, cargos e operadores com atribuições por nó.
 */
trait BuildsCommercialScenario
{
    protected function account(string $name = 'Grupo Gartan'): Client
    {
        return Client::create(['name' => $name, 'active' => true]);
    }

    protected function company(Client $account, string $name, string $root): Company
    {
        $data = SaveCompanyData::fromInput([
            'name' => $name,
            'person_type' => 'company',
            'document' => $root,
        ], $account->id, null);

        return app(CreateCompanyAction::class)->execute($account->id, $data);
    }

    protected function unit(Company $company, string $name): Establishment
    {
        $data = SaveEstablishmentData::fromInput(['name' => $name, 'classification' => 'unregistered'], $company, null);

        return app(SaveEstablishmentAction::class)->execute($company, null, $data);
    }

    /** @param list<string> $slugs */
    protected function role(Client $account, string $name, array $slugs): Role
    {
        $role = Role::create(['name' => $name, 'account_id' => $account->id]);
        $role->permissions()->attach(Permission::whereIn('slug', $slugs)->pluck('id'));

        return $role;
    }

    protected function administrator(Client $account): User
    {
        return User::factory()->asAccountAdministrator($account->id)->create();
    }

    /**
     * Operador com as atribuições informadas, sem a atribuição inicial de conta da fábrica.
     *
     * @param  list<array{0: Role, 1: AccessNode}>  $assignments
     */
    protected function operator(Client $account, array $assignments): User
    {
        $user = User::factory()->forAccount($account->id)->create();
        $membership = $this->membership($user);
        $membership->assignments()->delete();

        foreach ($assignments as [$role, $node]) {
            RoleAssignment::grant($membership, $role, $node, null)->saveOrFail();
        }

        return $user;
    }

    /** @return list<string> */
    protected function commercialSlugs(string $module): array
    {
        return [
            'account.'.$module.'.list',
            'account.'.$module.'.view',
            'account.'.$module.'.create',
            'account.'.$module.'.activate',
            'account.'.$module.'.deactivate',
        ];
    }

    /** @return list<string> */
    protected function peopleSlugs(): array
    {
        return ['account.people.view', 'account.people.create', 'account.people.update'];
    }
}
