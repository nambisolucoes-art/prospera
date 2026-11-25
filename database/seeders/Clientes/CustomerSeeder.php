<?php

namespace Database\Seeders\Clientes;

use App\Domains\Pessoas\Application\Actions\RegisterCommercialRelationAction;
use App\Domains\Pessoas\Application\Data\CommercialRelations\CommercialPersonChangesData;
use App\Domains\Pessoas\Application\Data\CommercialRelations\CommercialTagsData;
use App\Domains\Pessoas\Application\Data\CommercialRelations\RegisterCommercialRelationData;
use App\Domains\Pessoas\Application\Data\People\CreatePersonData;
use App\Domains\Pessoas\Domain\Enums\CommercialRole;
use Database\Seeders\Support\FiscalDocuments;
use Database\Seeders\Support\SeedCatalog;
use Database\Seeders\Support\SeedsAccountScenario;
use Illuminate\Database\Seeder;

/**
 * Clientes por empresa. Os dados centrais ficam na Pessoa da conta; a relação comercial guarda só o vínculo
 * com a empresa e as etiquetas. Um mesmo cliente pode atender a mais de uma empresa sem duplicar a Pessoa.
 */
final class CustomerSeeder extends Seeder
{
    use SeedsAccountScenario;

    /** Cliente que também é fornecedor no SupplierSeeder: a Pessoa é reaproveitada pelo documento. */
    public const SHARED_PERSON_ROOT = '50417293';

    public function run(): void
    {
        $account = $this->account();

        foreach ($this->customers() as [$company, $additionalCompanies, $tags, $person]) {
            $this->actAsAccountAdministrator();

            $additionalCodes = array_map(
                fn (string $key): string => $this->companyByKey($key)->code,
                $additionalCompanies,
            );

            app(RegisterCommercialRelationAction::class)->execute(
                new RegisterCommercialRelationData(
                    role: CommercialRole::Customer,
                    person: CreatePersonData::fromInput($person, $account->id),
                    tags: CommercialTagsData::fromInput($this->tagIds('customers', $tags)),
                    centralChanges: CommercialPersonChangesData::fromInput([], allowIdentificationChange: false),
                    additionalCompanies: $additionalCodes,
                ),
                $account,
                $this->companyByKey($company),
            );
        }

        $this->stopActing();
    }

    /** @return list<array{0: string, 1: list<string>, 2: list<string>, 3: array<string, mixed>}> */
    private function customers(): array
    {
        $supermarket = SeedCatalog::SUPERMARKET_ROOT;
        $pharmacy = SeedCatalog::PHARMACY_ROOT;
        $wholesale = SeedCatalog::WHOLESALE_ROOT;
        $grocery = SeedCatalog::GROCERY_CPF_BASE;

        return [
            [$supermarket, [$pharmacy], ['Cliente VIP', 'Fidelidade'], $this->individual(
                'Mariana Costa Lopes', '123456780', 'mariana.lopes@cliente.test', '77988110001', 'Candeias',
            )],
            [$supermarket, [], ['Fidelidade'], $this->individual(
                'Rafael Santos Pinheiro', '234567891', 'rafael.pinheiro@cliente.test', '77988110002', 'Centro',
            )],
            [$supermarket, [], [], $this->individual(
                'Luciana Ferreira Dias', '345678902', null, '77988110003', 'Recreio',
            )],
            [$supermarket, [$wholesale], ['Compra no atacado'], $this->organization(
                'Restaurante Sabor da Serra Ltda', 'Sabor da Serra', self::SHARED_PERSON_ROOT,
                'compras@sabordaserra.test', '7734260001',
            )],
            [$supermarket, [], ['Inadimplente'], $this->individual(
                'Paulo Henrique Batista', '456789013', 'paulo.batista@cliente.test', '77988110005', 'Brasil',
            )],
            [$pharmacy, [], ['Cliente VIP'], $this->individual(
                'Teresa Almeida Prado', '567890124', 'teresa.prado@cliente.test', '77988110006', 'Felícia',
            )],
            [$pharmacy, [], [], $this->individual(
                'Otávio Ramos Neto', '678901235', null, '77988110007', 'Jurema',
            )],
            [$pharmacy, [], ['Fidelidade'], $this->organization(
                'Clínica Bem Viver Ltda', 'Clínica Bem Viver', '61829374',
                'administrativo@bemviver.test', '7734270002',
            )],
            [$wholesale, [], ['Compra no atacado', 'Cliente VIP'], $this->organization(
                'Mercadinho Boa Vista Ltda', 'Mercadinho Boa Vista', '72930485',
                'contato@mercadinhoboavista.test', '7734280003',
            )],
            [$wholesale, [], ['Compra no atacado'], $this->organization(
                'Padaria Pão de Ouro Ltda', 'Padaria Pão de Ouro', '83041596',
                'pedidos@paodeouro.test', '7734280004',
            )],
            [$grocery, [], [], $this->individual(
                'Sebastião Moura', '789012346', null, '77988110011', 'Recreio',
            )],
        ];
    }

    /** @return array<string, mixed> */
    private function individual(
        string $name,
        string $cpfBase,
        ?string $email,
        string $phone,
        string $neighborhood,
    ): array {
        return [
            'person_type' => 'individual',
            'name' => $name,
            'document' => FiscalDocuments::cpf($cpfBase),
            'email' => $email,
            'phone' => $phone,
            'whatsapp' => $phone,
            'address' => $this->address($neighborhood),
        ];
    }

    /** @return array<string, mixed> */
    private function organization(string $name, string $tradeName, string $root, string $email, string $phone): array
    {
        return [
            'person_type' => 'company',
            'name' => $name,
            'trade_name' => $tradeName,
            'document' => FiscalDocuments::cnpj($root, '0001'),
            'email' => $email,
            'phone' => $phone,
            'contact_name' => 'Setor de compras',
            'address' => $this->address('Centro'),
        ];
    }

    /** @return array<string, string> */
    private function address(string $neighborhood): array
    {
        return [
            'postal_code' => '45000900',
            'street' => 'Rua das Flores',
            'number' => (string) (100 + strlen($neighborhood) * 7),
            'neighborhood' => $neighborhood,
            'city' => 'Vitória da Conquista',
            'state' => 'BA',
        ];
    }
}
