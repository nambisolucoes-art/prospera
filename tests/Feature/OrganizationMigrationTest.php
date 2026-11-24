<?php

namespace Tests\Feature;

use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class OrganizationMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_accounts_receive_one_company_without_inferred_fiscal_data(): void
    {
        $account = Client::create([
            'name' => 'Marley Teixeira Meira',
            'person_type' => 'individual',
            'document' => '52998224725',
            'active' => true,
            'details' => ['address' => ['city' => 'Guanambi', 'state' => 'BA'], 'phone' => '77998521027'],
        ]);

        $migration = $this->migration('2026_11_20_000006_provision_account_companies');
        $migration->up();
        $migration->up();

        $company = Company::query()->sole();
        $primary = Establishment::query()->sole();

        $this->assertSame($account->id, $company->account_id);
        $this->assertSame('Marley Teixeira Meira', $company->name);
        $this->assertSame('marley-teixeira-meira', $company->code);
        $this->assertNull($company->person_type);
        $this->assertNull($company->individual_document, 'O CPF do contratante não é copiado sem comprovação.');
        $this->assertNull($company->tax_root);

        $this->assertTrue($primary->is_primary);
        $this->assertTrue($primary->active);
        $this->assertSame('Unidade principal', $primary->name);
        $this->assertNull($primary->address);
        $this->assertNull($primary->phone);

        // Autoria de sistema e a mesma operação para empresa e unidade.
        $events = AuditRecord::whereIn('entity_type', ['company', 'establishment'])->get();

        $this->assertCount(2, $events);
        $this->assertSame([null], $events->pluck('actor_id')->unique()->values()->all());
        $this->assertSame(['Sistema'], $events->pluck('actor_name')->unique()->values()->all());
        $this->assertCount(1, $events->pluck('operation_id')->unique());

        $this->artisan('organization:check')
            ->expectsOutputToContain('Pendência: Empresa #'.$company->id.' da conta #'.$account->id)
            ->assertSuccessful();

        // Mesma ordem do migrate:rollback: a integridade da E08 referencia as unidades do histórico.
        $this->migration('2026_11_21_000003_consolidate_contextual_audit')->down();
        $migration->down();

        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseCount('establishments', 0);
    }

    public function test_reverting_the_provisioning_is_refused_after_new_registrations(): void
    {
        $account = Client::create(['name' => 'Conta', 'active' => true]);
        $migration = $this->migration('2026_11_20_000006_provision_account_companies');
        $migration->up();

        $company = Company::query()->sole();

        DB::table('establishments')->insert([
            'account_id' => $account->id,
            'company_id' => $company->id,
            'code' => 'segunda',
            'name' => 'Segunda unidade',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectExceptionMessage('A reversão perderia dados');

        $migration->down();
    }

    public function test_reverting_the_provisioning_is_refused_after_an_edit(): void
    {
        Client::create(['name' => 'Conta', 'active' => true]);
        $migration = $this->migration('2026_11_20_000006_provision_account_companies');
        $migration->up();

        $company = Company::query()->sole();
        $company->update(['name' => 'Empresa revisada']);

        $this->assertDatabaseHas('audit_records', ['entity_type' => 'company', 'event' => 'updated']);
        $this->expectExceptionMessage('A reversão perderia dados');

        $migration->down();
    }

    public function test_points_are_removed_only_when_empty_and_the_reverse_restores_the_initial_access(): void
    {
        $account = Client::create(['name' => 'Conta', 'active' => true]);
        $owner = User::factory()->asAccountAdministrator($account->id)->create();
        $migration = $this->migration('2026_11_20_000002_remove_points');

        // A reversão depende da tabela antiga de estabelecimentos, que a estrutura organizacional substitui.
        $organization = $this->migration('2026_11_20_000003_create_organization_structure');
        $this->migration('2026_11_21_000003_consolidate_contextual_audit')->down();
        $organization->down();
        $migration->down();

        $this->assertTrue(Schema::hasTable('points'));
        $member = Role::where('account_id', $account->id)->where('system_key', 'member')->sole();

        $this->assertContains('account.points.list', $member->permissions()->pluck('slug')->all());
        $this->assertNotContains('account.establishments.list', $member->permissions()->pluck('slug')->all());
        $this->assertTrue($this->enterAccount($owner->fresh()));
        $this->assertContains('account.points.create', $owner->fresh()?->permissionSlugs() ?? []);

        DB::table('points')->insert([
            'client_id' => $account->id,
            'name' => 'Caixa',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $migration->up();
            $this->fail('Com registros, a remoção deve ser interrompida.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('contém 1 registros', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('points'));
        $this->assertNotNull(Permission::where('slug', 'account.points.list')->first());

        DB::table('points')->delete();
        $migration->up();

        $this->assertFalse(Schema::hasTable('points'));
        $this->assertSame(['account.establishments.list'], $member->permissions()
            ->where('slug', 'like', 'account.establishments.%')
            ->pluck('slug')
            ->all());
        $this->assertNull(Permission::where('slug', 'like', 'account.points.%')->first());
        $this->assertSame([], $member->permissions()->where('slug', 'like', 'account.points.%')->pluck('slug')->all());

        $event = AuditRecord::where('entity_type', 'permission-catalog')->latest('id')->firstOrFail();

        $this->assertArrayHasKey('account.points.list', $event->changes);
        $this->assertNull($event->changes['account.points.list']['after']);

        $organization->up();
    }

    public function test_the_account_data_permission_is_renamed_preserving_roles(): void
    {
        $account = Client::create(['name' => 'Conta', 'active' => true]);
        $role = Role::create(['account_id' => $account->id, 'name' => 'Financeiro']);
        $migration = $this->migration('2026_11_20_000005_rename_account_data_permission');

        $role->permissions()->sync(Permission::where('slug', 'account.account-data.update')->pluck('id'));
        $migration->down();

        $this->assertSame(['account.establishment.update'], $role->permissions()->pluck('slug')->all());

        $migration->up();

        $this->assertSame(['account.account-data.update'], $role->permissions()->pluck('slug')->all());
        $this->assertNull(Permission::where('slug', 'account.establishment.update')->first());
        $this->assertSame(
            'Editar: Dados da conta',
            Permission::where('slug', 'account.account-data.update')->value('name'),
        );

        // Idempotente: uma segunda execução não altera nada.
        $migration->up();

        $this->assertSame(['account.account-data.update'], $role->permissions()->pluck('slug')->all());
    }

    private function migration(string $name): Migration
    {
        return require database_path('migrations/'.$name.'.php');
    }
}
