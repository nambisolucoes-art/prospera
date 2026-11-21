<?php

namespace Tests\Feature;

use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Fornecedores\Domain\Models\Supplier;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Concerns\BuildsCommercialScenario;
use Tests\TestCase;

class SupplierDirectoryTest extends TestCase
{
    use BuildsCommercialScenario;
    use RefreshDatabase;

    /** @var array<int, Company> */
    private array $companies = [];

    private function owner(): User
    {
        $account = $this->account('Conta de fornecedores');
        $this->companies[$account->id] = $this->company($account, 'Empresa de fornecedores', '11222333');

        return $this->administrator($account);
    }

    /** @return array<int, array<string,string>> */
    private function supplier(User $owner, bool $selected = true): array
    {
        return [$this->companies[$owner->account_id]->id => ['supplier' => $selected ? '1' : '0']];
    }

    /** @return array<string,mixed> */
    private function payload(User $owner, string $document = '52998224725'): array
    {
        return [
            'name' => 'Fornecedor de teste',
            'email' => 'fornecedor@example.test',
            'phone' => '71999999999',
            'tag_ids' => [],
            'relations' => $this->supplier($owner),
            'person_type' => 'individual',
            'document' => $document,
            'contact_name' => 'Contato comercial',
            'address' => ['postal_code' => '40000-000', 'city' => 'Salvador', 'state' => 'ba'],
        ];
    }

    public function test_supplier_is_created_on_the_same_person_scoped_audited_and_without_login(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->postJson('/conta/pessoas', $this->payload($owner))->assertOk();
        $person = Person::where('name', 'Fornecedor de teste')->sole();
        $supplier = $person->suppliers()->sole();
        $this->assertSame($owner->account_id, $supplier->account_id);
        $this->assertSame($this->companies[$owner->account_id]->id, $supplier->company_id);
        $this->assertTrue($supplier->active);
        $this->assertFalse($person->user()->exists());
        $this->assertSame('40000000', $person->address['postal_code']);
        $this->assertSame('BA', $person->address['state']);
        $this->getJson('/conta/pessoas?suppliers=1')->assertOk()->assertJsonCount(1, 'people.data');
        $this
            ->getJson('/conta/pessoas/'.$person->id.'/visualizar')
            ->assertOk()
            ->assertJsonPath('document', '52998224725');
        $audit = AuditRecord::where('entity_type', 'supplier')->where('entity_id', $supplier->id)->sole();
        $this->assertSame($owner->id, $audit->actor_id);
        $this->assertSame($owner->account_id, $audit->account_id);
        $this->assertSame($person->name.' (Empresa de fornecedores)', $audit->entity_name);
        $this->assertSame($supplier->company_id, $audit->company_id);
        $this->assertStringNotContainsString('52998224725', json_encode($audit->changes));
        $personEvent = AuditRecord::where('entity_type', 'person')->where('entity_id', $person->id)->sole();
        $this->assertSame($personEvent->operation_id, $audit->operation_id);
        $this->assertStringNotContainsString('52998224725', json_encode($personEvent->changes));
    }

    public function test_removing_supplier_label_inactivates_and_reselecting_restores_the_same_profile(): void
    {
        $owner = $this->owner();
        $payload = $this->payload($owner);
        $this->actingAs($owner)->postJson('/conta/pessoas', $payload)->assertOk();
        $supplier = Supplier::sole();
        $this->patchJson('/conta/pessoas/'.$supplier->person_id, [
            'name' => $payload['name'],
            'email' => $payload['email'],
            'phone' => $payload['phone'],
            'tag_ids' => [],
            'relations' => $this->supplier($owner, false),
        ])->assertOk();
        $this->assertFalse($supplier->fresh()->active);
        $this->assertSame('52998224725', Person::findOrFail($supplier->person_id)->document);
        $this->getJson('/conta/pessoas?suppliers=1')->assertJsonCount(0, 'people.data');
        $this->patchJson('/conta/pessoas/'.$supplier->person_id, $payload)->assertOk();
        $this->assertTrue($supplier->fresh()->active);
        $this->assertDatabaseCount('suppliers', 1);
        $count = AuditRecord::count();
        $this->patchJson('/conta/pessoas/'.$supplier->person_id, $payload)->assertOk();
        $this->assertSame($count, AuditRecord::count());
    }

