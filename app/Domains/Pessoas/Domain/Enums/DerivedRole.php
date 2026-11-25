<?php

declare(strict_types=1);

namespace App\Domains\Pessoas\Domain\Enums;

use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Domain\ValueObjects\AuthorizedNodes;
use App\Models\User;

/**
 * Registro único dos papéis que uma Pessoa deriva das relações por empresa: Cliente, Fornecedor e Colaborador.
 *
 * Cada papel tem uma etiqueta especial (`tags.system_key`) calculada da relação ativa, nunca atribuída à mão.
 * Não substitui `CommercialRole`, que continua restrito aos módulos comerciais.
 */
enum DerivedRole: string
{
    case Employee = 'employee';
    case Customer = 'customer';
    case Supplier = 'supplier';

    public function table(): string
    {
        return match ($this) {
            self::Employee => 'employees',
            self::Customer => 'customers',
            self::Supplier => 'suppliers',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Employee => 'Colaborador',
            self::Customer => 'Cliente',
            self::Supplier => 'Fornecedor',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Employee => '#1abc9c',
            self::Customer => '#23b7e5',
            self::Supplier => '#7266ba',
        };
    }

    /** Empresas em que o usuário visualiza as relações deste papel, o que decide se a etiqueta aparece. */
    public function viewNodes(EffectivePermissions $permissions, User $user): AuthorizedNodes
    {
        $nodes = match ($this) {
            self::Employee => $permissions->nodesFor($user, 'account.employees.view'),
            self::Customer => $permissions->nodesFor($user, 'account.customers.view'),
            self::Supplier => $permissions->nodesFor($user, 'account.suppliers.view'),
        };

        // Cliente e Fornecedor: atribuição de estabelecimento não cobre a relação da empresa. Colaborador: a unidade
        // cobre o vínculo que tem lotação nela (DA2-18), e a consulta aplica esse escopo.
        if ($this === self::Employee) {
            return $nodes;
        }

        return new AuthorizedNodes(tenant: $nodes->tenant, companies: $nodes->companies);
    }
}
