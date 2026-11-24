<?php

namespace Database\Seeders\Acessos;

use App\Domains\Acessos\Application\Actions\SaveRoleAction;
use App\Domains\Acessos\Application\Data\RoleAvailabilityData;
use App\Domains\Acessos\Application\Data\SaveRoleData;
use App\Domains\Acessos\Domain\Models\Permission;
use Database\Seeders\Support\SeedCatalog;
use Database\Seeders\Support\SeedsAccountScenario;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Cargos da conta com várias permissões cada. O escopo da atribuição (conta, empresa ou estabelecimento)
 * é escolhido no seeder de usuários; aqui as permissões respeitam os alvos admitidos em config/permissions.php.
 */
final class RoleSeeder extends Seeder
{
    use SeedsAccountScenario;

    private const SELF_SERVICE = ['self.help.list', 'self.help.view', 'self.profile.view'];

    public function run(): void
    {
        $this->actAsAccountAdministrator();
        $save = app(SaveRoleAction::class);
        $accountId = $this->account()->id;

        foreach ($this->roles() as $name => [$slugs, $restrictedTo]) {
            $data = SaveRoleData::fromInput(
                ['name' => $name, 'permission_ids' => $this->permissionIds([...$slugs, ...self::SELF_SERVICE])],
                $accountId,
                null,
            );

            $availability = $restrictedTo === null
                ? RoleAvailabilityData::fromInput(['availability' => 'tenant'], $accountId)
                : RoleAvailabilityData::fromInput([
                    'availability' => 'restricted',
                    'available_companies' => [$this->company($restrictedTo)->id],
                ], $accountId);

            $save->execute($accountId, null, $data, $availability);
        }

        $this->stopActing();
    }

    /** @return array<string, array{0: list<string>, 1: string|null}> */
    private function roles(): array
    {
        $commercial = fn (string $module): array => array_map(
            fn (string $action): string => 'account.'.$module.'.'.$action,
            ['list', 'view', 'create', 'update', 'activate', 'deactivate'],
        );

        return [
            SeedCatalog::COMPANY_MANAGER => [[
                'account.dashboard.view',
                'account.companies.list', 'account.companies.view', 'account.companies.update',
                'account.establishments.list', 'account.establishments.view', 'account.establishments.create',
                'account.establishments.update', 'account.establishments.activate',
                'account.establishments.deactivate',
                ...$commercial('customers'),
                ...$commercial('suppliers'),
                'account.people.view', 'account.people.create', 'account.people.update',
                'account.tags.list', 'account.tags.view',
                'account.users.list', 'account.users.view', 'account.users.create', 'account.users.assign-role',
                'account.roles.list', 'account.roles.view',
                'account.audit.list', 'account.audit.view',
            ], null],
            SeedCatalog::STORE_MANAGER => [[
                'account.dashboard.view',
                'account.establishments.list', 'account.establishments.view', 'account.establishments.update',
                'account.people.view', 'account.people.create',
                'account.tags.list', 'account.tags.view',
                'account.users.list', 'account.users.view',
                'account.roles.list', 'account.roles.view',
                'account.audit.list', 'account.audit.view',
            ], null],
            SeedCatalog::CASHIER => [[
                'account.dashboard.view',
                'account.establishments.list', 'account.establishments.view',
                'account.people.view', 'account.people.create',
                'account.tags.list', 'account.tags.view',
            ], null],
            SeedCatalog::BUYER => [[
                'account.dashboard.view',
                'account.companies.list', 'account.companies.view',
                ...$commercial('suppliers'),
                'account.people.view', 'account.people.create', 'account.people.update',
                'account.tags.list', 'account.tags.view',
            ], null],
            SeedCatalog::CUSTOMER_SERVICE => [[
                'account.dashboard.view',
                'account.companies.list', 'account.companies.view',
                'account.customers.list', 'account.customers.view', 'account.customers.create',
                'account.customers.update',
                'account.people.view', 'account.people.create', 'account.people.update',
                'account.tags.list', 'account.tags.view',
            ], null],
            SeedCatalog::INTERNAL_AUDITOR => [[
                'account.dashboard.view',
                'account.audit.list', 'account.audit.view', 'account.audit.technical',
                'account.companies.list', 'account.companies.view',
                'account.establishments.list', 'account.establishments.view',
                'account.users.list', 'account.users.view',
                'account.roles.list', 'account.roles.view',
                'account.tags.list', 'account.tags.view',
            ], null],
            SeedCatalog::PHARMACY_COORDINATOR => [[
                'account.dashboard.view',
                'account.companies.list', 'account.companies.view',
                'account.establishments.list', 'account.establishments.view', 'account.establishments.update',
                ...$commercial('customers'),
                'account.suppliers.list', 'account.suppliers.view',
                'account.people.view', 'account.people.create',
                'account.tags.list', 'account.tags.view',
            ], SeedCatalog::PHARMACY_ROOT],
        ];
    }

    /**
     * @param  list<string>  $slugs
     * @return list<int>
     */
    private function permissionIds(array $slugs): array
    {
        $unique = array_values(array_unique($slugs));
        $ids = Permission::whereIn('slug', $unique)->pluck('id', 'slug');
        $missing = array_diff($unique, $ids->keys()->all());

        if ($missing !== []) {
            throw new RuntimeException('Permissões fora do catálogo: '.implode(', ', $missing).'.');
        }

        return array_values(array_map(fn ($id): int => (int) $id, $ids->all()));
    }
}
