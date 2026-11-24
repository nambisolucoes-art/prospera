<?php

namespace Database\Seeders\Organizacao;

use App\Domains\Organizacao\Application\Actions\CreateCompanyAction;
use App\Domains\Organizacao\Application\Data\SaveCompanyData;
use Database\Seeders\Support\FiscalDocuments;
use Database\Seeders\Support\SeedCatalog;
use Database\Seeders\Support\SeedsAccountScenario;
use Illuminate\Database\Seeder;

/**
 * Empresas além da primeira (criada com a conta). Cada uma nasce com a unidade principal: matriz quando o
 * CNPJ é completo; sem inscrição quando a empresa é individual, identificada por CPF.
 */
final class CompanySeeder extends Seeder
{
    use SeedsAccountScenario;

    public function run(): void
    {
        $this->actAsAccountAdministrator();
        $create = app(CreateCompanyAction::class);
        $accountId = $this->account()->id;

        foreach ($this->companies() as $input) {
            $data = SaveCompanyData::fromInput($input, $accountId, null);

            $create->execute($accountId, $data);
        }

        $this->stopActing();
    }

    /** @return list<array<string, mixed>> */
    private function companies(): array
    {
        return [
            [
                'code' => 'horizonte-farma',
                'name' => 'Horizonte Farma Ltda',
                'trade_name' => 'Horizonte Farma',
                'person_type' => 'company',
                'document' => FiscalDocuments::cnpj(SeedCatalog::PHARMACY_ROOT, '0001'),
                'tax_regime' => 'lucro-presumido',
                'main_cnae' => '4771701',
                'primary' => [
                    'name' => 'Farmácia Centro',
                    'classification' => 'headquarters',
                    'address' => $this->address('Praça Barão do Rio Branco', '45', 'Centro', '45000200'),
                ],
            ],
            [
                'code' => 'horizonte-atacado',
                'name' => 'Horizonte Atacado Distribuidora Ltda',
                'trade_name' => 'Horizonte Atacado',
                'person_type' => 'company',
                'document' => FiscalDocuments::cnpj(SeedCatalog::WHOLESALE_ROOT, '0001'),
                'tax_regime' => 'lucro-real',
                'main_cnae' => '4639701',
                'primary' => [
                    'name' => 'Atacado Matriz',
                    'classification' => 'headquarters',
                    'address' => $this->address('Avenida Juracy Magalhães', '3100', 'Felícia', '45000300'),
                ],
            ],
            [
                'code' => 'mercearia-dona-clara',
                'name' => 'Clara Mendes Oliveira',
                'trade_name' => 'Mercearia Dona Clara',
                'person_type' => 'individual',
                'document' => FiscalDocuments::cpf(SeedCatalog::GROCERY_CPF_BASE),
                'tax_regime' => 'mei',
                'primary' => [
                    'name' => 'Mercearia Dona Clara',
                    'address' => $this->address('Rua Siqueira Campos', '88', 'Recreio', '45000400'),
                ],
            ],
        ];
    }

    /** @return array<string, string> */
    private function address(string $street, string $number, string $neighborhood, string $postalCode): array
    {
        return [
            'postal_code' => $postalCode,
            'street' => $street,
            'number' => $number,
            'neighborhood' => $neighborhood,
            'city' => 'Vitória da Conquista',
            'state' => 'BA',
        ];
    }
}