    public function test_documents_validate_checksums_and_support_alphanumeric_cnpj(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        foreach (['11111111111', '52998224724', '123'] as $document) {
            $this
                ->postJson('/conta/pessoas', $this->payload($owner, $document))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('document');
        }
        $payload = $this->payload($owner, '12.ABC.345/01DE-35');
        $payload['person_type'] = 'company';
        $payload['trade_name'] = 'Fantasia';
        $this->postJson('/conta/pessoas', $payload)->assertOk();
        $this->assertSame('12ABC34501DE35', Person::whereHas('suppliers')->sole()->document);
        $this->postJson('/conta/pessoas', $payload)->assertUnprocessable()->assertJsonValidationErrors('document');
        $this->assertDatabaseCount('suppliers', 1);
        $other = $this->owner();
        $payload['relations'] = $this->supplier($other);
        $this->actingAs($other)->postJson('/conta/pessoas', $payload)->assertOk();
        $this->assertDatabaseCount('suppliers', 2);
        $this
            ->postJson('/conta/pessoas', [...$payload, 'relations' => $this->supplier($owner)])
            ->assertUnprocessable();
        $personId = Supplier::where('account_id', $owner->account_id)->value('person_id');
        $this->getJson('/conta/pessoas/'.$personId.'/visualizar')->assertNotFound();
    }

    public function test_supplier_permissions_are_independent_and_failures_roll_back_all_writes(): void
    {
        $owner = $this->owner();
        $payload = $this->payload($owner);
        $role = Role::create(['account_id' => $owner->account_id, 'name' => 'Editor de pessoas']);
        $role->permissions()->sync(Permission::whereIn('slug', [
            'account.people.create',
            'account.people.update',
            'account.people.view',
            'account.people.list',
        ])->pluck('id'));
        $editor = User::factory()->forAccount($owner->account_id, $role)->create();
        $count = AuditRecord::count();
        $this->actingAs($editor)->postJson('/conta/pessoas', $payload)->assertForbidden();
        $this->assertDatabaseMissing('people', ['name' => $payload['name']]);
        $this->assertSame($count, AuditRecord::count());
        $this->actingAs($owner)->postJson('/conta/pessoas', $payload)->assertOk();
        $supplier = Supplier::sole();
        $this
            ->actingAs($editor)
            ->getJson('/conta/pessoas/'.$supplier->person_id.'/visualizar')
            ->assertJsonPath('relations', []);
        $this->getJson('/conta/pessoas?suppliers=1')->assertForbidden();
        $count = AuditRecord::count();
        $payload['name'] = 'Identidade compartilhada';
        $this->patchJson('/conta/pessoas/'.$supplier->person_id, $payload)->assertOk();
        $this->assertDatabaseHas('people', ['name' => 'Identidade compartilhada']);
        $count = AuditRecord::count();
        $this
            ->patchJson('/conta/pessoas/'.$supplier->person_id, ['name' => 'Identidade compartilhada', 'tag_ids' => []])
            ->assertOk();

        // Sem envio da relação, a relação existente não muda; o envio forjado de inativação é recusado.
        $this
            ->patchJson('/conta/pessoas/'.$supplier->person_id, [
                'name' => 'Identidade compartilhada',
                'relations' => $this->supplier($owner, false),
            ])
            ->assertForbidden();
        $this->assertTrue($supplier->fresh()->active);
        $this->assertSame($count, AuditRecord::count());
    }

