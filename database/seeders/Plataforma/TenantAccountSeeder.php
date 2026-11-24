<?php

namespace Database\Seeders\Plataforma;

use App\Domains\Plataforma\Application\Actions\SaveAccountAction;
use App\Domains\Plataforma\Application\Data\SaveAccountData;
use App\Domains\Plataforma\Domain\Models\Client;
use Database\Seeders\Support\FiscalDocuments;
use Database\Seeders\Support\SeedCatalog;
use Database\Seeders\Support\SeedsAccountScenario;
use Illuminate\Database\Seeder;

/**
 * A conta (tenant), cadastrada pela plataforma como no painel SaaS: nasce com a primeira empresa, a unidade
 * principal (matriz) e o administrador integral da conta.
 */
final class TenantAccountSeeder extends Seeder
{
    use SeedsAccountScenario;

    public function run(): void
    {
        $this->actAsPlatformAdministrator();
        $save = app(SaveAccountAction::class);

        $data = SaveAccountData::fromInput([
            'name' => 'Grupo Horizonte Comércio Ltda',
            'email' => 'contato@grupohorizonte.test',
            'plan' => 'Empresarial',
            'person_type' => 'company',
            'document' => FiscalDocuments::cnpj(SeedCatalog::ACCOUNT_ROOT, '0001'),
            'active' => true,
            'details' => [
                'trade_name' => 'Grupo Horizonte',
                'phone' => '7734210000',
                'whatsapp' => '77999010000',
                'address' => [
                    'postal_code' => '45000000',
                    'street' => 'Avenida Olívia Flores',
                    'number' => '1200',
                    'neighborhood' => 'Candeias',
                    'city' => 'Vitória da Conquista',
                    'state' => 'BA',
                ],
            ],
            'organization_setup' => 'manual',
            'first_company' => [
                'name' => 'Horizonte Supermercados Ltda',
                'trade_name' => 'Horizonte Supermercados',
                'person_type' => 'company',
                'document' => FiscalDocuments::cnpj(SeedCatalog::SUPERMARKET_ROOT, '0001'),
                'primary' => [
                    'name' => 'Supermercado Centro',
                    'classification' => 'headquarters',
                    'phone' => '7734221000',
                    'email' => 'centro@horizontesupermercados.test',
                    'address' => [
                        'postal_code' => '45000100',
                        'street' => 'Rua Grande',
                        'number' => '350',
                        'neighborhood' => 'Centro',
                        'city' => 'Vitória da Conquista',
                        'state' => 'BA',
                    ],
                ],
            ],
            'administrator' => [
                'name' => 'Ana Ribeiro',
                'email' => SeedCatalog::ACCOUNT_ADMINISTRATOR,
                'mode' => 'manual',
                'password' => SeedCatalog::PASSWORD,
                'password_confirmation' => SeedCatalog::PASSWORD,
            ],
        ]);

        $save->execute(new Client, $data);

        $this->stopActing();
    }
}
