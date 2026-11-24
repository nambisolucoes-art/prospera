<?php

namespace Database\Seeders\Fornecedores;

use App\Domains\Pessoas\Application\Actions\RegisterCommercialRelationAction;
use App\Domains\Pessoas\Application\Data\CommercialTagsData;
use App\Domains\Pessoas\Application\Data\SavePersonData;
use App\Domains\Pessoas\Domain\Enums\CommercialRole;
use App\Domains\Pessoas\Domain\Models\Person;
use Database\Seeders\Clientes\CustomerSeeder;
use Database\Seeders\Support\FiscalDocuments;
use Database\Seeders\Support\SeedCatalog;
use Database\Seeders\Support\SeedsAccountScenario;
use Illuminate\Database\Seeder;

/**
 * Fornecedores por empresa, com a mesma regra de Pessoas dos clientes. Uma Pessoa que já é cliente de uma
 * empresa vira fornecedora de outra pelo reaproveitamento do cadastro central, sem duplicá-lo.
 */
final class SupplierSeeder extends Seeder
{
    use SeedsAccountScenario;

    public function run(): void
    {
        $account = $this->account();

        foreach ($this->suppliers() as [$company, $additionalCompanies, $tags, $person]) {
            $this->actAsAccountAdministrator();

            $additionalCodes = array_map(
                fn (string $key): string => $this->companyByKey($key)->code,
                $additionalCompanies,
            );

            app(RegisterCommercialRelationAction::class)->execute(
                CommercialRole::Supplier,
                $account,
                $this->companyByKey($company),
                SavePersonData::fromInput($person, $account->id),
                CommercialTagsData::fromInput($this->tagIds('suppliers', $tags)),
                additionalCompanies: $additionalCodes,
            );
        }

        $this->registerCustomerAsSupplier();

        $this->stopActing();
    }

    /** O restaurante cliente do supermercado fornece refeições prontas ao atacado. */
    private function registerCustomerAsSupplier(): void
    {
        $this->actAsAccountAdministrator();
        $account = $this->account();
        $document = FiscalDocuments::cnpj(CustomerSeeder::SHARED_PERSON_ROOT, '0001');

        $person = Person::where('account_id', $account->id)
            ->where('document', $document)
            ->firstOrFail();

        app(RegisterCommercialRelationAction::class)->execute(
            CommercialRole::Supplier,
            $account,
            $this->companyByKey(SeedCatalog::WHOLESALE_ROOT),
            $person->id,
            CommercialTagsData::fromInput($this->tagIds('suppliers', ['Entrega rápida'])),
        );
    }

    /** @return list<array{0: string, 1: list<string>, 2: list<string>, 3: array<string, mixed>}> */
    private function suppliers(): array
    {
        $supermarket = SeedCatalog::SUPERMARKET_ROOT;
        $pharmacy = SeedCatalog::PHARMACY_ROOT;
        $wholesale = SeedCatalog::WHOLESALE_ROOT;

        return [
            [$supermarket, [$wholesale], ['Fornecedor homologado', 'Crítico para o estoque'], $this->organization(
                'Laticínios Vale Verde Ltda', 'Laticínios Vale Verde', '14725836', 'vendas@valeverde.test',
            )],
            [$supermarket, [], ['Entrega rápida'], $this->organization(
                'Hortifrúti Serra Fresca Ltda', 'Serra Fresca', '25836947', 'pedidos@serrafresca.test',
            )],
            [$supermarket, [$wholesale], ['Fornecedor homologado'], $this->organization(
                'Distribuidora Nordeste Alimentos S.A.', 'Nordeste Alimentos', '36947158',
                'comercial@nordestealimentos.test',
            )],
            [$supermarket, [], ['Importado'], $this->organization(
                'Importadora Atlântico Ltda', 'Atlântico Importados', '47158269', 'contato@atlantico.test',
            )],
            [$pharmacy, [], ['Fornecedor homologado', 'Crítico para o estoque'], $this->organization(
                'Farmadistribuidora Bahia Ltda', 'Farmadis Bahia', '58269371', 'pedidos@farmadis.test',
            )],
            [$pharmacy, [], ['Entrega rápida'], $this->organization(
                'Laboratório Vida Natural Ltda', 'Vida Natural', '69371482', 'comercial@vidanatural.test',
            )],
            [$wholesale, [], ['Fornecedor homologado'], $this->organization(
                'Embalagens Sertão Ltda', 'Embalagens Sertão', '71482593', 'vendas@embalagenssertao.test',
            )],
            [SeedCatalog::GROCERY_CPF_BASE, [], [], [
                'person_type' => 'individual',
                'name' => 'Antônio Ferreira Gomes',
                'trade_name' => 'Ovos do Antônio',
                'document' => FiscalDocuments::cpf('890123457'),
                'phone' => '77988220001',
                'whatsapp' => '77988220001',
                'address' => $this->address('Zona Rural'),
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function organization(string $name, string $tradeName, string $root, string $email): array
    {
        return [
            'person_type' => 'company',
            'name' => $name,
            'trade_name' => $tradeName,
            'document' => FiscalDocuments::cnpj($root, '0001'),
            'email' => $email,
            'phone' => '7734'.substr($root, 0, 6),
            'contact_name' => 'Representante comercial',
            'address' => $this->address('Distrito Industrial'),
        ];
    }

    /** @return array<string, string> */
    private function address(string $neighborhood): array
    {
        return [
            'postal_code' => '45001000',
            'street' => 'Avenida Industrial',
            'number' => (string) (200 + strlen($neighborhood) * 11),
            'neighborhood' => $neighborhood,
            'city' => 'Vitória da Conquista',
            'state' => 'BA',
        ];
    }
}