    public function test_supplier_special_definition_is_protected_and_optional_fields_are_not_required(): void
    {
        $owner = $this->owner();
        $tag = Tag::supplierTag($owner->account_id);
        $this
            ->actingAs($owner)
            ->patchJson('/conta/etiquetas/'.$tag->id, [
                'name' => 'Alterada',
                'module' => 'people',
                'color' => '#123456',
                'active' => false,
            ])
            ->assertForbidden();
        $this->deleteJson('/conta/etiquetas/'.$tag->id)->assertForbidden();
        $this->postJson('/conta/pessoas', ['name' => 'Fornecedor inicial', 'relations' => $this->supplier($owner)])
            ->assertOk();
        $this->assertNull(Person::whereHas('suppliers')->sole()->document);
        $this->postJson('/conta/pessoas', [
            'name' => 'Fornecedor inválido',
            'relations' => $this->supplier($owner),
            'person_type' => 'inválido',
        ])->assertUnprocessable()->assertJsonValidationErrors('person_type');
        $this->assertDatabaseMissing('people', ['name' => 'Fornecedor inválido']);
        $this->artisan('audit:check')->assertSuccessful();
    }

    public function test_supplier_inspection_preserves_platform_author_and_read_only_rejects_writes(): void
    {
        $owner = $this->owner();
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $this
            ->actingAs($platform)
            ->postJson('/saas/contas/'.$owner->account_id.'/inspecao', ['user_id' => $owner->id, 'mode' => 'edit'])
            ->assertOk();
        $this->postJson('/conta/pessoas', $this->payload($owner))->assertOk();
        $event = AuditRecord::where('entity_type', 'supplier')->sole();
        $this->assertSame($platform->id, $event->actor_id);
        $this->assertSame($owner->id, $event->represented_user_id);
        $this->assertSame($owner->account_id, $event->account_id);
        $this->assertSame('platform-account', $event->context);
        $this->getJson('/conta/auditoria/'.$event->id.'/visualizar')->assertOk()->assertDontSee('52998224725');
        $this->postJson('/conta/inspecao/encerrar')->assertOk();
        $this
            ->actingAs($platform)
            ->postJson('/saas/contas/'.$owner->account_id.'/inspecao', ['user_id' => $owner->id, 'mode' => 'read-only'])
            ->assertOk();
        $count = AuditRecord::count();
        $this
            ->postJson('/conta/pessoas', [...$this->payload($owner, ''), 'name' => 'Fornecedor bloqueado'])
            ->assertForbidden();
        $this->assertDatabaseMissing('people', ['name' => 'Fornecedor bloqueado']);
        $this->assertSame($count, AuditRecord::count());
    }

    public function test_supplier_definition_does_not_convert_an_existing_custom_label(): void
    {
        $account = $this->account('Conta com etiqueta comum');
        $custom = Tag::create([
            'account_id' => $account->id,
            'module' => 'people',
            'name' => 'Fornecedor',
            'color' => '#123456',
        ]);
        $owner = $this->administrator($account);
        $special = Tag::supplierTag($account->id);
        $this->assertNotSame($custom->id, $special->id);
        $this->assertNull($custom->fresh()->system_key);
        $this->assertSame('supplier', $special->system_key);
        $this
            ->actingAs($owner)
            ->postJson('/conta/pessoas', ['name' => 'Pessoa com categoria', 'tag_ids' => [$custom->id]])
            ->assertOk();
        $this->assertDatabaseCount('suppliers', 0);
    }

