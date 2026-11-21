<?php

namespace App\Domains\Pessoas\Domain\Enums;

use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Domain\DTOs\AuthorizedNodes;
use App\Domains\Clientes\Domain\Models\Customer;
use App\Domains\Fornecedores\Domain\Models\Supplier;
use App\Models\User;
use InvalidArgumentException;

/**
 * Papel de uma relação comercial entre uma pessoa e uma empresa (E07).
 *
 * Cliente e Fornecedor são relações independentes, cada uma com a própria linha por empresa.
 */
enum CommercialRole: string
{
    case Customer = 'customer';
    case Supplier = 'supplier';

    /** @return class-string<Customer|Supplier> */
    public function model(): string
    {
        return match ($this) {
            self::Customer => Customer::class,
            self::Supplier => Supplier::class,
        };
    }

    public function table(): string
    {
        return match ($this) {
            self::Customer => 'customers',
            self::Supplier => 'suppliers',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Cliente',
            self::Supplier => 'Fornecedor',
        };
    }

    public function pluralLabel(): string
    {
        return match ($this) {
            self::Customer => 'Clientes',
            self::Supplier => 'Fornecedores',
        };
    }

    public function segment(): string
    {
        return match ($this) {
            self::Customer => 'clientes',
            self::Supplier => 'fornecedores',
        };
    }

    public function tagColor(): string
    {
        return match ($this) {
            self::Customer => '#23b7e5',
            self::Supplier => '#7266ba',
        };
    }

    /**
     * Empresas em que o usuário pode executar a ação sobre relações deste papel.
     *
     * O alvo das permissões comerciais é a empresa (seção 8.2.1): uma atribuição de estabelecimento
     * não cobre a relação, por isso o descritor só guarda a conta inteira ou empresas.
     */
    public function companies(EffectivePermissions $permissions, User $user, string $action): AuthorizedNodes
    {
        $nodes = match ($this) {
            self::Customer => match ($action) {
                'list' => $permissions->nodesFor($user, 'account.customers.list'),
                'view' => $permissions->nodesFor($user, 'account.customers.view'),
                'create' => $permissions->nodesFor($user, 'account.customers.create'),
                'update' => $permissions->nodesFor($user, 'account.customers.update'),
                'activate' => $permissions->nodesFor($user, 'account.customers.activate'),
                'deactivate' => $permissions->nodesFor($user, 'account.customers.deactivate'),
                default => throw new InvalidArgumentException('Ação comercial desconhecida: '.$action),
            },
            self::Supplier => match ($action) {
                'list' => $permissions->nodesFor($user, 'account.suppliers.list'),
                'view' => $permissions->nodesFor($user, 'account.suppliers.view'),
                'create' => $permissions->nodesFor($user, 'account.suppliers.create'),
                'update' => $permissions->nodesFor($user, 'account.suppliers.update'),
                'activate' => $permissions->nodesFor($user, 'account.suppliers.activate'),
                'deactivate' => $permissions->nodesFor($user, 'account.suppliers.deactivate'),
                default => throw new InvalidArgumentException('Ação comercial desconhecida: '.$action),
            },
        };

        return new AuthorizedNodes(tenant: $nodes->tenant, companies: $nodes->companies);
    }

    public function allows(EffectivePermissions $permissions, User $user, string $action, int $companyId): bool
    {
        $companies = $this->companies($permissions, $user, $action);

        return $companies->tenant || in_array($companyId, $companies->companies, true);
    }
}
