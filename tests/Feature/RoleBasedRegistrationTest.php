<?php

namespace Tests\Feature;

use App\Domains\Acessos\Domain\DTOs\AccessNode;
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

    public function test_commercial_edit_updates_status_and_shared_document_with_separate_authority(): void
    {
        $account = $this->account();
        $owner = $this->administrator($account);
        $company = $this->company($account, 'Comercial', '11222333');
        $base = '/conta/empresa/'.$company->code;

        $this->actingAs($owner)->postJson($base.'/clientes', [
            'name' => 'João',
            'document' => '52998224725',
        ])->assertNoContent();

        $person = Person::where('document', '52998224725')->sole();
        $this->postJson($base.'/fornecedores', ['person_id' => $person->id])->assertNoContent();
        $customer = $person->customers()->sole();
        $supplier = $person->suppliers()->sole();
        $customerPath = $base.'/clientes/'.$customer->id;
        $supplierPath = $base.'/fornecedores/'.$supplier->id;

        $role = $this->role($account, 'Editor comercial', ['account.customers.update']);
        $editor = $this->operator($account, [[$role, AccessNode::company($account->id, $company->id)]]);
        $this->actingAs($editor)->patchJson($customerPath, ['active' => '0'])->assertForbidden();
        $this->assertTrue($customer->fresh()->active);

        $this->actingAs($owner)->patchJson($customerPath, ['active' => '0', 'document' => '390.533.447-05'])
            ->assertNoContent();
        $this->assertFalse($customer->fresh()->active);
        $this->assertTrue($supplier->fresh()->active);
        $this->assertSame('39053344705', $supplier->person()->sole()->document);

        $this->patchJson($supplierPath, ['active' => '0'])->assertNoContent();
        $this->assertFalse($supplier->fresh()->active);
        $this->patchJson($customerPath, ['active' => '1'])->assertNoContent();
        $this->assertTrue($customer->fresh()->active);
        $this->assertFalse($supplier->fresh()->active);
    }

    public function test_multiple_company_registration_and_edit_keep_independent_audited_links(): void
    {
        $account = $this->account();
        $owner = $this->administrator($account);
        $first = $this->company($account, 'Primeira', '11222333');
        $second = $this->company($account, 'Segunda', '44555666');
        $third = $this->company($account, 'Terceira', '66777888');

        foreach (['clientes' => 'customer', 'fornecedores' => 'supplier'] as $segment => $type) {
            $base = '/conta/empresa/'.$first->code.'/'.$segment;
            $name = 'Cadastro '.$segment;
            $this->actingAs($owner)->postJson($base, [
                'name' => $name,
                'add_companies' => [$second->code],
            ])->assertNoContent();

            $person = Person::where('account_id', $account->id)->where('name', $name)->sole();
            $links = $type === 'customer' ? $person->customers() : $person->suppliers();
            $this->assertEqualsCanonicalizing([$first->id, $second->id], (clone $links)->pluck('company_id')->all());
            $firstLink = (clone $links)->where('company_id', $first->id)->sole();

            $this->patchJson($base.'/'.$firstLink->id, [
                'person_type' => 'company',
                'document' => '11.222.333/0001-81',
                'add_companies' => [$third->code],
                'remove_companies' => [$second->code],
            ])->assertNoContent();

            $this->assertSame('company', $person->fresh()->person_type);
            $this->assertEqualsCanonicalizing(
                [$first->id, $third->id],
                (clone $links)->where('active', true)->pluck('company_id')->all(),
            );
            $this->assertFalse((clone $links)->where('company_id', $second->id)->sole()->active);
            $events = AuditRecord::where('entity_type', $type)
                ->whereIn('entity_id', (clone $links)->pluck('id'))
                ->latest('id')->take(2)->get();
            $this->assertEqualsCanonicalizing([$second->id, $third->id], $events->pluck('company_id')->all());
            $this->assertCount(1, $events->pluck('operation_id')->unique());

            $this->patchJson($base.'/'.$firstLink->id, ['person_type' => 'individual'])
                ->assertUnprocessable()->assertJsonValidationErrors('document');
            $this->patchJson($base.'/'.$firstLink->id, ['person_type' => 'individual', 'document' => null])
                ->assertNoContent();
        }
    }

    public function test_forged_company_changes_leave_no_partial_relationships_or_identity_update(): void
    {
        $account = $this->account();
        $first = $this->company($account, 'Visível', '11222333');
        $second = $this->company($account, 'Restrita', '44555666');
        $foreign = $this->company($this->account('Outra conta'), 'Externa', '66777888');
        $owner = $this->administrator($account);
        $base = '/conta/empresa/'.$first->code.'/clientes';

        $this->actingAs($owner)->postJson($base, [
            'name' => 'Fora da conta',
            'add_companies' => [$foreign->code],
        ])->assertNotFound();
        $this->assertDatabaseMissing('people', ['name' => 'Fora da conta']);

        $permissions = [
            'account.customers.create',
            'account.customers.update',
            'account.customers.deactivate',
            'account.people.create',
            'account.people.view',
            'account.people.update',
        ];
        $role = $this->role($account, 'Local', $permissions);
        $operator = $this->operator($account, [[$role, AccessNode::company($account->id, $first->id)]]);
        $this->actingAs($operator)->postJson($base, [
            'name' => 'Escrita proibida',
            'add_companies' => [$second->code],
        ])->assertForbidden();
        $this->assertDatabaseMissing('people', ['name' => 'Escrita proibida']);

        $this->postJson($base, ['name' => 'Registro original'])->assertNoContent();
        $person = Person::where('name', 'Registro original')->sole();
        $relation = $person->customers()->sole();
        $this->patchJson($base.'/'.$relation->id, [
            'name' => 'Escrita revertida',
            'active' => '0',
            'add_companies' => [$second->code],
        ])->assertForbidden();

        $this->assertSame('Registro original', $person->fresh()->name);
        $this->assertTrue($relation->fresh()->active);
        $this->assertCount(1, $person->customers()->get());
        $this->assertDatabaseMissing('audit_records', ['entity_name' => 'Escrita revertida']);

        $this->actingAs($owner)
            ->postJson('/conta/empresa/'.$second->code.'/clientes', ['person_id' => $person->id])
            ->assertNoContent();
        $this->actingAs($operator)
            ->patchJson($base.'/'.$relation->id, ['remove_companies' => [$second->code]])
            ->assertForbidden();
        $this->patchJson($base.'/'.$relation->id, [
            'person_type' => 'company',
            'document' => '11.222.333/0001-81',
        ])->assertForbidden();
        $this->assertSame('individual', $person->fresh()->person_type);
        $this->assertTrue($person->customers()->where('company_id', $second->id)->sole()->active);

    }
}
