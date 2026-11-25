<?php

namespace Database\Seeders\Usuarios;

use App\Domains\Acessos\Application\Actions\ChangeRoleAssignmentsAction;
use App\Domains\Acessos\Application\Actions\SetMembershipStatusAction;
use App\Domains\Acessos\Application\Data\ChangeRoleAssignmentsData;
use App\Domains\Usuarios\Application\Actions\CreateUserAction;
use App\Domains\Usuarios\Application\Data\CreateUserData;
use App\Domains\Usuarios\Domain\Enums\UserRole;
use Database\Seeders\Support\SeedCatalog;
use Database\Seeders\Support\SeedsAccountScenario;
use Illuminate\Database\Seeder;

/**
 * Usuários de teste da conta, criados pelo administrador como na tela de Usuários. A primeira concessão
 * acompanha o cadastro; as demais passam pela atribuição de cargos, com a mesma autorização da interface.
 */
final class AccountUserSeeder extends Seeder
{
    use SeedsAccountScenario;

    public function run(): void
    {
        $this->actAsAccountAdministrator();
        $account = $this->account();

        app(CreateUserAction::class)->execute(
            $this->identity('Henrique Alves', 'henrique.alves@horizonte.test'),
            UserRole::AccountAdministrator,
            $account,
        );

        foreach ($this->operators() as $email => [$name, $grants, $active]) {
            [$firstRole, $firstScope, $firstCompany, $firstUnit] = $grants[0];

            $this->actAsAccountAdministrator();
            $user = app(CreateUserAction::class)->execute(
                $this->identity($name, $email),
                UserRole::AccountMember,
                $account,
                roleId: $this->role($firstRole)->id,
                node: $this->node($firstScope, $firstCompany, $firstUnit),
            );

            $membershipId = $this->membership($user)->id;
            $others = array_slice($grants, 1);

            if ($others !== []) {
                $this->actAsAccountAdministrator();
                app(ChangeRoleAssignmentsAction::class)->execute($this->grants($others), $account->id, $membershipId);
            }

            if (! $active) {
                $this->actAsAccountAdministrator();
                app(SetMembershipStatusAction::class)->execute($account->id, $membershipId, false);
            }
        }

        $this->stopActing();
    }

    /**
     * Concessões: [cargo, escopo, raiz da empresa, código do estabelecimento]. A primeira entra no cadastro.
     *
     * @return array<string, array{0: string, 1: list<array{0: string, 1: string, 2: ?string, 3: ?string}>, 2: bool}>
     */
    private function operators(): array
    {
        $supermarket = SeedCatalog::SUPERMARKET_ROOT;
        $pharmacy = SeedCatalog::PHARMACY_ROOT;
        $wholesale = SeedCatalog::WHOLESALE_ROOT;

        return [
            // Gerente de uma empresa inteira.
            'bruno.carvalho@horizonte.test' => ['Bruno Carvalho', [
                [SeedCatalog::COMPANY_MANAGER, 'company', $supermarket, null],
            ], true],
            // Gerente de loja em três estabelecimentos da mesma empresa.
            'carla.souza@horizonte.test' => ['Carla Souza', [
                [SeedCatalog::STORE_MANAGER, 'establishment', null, SeedCatalog::BRUMADO_STORE],
                [SeedCatalog::STORE_MANAGER, 'establishment', null, SeedCatalog::GUANAMBI_STORE],
                [SeedCatalog::STORE_MANAGER, 'establishment', null, SeedCatalog::DISTRIBUTION_CENTER],
            ], true],
            // Operador de caixa em estabelecimentos de empresas diferentes.
            'diego.lima@horizonte.test' => ['Diego Lima', [
                [SeedCatalog::CASHIER, 'establishment', null, SeedCatalog::BRUMADO_STORE],
                [SeedCatalog::CASHIER, 'establishment', null, SeedCatalog::SHOPPING_PHARMACY],
            ], true],
            // Comprador em duas empresas e atendimento em uma terceira: cargos diferentes por empresa.
            'elisa.martins@horizonte.test' => ['Elisa Martins', [
                [SeedCatalog::BUYER, 'company', $supermarket, null],
                [SeedCatalog::BUYER, 'company', $wholesale, null],
                [SeedCatalog::CUSTOMER_SERVICE, 'company', $pharmacy, null],
            ], true],
            // Auditoria e leitura em toda a conta.
            'felipe.rocha@horizonte.test' => ['Felipe Rocha', [
                [SeedCatalog::INTERNAL_AUDITOR, 'tenant', null, null],
            ], true],
            // Cargo de disponibilidade restrita à farmácia e gerência de uma das unidades dela.
            'gabriela.nunes@horizonte.test' => ['Gabriela Nunes', [
                [SeedCatalog::PHARMACY_COORDINATOR, 'company', $pharmacy, null],
                [SeedCatalog::STORE_MANAGER, 'establishment', null, SeedCatalog::CANDEIAS_PHARMACY],
            ], true],
            // Atendimento em duas empresas, com caixa na matriz do supermercado.
            'joana.teixeira@horizonte.test' => ['Joana Teixeira', [
                [SeedCatalog::CUSTOMER_SERVICE, 'company', $supermarket, null],
                [SeedCatalog::CUSTOMER_SERVICE, 'company', $wholesale, null],
                [SeedCatalog::CASHIER, 'establishment', null, SeedCatalog::GUANAMBI_STORE],
            ], true],
            // Vínculo suspenso: a identidade existe, mas não entra na conta.
            'igor.pereira@horizonte.test' => ['Igor Pereira', [
                [SeedCatalog::CASHIER, 'establishment', null, SeedCatalog::CANDEIAS_PHARMACY],
            ], false],
        ];
    }

    private function identity(string $name, string $email): CreateUserData
    {
        return CreateUserData::fromInput([
            'name' => $name,
            'email' => $email,
            'mode' => 'manual',
            'password' => SeedCatalog::PASSWORD,
            'password_confirmation' => SeedCatalog::PASSWORD,
        ]);
    }

    /** @param list<array{0: string, 1: string, 2: ?string, 3: ?string}> $grants */
    private function grants(array $grants): ChangeRoleAssignmentsData
    {
        $input = [];

        foreach ($grants as [$role, $scope, $company, $unit]) {
            $node = $this->node($scope, $company, $unit);

            $input[] = [
                'role_id' => $this->role($role)->id,
                'scope' => $node->type,
                'company_id' => $node->companyId,
                'establishment_id' => $node->establishmentId,
            ];
        }

        return ChangeRoleAssignmentsData::fromInput(['grants' => $input]);
    }
}
