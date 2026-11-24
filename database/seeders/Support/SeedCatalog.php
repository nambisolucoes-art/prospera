<?php

namespace Database\Seeders\Support;

/**
 * Chaves naturais do cenário de desenvolvimento. Os seeders se encontram por elas no banco, nunca por objetos
 * passados entre si: cada módulo pode evoluir sozinho, desde que os anteriores tenham criado o que ele procura.
 */
final class SeedCatalog
{
    /** Senha de todas as identidades sintéticas. Atende também à regra de senha de produção. */
    public const PASSWORD = 'Prospera@2026';

    public const PLATFORM_ADMINISTRATOR = 'admin@prospera.test';

    public const ACCOUNT_ADMINISTRATOR = 'ana.ribeiro@horizonte.test';

    /** Raiz do CNPJ do contratante (a conta). A conta é localizada pelo documento completo da matriz. */
    public const ACCOUNT_ROOT = '42318765';

    public const SUPERMARKET_ROOT = '27184390';

    public const PHARMACY_ROOT = '31975264';

    public const WHOLESALE_ROOT = '18642093';

    /** Empresa individual identificada por CPF: não tem matriz nem filial. */
    public const GROCERY_CPF_BASE = '529982247';

    public const BRUMADO_STORE = 'loja-brumado';

    public const GUANAMBI_STORE = 'loja-guanambi';

    public const DISTRIBUTION_CENTER = 'centro-distribuicao';

    public const SHOPPING_PHARMACY = 'farmacia-shopping';

    public const CANDEIAS_PHARMACY = 'farmacia-candeias';

    public const WHOLESALE_WAREHOUSE = 'deposito-atacado';

    public const COMPANY_MANAGER = 'Gerente de empresa';

    public const STORE_MANAGER = 'Gerente de loja';

    public const CASHIER = 'Operador de caixa';

    public const BUYER = 'Comprador';

    public const CUSTOMER_SERVICE = 'Atendimento ao cliente';

    public const INTERNAL_AUDITOR = 'Auditor interno';

    public const PHARMACY_COORDINATOR = 'Coordenador farmacêutico';
}