    public function test_identity_and_contacts_belong_to_people_independently_of_their_roles(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $input = [
            'name' => 'Pessoa compartilhada',
            'person_type' => 'individual',
            'document' => '529.982.247-25',
            'phone' => '(71) 99999-9999',
            'whatsapp' => '(71) 98888-8888',
            'address' => ['city' => 'Salvador', 'state' => 'BA'],
        ];
        $this->postJson('/conta/pessoas', $input)->assertOk();
        $person = Person::where('name', $input['name'])->sole();
        $this->assertSame('52998224725', $person->document);
        $this->assertSame('71999999999', $person->phone);
        $this->assertSame('71988888888', $person->whatsapp);
        $this->assertDatabaseCount('suppliers', 0);
        $this
            ->getJson('/conta/pessoas/'.$person->id.'/editar')
            ->assertJsonPath('person.document', '52998224725')
            ->assertJsonPath('person.address.city', 'Salvador');
        $companyId = $this->companies[$owner->account_id]->id;
        $this
            ->patchJson(
                '/conta/pessoas/'.$person->id,
                [...$input, 'relations' => [$companyId => ['customer' => '1', 'supplier' => '1']]],
            )
            ->assertOk();
        $this->assertDatabaseCount('people', 2);
        $this->assertDatabaseCount('suppliers', 1);
        $this->assertFalse(Schema::hasColumn('suppliers', 'document'));
        $this->assertFalse(Schema::hasColumn('suppliers', 'address'));
        $this->patchJson('/conta/pessoas/'.$person->id, [...$input, 'relations' => $this->supplier($owner, false)])
            ->assertOk();
        $this->assertSame('52998224725', $person->fresh()->document);
        $this->assertSame('Salvador', $person->fresh()->address['city']);
        $this
            ->postJson('/conta/pessoas', [...$input, 'name' => 'Duplicada'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document');
        $this
            ->postJson('/conta/pessoas', [...$input, 'document' => '11111111111'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document');
        $this->postJson('/conta/pessoas', [...$input, 'name' => 'Documento opcional', 'document' => '---'])->assertOk();
        $this->assertNull(Person::where('name', 'Documento opcional')->sole()->document);
    }

    public function test_migration_preserves_existing_supplier_identity_on_its_person(): void
    {
        $owner = $this->owner();
        $commercial = require database_path('migrations/2026_11_21_000001_add_company_to_commercial_relations.php');
        $migration = require database_path('migrations/2026_11_18_000008_move_shared_supplier_fields_to_people.php');
        (require database_path('migrations/2026_11_21_000002_add_commercial_tags.php'))->down();
        $commercial->down();
        $migration->down();
        $personId = DB::table('people')->insertGetId([
            'name' => 'Legado',
            'account_id' => $owner->account_id,
            'email' => 'legado@example.test',
        ]);
        DB::table('suppliers')->insert([
            'person_id' => $personId,
            'account_id' => $owner->account_id,
            'active' => true,
            'person_type' => 'company',
            'document' => '12ABC34501DE35',
            'trade_name' => 'Nome fantasia legado',
            'whatsapp' => '71988888888',
            'address' => json_encode(['city' => 'Salvador', 'state' => 'BA']),
        ]);
        $count = AuditRecord::count();
        $migration->up();
        $person = Person::findOrFail($personId);
        $this->assertSame('company', $person->person_type);
        $this->assertSame('12ABC34501DE35', $person->document);
        $this->assertSame('Nome fantasia legado', $person->trade_name);
        $this->assertSame('71988888888', $person->whatsapp);
        $this->assertSame('Salvador', $person->address['city']);
        $this->assertTrue((bool) DB::table('suppliers')->where('person_id', $personId)->value('active'));
        $commercial->up();
        $supplier = $person->suppliers()->sole();
        $this->assertTrue($supplier->active);
        $this->assertSame($this->companies[$owner->account_id]->id, $supplier->company_id);
        $this->assertSame($count, AuditRecord::count());
        $this->artisan('audit:check')->assertSuccessful();
    }

    public function test_state_and_municipal_registrations_work_for_both_person_types(): void
    {
        $owner = $this->owner();
        $this
            ->actingAs($owner)
            ->postJson('/conta/pessoas', [
                'name' => 'Pessoa com inscrições',
                'person_type' => 'individual',
                'state_registration' => '123456789',
                'municipal_registration' => '987654',
            ])
            ->assertOk();
        $person = Person::where('name', 'Pessoa com inscrições')->sole();
        foreach (['individual', 'company', 'individual'] as $type) {
            $this
                ->patchJson('/conta/pessoas/'.$person->id, ['name' => $person->name, 'person_type' => $type])
                ->assertOk();
            $this->assertSame('123456789', $person->fresh()->state_registration);
            $this->assertSame('987654', $person->fresh()->municipal_registration);
            $this
                ->getJson('/conta/pessoas/'.$person->id.'/visualizar')
                ->assertOk()
                ->assertJsonPath('state_registration', '123456789')
                ->assertJsonPath('municipal_registration', '987654');
        }
    }
}
