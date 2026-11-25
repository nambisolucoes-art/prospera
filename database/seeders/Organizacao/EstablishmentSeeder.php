<?php

namespace Database\Seeders\Organizacao;

use App\Domains\Organizacao\Application\Actions\Establishments\CreateEstablishmentAction;
use App\Domains\Organizacao\Application\Data\Establishments\CreateEstablishmentData;
use Database\Seeders\Support\FiscalDocuments;
use Database\Seeders\Support\SeedCatalog;
use Database\Seeders\Support\SeedsAccountScenario;
use Illuminate\Database\Seeder;

/** Filiais com CNPJ da mesma raiz da empresa e unidades sem inscrição própria. */
final class EstablishmentSeeder extends Seeder
{
    use SeedsAccountScenario;

    public function run(): void
    {
        $this->actAsAccountAdministrator();
        $save = app(CreateEstablishmentAction::class);

        foreach ($this->establishments() as [$root, $input]) {
            $company = $this->company($root);
            $data = CreateEstablishmentData::fromInput($input, $company);

            $save->execute($data, $company);
        }

        $this->stopActing();
    }

    /** @return list<array{0: string, 1: array<string, mixed>}> */
    private function establishments(): array
    {
        return [
            [SeedCatalog::SUPERMARKET_ROOT, [
                'code' => SeedCatalog::BRUMADO_STORE,
                'name' => 'Loja Brumado',
                'classification' => 'branch',
                'tax_document' => FiscalDocuments::cnpj(SeedCatalog::SUPERMARKET_ROOT, '0002'),
                'phone' => '7734412000',
                'email' => 'brumado@horizontesupermercados.test',
                'address' => $this->address('Avenida João Paulo I', '780', 'Centro', 'Brumado', '46100000'),
            ]],
            [SeedCatalog::SUPERMARKET_ROOT, [
                'code' => SeedCatalog::GUANAMBI_STORE,
                'name' => 'Loja Guanambi',
                'classification' => 'branch',
                'tax_document' => FiscalDocuments::cnpj(SeedCatalog::SUPERMARKET_ROOT, '0003'),
                'phone' => '7734513000',
                'address' => $this->address('Rua Coronel Martins', '102', 'Centro', 'Guanambi', '46430000'),
            ]],
            [SeedCatalog::SUPERMARKET_ROOT, [
                'code' => SeedCatalog::DISTRIBUTION_CENTER,
                'name' => 'Centro de Distribuição',
                'classification' => 'unregistered',
                'address' => $this->address('Rodovia BR 116', 'S/N', 'Distrito Industrial', null, '45000500'),
            ]],
            [SeedCatalog::PHARMACY_ROOT, [
                'code' => SeedCatalog::SHOPPING_PHARMACY,
                'name' => 'Farmácia Shopping',
                'classification' => 'branch',
                'tax_document' => FiscalDocuments::cnpj(SeedCatalog::PHARMACY_ROOT, '0002'),
                'phone' => '7734245000',
                'address' => $this->address('Avenida Juracy Magalhães', '3340', 'Felícia', null, '45000600'),
            ]],
            [SeedCatalog::PHARMACY_ROOT, [
                'code' => SeedCatalog::CANDEIAS_PHARMACY,
                'name' => 'Farmácia Candeias',
                'classification' => 'branch',
                'tax_document' => FiscalDocuments::cnpj(SeedCatalog::PHARMACY_ROOT, '0003'),
                'address' => $this->address('Rua Ernesto Dantas', '410', 'Candeias', null, '45000700'),
            ]],
            [SeedCatalog::WHOLESALE_ROOT, [
                'code' => SeedCatalog::WHOLESALE_WAREHOUSE,
                'name' => 'Depósito Anexo',
                'classification' => 'unregistered',
                'address' => $this->address('Rua do Comércio', '15', 'Jurema', null, '45000800'),
            ]],
        ];
    }

    /** @return array<string, string> */
    private function address(
        string $street,
        string $number,
        string $neighborhood,
        ?string $city,
        string $postalCode,
    ): array {
        return [
            'postal_code' => $postalCode,
            'street' => $street,
            'number' => $number,
            'neighborhood' => $neighborhood,
            'city' => $city ?? 'Vitória da Conquista',
            'state' => 'BA',
        ];
    }
}
