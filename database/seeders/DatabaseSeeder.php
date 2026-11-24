<?php

namespace Database\Seeders;

use Database\Seeders\Acessos\PermissionCatalogSeeder;
use Database\Seeders\Acessos\RoleSeeder;
use Database\Seeders\Clientes\CustomerSeeder;
use Database\Seeders\Etiquetas\CommercialTagSeeder;
use Database\Seeders\Fornecedores\SupplierSeeder;
use Database\Seeders\Organizacao\CompanySeeder;
use Database\Seeders\Organizacao\EstablishmentSeeder;
use Database\Seeders\Plataforma\PlatformAdministratorSeeder;
use Database\Seeders\Plataforma\TenantAccountSeeder;
use Database\Seeders\Support\SeedCatalog;
use Database\Seeders\Support\SeedSummary;
use Database\Seeders\Usuarios\AccountUserSeeder;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Cenário completo de desenvolvimento, do catálogo de permissões às relações comerciais. Cada módulo é um
 * seeder próprio; a ordem abaixo só respeita o que cada um precisa encontrar no banco. Use make seed.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Os dados sintéticos de desenvolvimento não podem ser gerados em produção.');
        }

        $this->call([
            PermissionCatalogSeeder::class,
            PlatformAdministratorSeeder::class,
            TenantAccountSeeder::class,
            CompanySeeder::class,
            EstablishmentSeeder::class,
            CommercialTagSeeder::class,
            RoleSeeder::class,
            AccountUserSeeder::class,
            CustomerSeeder::class,
            SupplierSeeder::class,
        ]);

        $this->printTestUsers();
    }

    private function printTestUsers(): void
    {
        if ($this->command === null) {
            return;
        }

        $this->command->newLine();
        $this->command->info('Usuários de teste. Senha de todos: '.SeedCatalog::PASSWORD);
        $this->command->line('Entrada: '.rtrim((string) config('app.url'), '/').'/login');
        $this->command->table(['E-mail', 'Atuação'], SeedSummary::users());
    }
}
