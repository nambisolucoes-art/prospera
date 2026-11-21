<?php

namespace Tests\Feature;

use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Domains\Clientes\Domain\Models\Customer;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Concerns\BuildsCommercialScenario;
use Tests\TestCase;

class CustomerDirectoryTest extends TestCase
{
    use BuildsCommercialScenario;
    use RefreshDatabase;

    private Company $company;

    private function owner(): User
    {
        $account = $this->account('Conta de clientes');
        $this->company = $this->company($account, 'Empresa de clientes', '11222333');

        return $this->administrator($account);
    }

    /**
     * @param  array<string,bool>  $roles
     * @return array<string,mixed>
     */
    private function payload(array $roles = ['customer' => true]): array
    {
        return [
            'name' => 'Cliente de teste',
            'tag_ids' => [],
            'person_type' => 'individual',
            'document' => '52998224725',
            'additional_phones' => ['71999991234'],
            'additional_emails' => ['contato@example.test'],
            'address' => ['city' => 'Salvador', 'state' => 'BA'],
            'relations' => [$this->company->id => array_map(fn (bool $value) => $value ? '1' : '0', $roles)],
        ];
    }

    public function test_customer_uses_the_same_person_and_contacts_as_supplier_without_creating_access(): void
    {
        $owner = $this->owner();
        $payload = $this->payload(['customer' => true, 'supplier' => true]);
        $this->actingAs($owner)->postJson('/conta/pessoas', $payload)->assertOk();
        $person = Person::where('name', $payload['name'])->sole();
        $customer = $person->customers()->sole();
        $this->assertSame($owner->account_id, $customer->account_id);
        $this->assertSame($this->company->id, $customer->company_id);
        $this->assertTrue($customer->active);
        $this->assertTrue($person->suppliers()->sole()->active);
        $this->assertFalse($person->user()->exists());
        $this->assertSame(['71999991234'], $person->additional_phones);
        $this->assertFalse(Schema::hasColumn('customers', 'document'));
        $this->assertDatabaseMissing('person_tag', ['person_id' => $person->id]);
        $this->getJson('/conta/pessoas?customers=1')->assertOk()->assertJsonCount(1, 'people.data');
        $this
            ->getJson('/conta/pessoas/'.$person->id.'/visualizar')
            ->assertOk()
            ->assertJsonPath('relations.0.company_id', $this->company->id)
            ->assertJsonPath('relations.0.customer', 'active')
            ->assertJsonPath('relations.0.supplier', 'active')
            ->assertJsonPath('document', '52998224725')
            ->assertJsonPath('additional_emails.0', 'contato@example.test')
            ->assertJsonFragment(['system_key' => 'customer']);
        $audit = AuditRecord::where('entity_type', 'customer')->sole();
        $this->assertSame($owner->id, $audit->actor_id);
        $this->assertSame($owner->account_id, $audit->account_id);
        $this->assertSame($this->company->id, $audit->company_id);
        $this->assertNull($audit->establishment_id);
        $this->assertSame($person->name.' (Empresa de clientes)', $audit->entity_name);
        $personAudit = AuditRecord::where('entity_type', 'person')->where('entity_id', $person->id)->sole();
        $this->assertSame($personAudit->operation_id, $audit->operation_id);
        $this->assertNull($personAudit->company_id);
        $this->assertStringNotContainsString('52998224725', json_encode($audit->changes));
        $this->artisan('audit:check')->assertSuccessful();
    }

    public function test_removing_and_reselecting_customer_preserves_person_supplier_and_same_customer(): void
    {
        $owner = $this->owner();
        $payload = $this->payload(['customer' => true, 'supplier' => true]);
        $this->actingAs($owner)->postJson('/conta/pessoas', $payload)->assertOk();
        $customer = Customer::sole();
        $person = Person::findOrFail($customer->person_id);
        $this->patchJson('/conta/pessoas/'.$person->id, $this->payload(['customer' => false]))->assertOk();
        $this->assertFalse($customer->fresh()->active);
        $this->assertTrue($person->suppliers()->sole()->active);
        $this->assertSame($payload['document'], $person->fresh()->document);
        $this->assertSame($payload['additional_emails'], $person->fresh()->additional_emails);
        $this->getJson('/conta/pessoas?customers=1')->assertJsonCount(0, 'people.data');
        $this->getJson('/conta/pessoas/'.$person->id.'/visualizar')->assertJsonPath('relations.0.customer', 'inactive');
        $this->patchJson('/conta/pessoas/'.$person->id, $payload)->assertOk();
        $this->assertTrue($customer->fresh()->active);
        $this->assertDatabaseCount('customers', 1);
        $count = AuditRecord::count();
        $this->patchJson('/conta/pessoas/'.$person->id, $payload)->assertOk();
        $this->assertSame($count, AuditRecord::count());
    }

