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

/**
 * Clientes como relação comercial por empresa (E07), cadastrados pela entrada do próprio módulo.
 */
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

    private function customers(?Company $company = null): string
    {
        return '/conta/empresa/'.($company ?? $this->company)->code.'/clientes';
    }

    private function listing(): string
    {
        return '/conta/estabelecimento/'.$this->company->primaryEstablishment()->sole()->code.'/clientes';
    }

    /** @return array<string,mixed> */
    private function payload(): array
    {
        return [
            'name' => 'Cliente de teste',
            'tag_ids' => [],
            'person_type' => 'individual',
            'document' => '52998224725',
            'additional_phones' => ['71999991234'],
            'additional_emails' => ['contato@example.test'],
            'address' => ['city' => 'Salvador', 'state' => 'BA'],
        ];
    }

    public function test_customer_uses_the_same_person_and_contacts_as_supplier_without_creating_access(): void
    {
        $owner = $this->owner();
        $payload = $this->payload();

        $this->actingAs($owner)->postJson($this->customers(), $payload)->assertNoContent();

        $person = Person::where('name', $payload['name'])->sole();

        $this->postJson('/conta/empresa/'.$this->company->code.'/fornecedores', ['person_id' => $person->id])
            ->assertNoContent();

        $customer = $person->customers()->sole();

        $this->assertSame($owner->account_id, $customer->account_id);
        $this->assertSame($this->company->id, $customer->company_id);
        $this->assertTrue($customer->active);
        $this->assertTrue($person->suppliers()->sole()->active);
        $this->assertFalse($person->user()->exists());
        $this->assertSame(['71999991234'], $person->additional_phones);
        $this->assertFalse(Schema::hasColumn('customers', 'document'));
        $this->assertDatabaseMissing('person_tag', ['person_id' => $person->id]);

        $this->getJson($this->listing())->assertOk()->assertJsonPath('relations.total', 1);

        $this->getJson($this->customers().'/'.$customer->id.'/visualizar')
            ->assertOk()
            ->assertJsonPath('document', '52998224725')
            ->assertJsonPath('additional_emails.0', 'contato@example.test')
            ->assertJsonPath('tags.0.name', 'Cliente');

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
        $payload = $this->payload();

        $this->actingAs($owner)->postJson($this->customers(), $payload)->assertNoContent();

        $customer = Customer::sole();
        $person = Person::findOrFail($customer->person_id);

        $this->postJson('/conta/empresa/'.$this->company->code.'/fornecedores', ['person_id' => $person->id])
            ->assertNoContent();

        $this->patchJson($this->customers().'/'.$customer->id.'/inativar')->assertNoContent();

        $this->assertFalse($customer->fresh()->active);
        $this->assertTrue($person->suppliers()->sole()->active);
        $this->assertSame($payload['document'], $person->fresh()->document);
        $this->assertSame($payload['additional_emails'], $person->fresh()->additional_emails);
        $this->getJson($this->listing().'?status=active')->assertJsonPath('relations.total', 0);
        $this->getJson($this->listing().'?status=inactive')->assertJsonPath('relations.data.0.active', false);

        $this->patchJson($this->customers().'/'.$customer->id.'/ativar')->assertNoContent();

        $this->assertTrue($customer->fresh()->active);
        $this->assertDatabaseCount('customers', 1);

        // Repetir a mesma situação não gera evento.
        $count = AuditRecord::count();

        $this->patchJson($this->customers().'/'.$customer->id.'/ativar')->assertNoContent();

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

        // Permissões de Pessoas não cadastram cliente.
        $this->actingAs($editor)->postJson($this->customers(), $payload)->assertForbidden();

        $this->assertDatabaseMissing('people', ['name' => $payload['name']]);
        $this->assertSame($count, AuditRecord::count());

        $role->permissions()->sync(Permission::whereIn('slug', [...$base, 'account.customers.create'])->pluck('id'));

        $this->postJson($this->customers(), $payload)->assertNoContent();

        $customer = Customer::sole();
        $url = $this->customers().'/'.$customer->id;

        $this->getJson($url.'/visualizar')->assertForbidden();
        $this->getJson($this->listing())->assertForbidden();
        $this->patchJson($url, ['name' => 'Nome compartilhado'])->assertForbidden();

        $role->permissions()->sync(Permission::whereIn('slug', [
            ...$base,
            'account.customers.update',
        ])->pluck('id'));

        $this->patchJson($url, ['name' => 'Nome compartilhado'])->assertNoContent();

        $this->assertSame('Nome compartilhado', $customer->person()->sole()->name);

        // A inativação sem permissão desfaz também a alteração central enviada junto.
        $count = AuditRecord::count();

        $this->patchJson($url, ['name' => 'Mudança revertida', 'active' => '0'])->assertForbidden();
        $this->patchJson($url.'/inativar')->assertForbidden();

        $this->assertDatabaseMissing('people', ['name' => 'Mudança revertida']);
        $this->assertTrue($customer->fresh()->active);
        $this->assertSame($count, AuditRecord::count());

        $role->permissions()->sync(Permission::whereIn('slug', [...$base, 'account.customers.deactivate'])->pluck('id'));

        $this->patchJson($url.'/inativar')->assertNoContent();
        $this->assertFalse($customer->fresh()->active);
        $this->patchJson($url.'/ativar')->assertForbidden();

        $role->permissions()->sync(Permission::whereIn('slug', [
            ...$base,
            'account.customers.activate',
            'account.customers.view',
            'account.customers.list',
        ])->pluck('id'));

        $this->patchJson($url.'/ativar')->assertNoContent();
        $this->getJson($url.'/visualizar')->assertOk()->assertJsonPath('name', 'Nome compartilhado');
        $this->getJson($this->listing())->assertOk()->assertJsonPath('relations.total', 1);
    }

    public function test_special_tag_is_protected_scoped_and_does_not_convert_custom_labels(): void
    {
        $owner = $this->owner();
        $tag = Tag::customerTag($owner->account_id);

        $this->actingAs($owner)
            ->patchJson('/conta/etiquetas/'.$tag->id, ['name' => 'Alterada', 'active' => false])
            ->assertForbidden();
        $this->deleteJson('/conta/etiquetas/'.$tag->id)->assertForbidden();

        // A etiqueta Cliente não é selecionável: a relação vem da empresa escolhida.
        $this->postJson($this->customers(), [...$this->payload(), 'tag_ids' => [$tag->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tag_ids');
        $this->assertDatabaseCount('customers', 0);

        // Empresa de outra conta não é encontrada; a da própria conta recebe o cadastro.
        // Códigos de empresa são únicos por conta: a outra conta usa outro nome para não coincidir.
        $foreignCompany = $this->company;
        $otherAccount = $this->account('Outra conta');
        $this->company($otherAccount, 'Empresa da outra conta', '99888777');
        $other = $this->administrator($otherAccount);

        $this->actingAs($other)->postJson($this->customers($foreignCompany), $this->payload())->assertNotFound();
        $this->actingAs($owner)->postJson($this->customers($foreignCompany), $this->payload())->assertNoContent();

        $customer = Customer::sole();

        $this->actingAs($other)->getJson($this->customers($foreignCompany).'/'.$customer->id.'/visualizar')
            ->assertNotFound();
        $this->patchJson($this->customers($foreignCompany).'/'.$customer->id, ['name' => 'Intruso'])->assertNotFound();
        $otherUnit = Company::where('account_id', $otherAccount->id)->sole()->primaryEstablishment()->sole();
        $this->getJson('/conta/estabelecimento/'.$otherUnit->code.'/clientes')
            ->assertOk()
            ->assertJsonPath('relations.total', 0);

        // Uma etiqueta comum chamada Cliente continua comum e não cria relações por si.
        $account = $this->account('Conta com etiqueta comum');
        $company = $this->company($account, 'Empresa com etiqueta comum', '44555666');
        $custom = Tag::create([
            'account_id' => $account->id,
            'module' => 'customers',
            'name' => 'Cliente',
            'color' => '#123456',
        ]);
        $newOwner = $this->administrator($account);
        $special = Tag::customerTag($account->id);

        $this->assertNotSame($custom->id, $special->id);
        $this->assertNull($custom->fresh()->system_key);
        $this->assertSame('customer', $special->system_key);

        $this->actingAs($newOwner)
            ->postJson($this->customers($company), [
                ...$this->payload(),
                'name' => 'Categoria comum',
                'tag_ids' => [$custom->id],
            ])
            ->assertNoContent();

        $relation = Customer::where('company_id', $company->id)->sole();

        $this->assertDatabaseHas('customers_tags', ['relation_id' => $relation->id, 'tag_id' => $custom->id]);
        $this->assertDatabaseCount('customers', 2);
        $this->assertNull($custom->fresh()->system_key);
    }

    public function test_inspection_records_real_author_and_read_only_blocks_changes(): void
    {
        $owner = $this->owner();
        $platform = User::factory()->create(['is_saas_admin' => true]);

        $this->actingAs($platform)
            ->postJson('/saas/contas/'.$owner->account_id.'/inspecao', ['user_id' => $owner->id, 'mode' => 'edit'])
            ->assertOk();

        $this->postJson($this->customers(), $this->payload())->assertNoContent();

        $event = AuditRecord::where('entity_type', 'customer')->sole();

        $this->assertSame($platform->id, $event->actor_id);
        $this->assertSame($owner->id, $event->represented_user_id);
        $this->assertSame($owner->account_id, $event->account_id);
        $this->assertSame($this->company->id, $event->company_id);
        $this->assertSame('platform-account', $event->context);

        $this->postJson('/conta/inspecao/encerrar')->assertOk();

        $this->postJson('/saas/contas/'.$owner->account_id.'/inspecao', ['user_id' => $owner->id, 'mode' => 'read-only'])
            ->assertOk();

        $count = AuditRecord::count();

        $this->postJson($this->customers(), [...$this->payload(), 'name' => 'Bloqueado', 'document' => '39053344705'])
            ->assertForbidden();

        $this->assertDatabaseMissing('people', ['name' => 'Bloqueado']);
        $this->assertSame($count, AuditRecord::count());
    }
}
