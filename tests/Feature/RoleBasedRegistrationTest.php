<?php

namespace Tests\Feature;

use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Domains\Clientes\Domain\Models\Customer;
use App\Domains\Fornecedores\Domain\Models\Supplier;
use App\Domains\Pessoas\Domain\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsCommercialScenario;
use Tests\TestCase;

final class RoleBasedRegistrationTest extends TestCase
{
    use BuildsCommercialScenario;
    use RefreshDatabase;

    public function test_customer_and_supplier_reuse_the_same_identity_without_a_people_directory(): void
    {
        $account = $this->account();
        $company = $this->company($account, 'Comercial', '11222333');
        $otherCompany = $this->company($account, 'Outra empresa', '44555666');

        $base = '/conta/empresa/'.$company->code;
        $this->actingAs($this->administrator($account));
        $this->getJson('/conta/pessoas')->assertNotFound();
        $this->getJson('/conta/pessoas/cadastrar')->assertNotFound();
        $this->postJson('/conta/pessoas', ['name' => 'Avulsa'])->assertNotFound();

        $this->postJson($base.'/clientes', [
            'name' => 'João',
            'person_type' => 'individual',
            'document' => '52998224725',
            'relations' => [$otherCompany->id => ['supplier' => true]],
        ])->assertNoContent();

        $person = Person::where('account_id', $account->id)->where('document', '52998224725')->sole();
        $this->assertFalse($person->suppliers()->exists());
        $this->postJson($base.'/fornecedores/pessoa', [
            'documento' => '52998224725',
            'person_type' => 'individual',
        ])->assertOk()->assertJsonPath('person.id', $person->id)->assertJsonPath('person.other_role', 'Cliente');

        $this->postJson($base.'/fornecedores', ['person_id' => $person->id])->assertNoContent();

        $customer = Customer::where('person_id', $person->id)->sole();
        $supplier = Supplier::where('person_id', $person->id)->sole();
        $this->assertSame($customer->person_id, $supplier->person_id);
        $this->getJson($base.'/clientes/'.$customer->id.'/visualizar')
            ->assertOk()
            ->assertJsonPath('tags.0.name', 'Cliente');
        $this->getJson($base.'/fornecedores/'.$supplier->id.'/visualizar')
            ->assertOk()
            ->assertJsonPath('tags.0.name', 'Fornecedor');
        $this->getJson('/conta/empresa/'.$otherCompany->code.'/clientes/'.$customer->id.'/visualizar')
            ->assertNotFound();
    }

    public function test_account_user_receives_the_system_tag_without_selecting_it(): void
    {
        $account = $this->account();
        $owner = $this->administrator($account);
        $roleId = $this->membership($owner)->assignments()->sole()->role_id;
        $this->actingAs($owner)->postJson('/conta/usuarios', [
            'name' => 'Operador da conta',
            'email' => 'operador@example.test',
            'mode' => 'manual',
            'password' => 'SenhaTeste123!',
            'password_confirmation' => 'SenhaTeste123!',
            'role_id' => $roleId,
            'scope' => 'tenant',
        ])->assertOk();

        $person = Person::where('account_id', $account->id)->where('name', 'Operador da conta')->sole();
        $this->assertTrue($person->tags()->where('system_key', 'user')->exists());
    }

    public function test_removed_screen_permissions_are_retired_and_audited(): void
    {
        $account = $this->account();
        $role = $this->role($account, 'Cargo anterior', ['account.customers.list']);
        $old = Permission::create([
            'slug' => 'account.people.list',
            'scope' => 'account',
            'module' => 'people',
            'name' => 'Listar: Pessoas',
            'is_system' => true,
        ]);
        $role->permissions()->attach($old->id);

        $migration = require database_path('migrations/2026_11_21_000007_retire_people_directory_permissions.php');
        $migration->up();

        $this->assertDatabaseMissing('permissions', ['id' => $old->id]);
        $this->assertDatabaseMissing('permission_role', ['permission_id' => $old->id]);
        $this->assertTrue($role->permissions()->where('slug', 'account.customers.list')->exists());

        $record = AuditRecord::where('event', 'catalog-synchronized')
            ->latest('id')->firstOrFail();
        $this->assertSame('Listar: Pessoas', $record->changes['account.people.list']['before']);
        $this->assertNull($record->changes['account.people.list']['after']);
    }
}