    public function test_each_customer_permission_is_independent_and_failed_writes_roll_back(): void
    {
        $owner = $this->owner();
        $payload = $this->payload();
        $role = Role::create(['account_id' => $owner->account_id, 'name' => 'Editor de pessoas']);
        $base = $this->peopleSlugs();
        $role->permissions()->sync(Permission::whereIn('slug', $base)->pluck('id'));
        $editor = User::factory()->forAccount($owner->account_id, $role)->create();
        $count = AuditRecord::count();
        $this->actingAs($editor)->postJson('/conta/pessoas', $payload)->assertForbidden();
        $this->assertDatabaseMissing('people', ['name' => $payload['name']]);
        $this->assertSame($count, AuditRecord::count());
        $role->permissions()->sync(Permission::whereIn('slug', [...$base, 'account.customers.create'])->pluck('id'));
        $this->postJson('/conta/pessoas', $payload)->assertOk();
        $customer = Customer::sole();
        $url = '/conta/pessoas/'.$customer->person_id;
        $this->getJson($url.'/visualizar')->assertJsonPath('relations', []);
        $this->getJson('/conta/pessoas?customers=1')->assertForbidden();
        $this->patchJson($url, [...$payload, 'name' => 'Nome compartilhado'])->assertOk();
        $count = AuditRecord::count();
        $this->patchJson($url, [...$this->payload(['customer' => false]), 'name' => 'Mudança revertida'])
            ->assertForbidden();
        $this->assertDatabaseMissing('people', ['name' => 'Mudança revertida']);
        $this->assertSame($count, AuditRecord::count());
        $role
            ->permissions()
            ->sync(Permission::whereIn('slug', [...$base, 'account.customers.deactivate'])->pluck('id'));
        $this->patchJson($url, [...$this->payload(['customer' => false]), 'name' => 'Nome compartilhado'])->assertOk();
        $this->assertFalse($customer->fresh()->active);
        $this->patchJson($url, [...$payload, 'name' => 'Nome compartilhado'])->assertForbidden();
        $role->permissions()->sync(Permission::whereIn('slug', [
            ...$base,
            'account.customers.activate',
            'account.customers.view',
            'account.customers.list',
        ])->pluck('id'));
        $this->patchJson($url, [...$payload, 'name' => 'Nome compartilhado'])->assertOk();
        $this->getJson($url.'/visualizar')->assertJsonPath('relations.0.customer', 'active');
        $this->getJson('/conta/pessoas?customers=1')->assertOk()->assertJsonCount(1, 'people.data');
    }

    public function test_special_tag_is_protected_scoped_and_does_not_convert_custom_labels(): void
    {
        $owner = $this->owner();
        $tag = Tag::customerTag($owner->account_id);
        $this
            ->actingAs($owner)
            ->patchJson('/conta/etiquetas/'.$tag->id, ['name' => 'Alterada', 'active' => false])
            ->assertForbidden();
        $this->deleteJson('/conta/etiquetas/'.$tag->id)->assertForbidden();

        // A etiqueta Cliente não é selecionável: a relação vem da empresa escolhida.
        $this->postJson('/conta/pessoas', [...$this->payload(), 'tag_ids' => [$tag->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tag_ids.0');
        $foreignCompany = $this->company;
        $other = $this->owner();
        $this->actingAs($other)
            ->postJson('/conta/pessoas', [...$this->payload(), 'relations' => [$foreignCompany->id => ['customer' => '1']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('relations');
        $this->actingAs($owner)
            ->postJson('/conta/pessoas', [...$this->payload(), 'relations' => [$foreignCompany->id => ['customer' => '1']]])
            ->assertOk();
        $id = Customer::sole()->person_id;
        $this->actingAs($other)->getJson('/conta/pessoas/'.$id.'/visualizar')->assertNotFound();
        $this->patchJson('/conta/pessoas/'.$id, ['name' => 'Intruso'])->assertNotFound();
        $this->getJson('/conta/pessoas?customers=1')->assertJsonCount(0, 'people.data');
        $account = $this->account('Conta com etiqueta comum');
        $custom = Tag::create([
            'account_id' => $account->id,
            'module' => 'people',
            'name' => 'Cliente',
            'color' => '#123456',
        ]);
        $newOwner = $this->administrator($account);
        $special = Tag::customerTag($account->id);
        $this->assertNotSame($custom->id, $special->id);
        $this->assertNull($custom->fresh()->system_key);
        $this->assertSame('customer', $special->system_key);
        $this
            ->actingAs($newOwner)
            ->postJson('/conta/pessoas', ['name' => 'Categoria comum', 'tag_ids' => [$custom->id]])
            ->assertOk();
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_inspection_records_real_author_and_read_only_blocks_changes(): void
    {
        $owner = $this->owner();
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $this
            ->actingAs($platform)
            ->postJson('/saas/contas/'.$owner->account_id.'/inspecao', ['user_id' => $owner->id, 'mode' => 'edit'])
            ->assertOk();
        $this->postJson('/conta/pessoas', $this->payload())->assertOk();
        $event = AuditRecord::where('entity_type', 'customer')->sole();
        $this->assertSame($platform->id, $event->actor_id);
        $this->assertSame($owner->id, $event->represented_user_id);
        $this->assertSame($owner->account_id, $event->account_id);
        $this->assertSame($this->company->id, $event->company_id);
        $this->assertSame('platform-account', $event->context);
        $this->postJson('/conta/inspecao/encerrar')->assertOk();
        $this
            ->postJson('/saas/contas/'.$owner->account_id.'/inspecao', ['user_id' => $owner->id, 'mode' => 'read-only'])
            ->assertOk();
        $count = AuditRecord::count();
        $this->postJson('/conta/pessoas', [...$this->payload(), 'name' => 'Bloqueado'])->assertForbidden();
        $this->assertDatabaseMissing('people', ['name' => 'Bloqueado']);
        $this->assertSame($count, AuditRecord::count());
    }
}
