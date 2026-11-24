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

    private function owner(string $company = 'Empresa de fornecedores', string $root = '11222333'): User
    {
        $account = $this->account('Conta de fornecedores');
        $this->companies[$account->id] = $this->company($account, $company, $root);

        return $this->administrator($account);
    }

    private function suppliers(User $owner): string
    {
        return '/conta/empresa/'.$this->companies[$this->accountId($owner)]->code.'/fornecedores';
    }

    private function listing(User $owner): string
    {
        $unit = $this->companies[$this->accountId($owner)]->primaryEstablishment()->sole();

        return '/conta/estabelecimento/'.$unit->code.'/fornecedores';
    }

    /** @return array<string,mixed> */
    private function payload(string $document = '52998224725'): array
    {
        return [
            'name' => 'Fornecedor de teste',
            'email' => 'fornecedor@example.test',
            'phone' => '71999999999',
            'tag_ids' => [],
            'person_type' => 'individual',
            'document' => $document,
            'contact_name' => 'Contato comercial',
            'address' => ['postal_code' => '40000-000', 'city' => 'Salvador', 'state' => 'ba'],
        ];
    }

    public function test_supplier_is_created_on_the_same_person_scoped_audited_and_without_login(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->postJson($this->suppliers($owner), $this->payload())->assertNoContent();

        $person = Person::where('name', 'Fornecedor de teste')->sole();
        $supplier = $person->suppliers()->sole();

        $this->assertSame($this->accountId($owner), $supplier->account_id);
        $this->assertSame($this->companies[$this->accountId($owner)]->id, $supplier->company_id);
        $this->assertTrue($supplier->active);
        $this->assertFalse($person->user()->exists());
        $this->assertSame('40000000', $person->address['postal_code']);
        $this->assertSame('BA', $person->address['state']);
        $this->assertSame('Contato comercial', $person->contact_name);

        $this->getJson($this->listing($owner))->assertOk()->assertJsonPath('relations.total', 1);

        $this->getJson($this->suppliers($owner).'/'.$supplier->id.'/visualizar')
            ->assertOk()
            ->assertJsonPath('document', '52998224725')
            ->assertJsonPath('tags.0.name', 'Fornecedor');

        $audit = AuditRecord::where('entity_type', 'supplier')->where('entity_id', $supplier->id)->sole();

        $this->assertSame($owner->id, $audit->actor_id);
        $this->assertSame($this->accountId($owner), $audit->account_id);
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

        $this->actingAs($owner)->postJson($this->suppliers($owner), $this->payload())->assertNoContent();

        $supplier = Supplier::sole();
        $url = $this->suppliers($owner).'/'.$supplier->id;

        $this->patchJson($url.'/inativar')->assertNoContent();

        $this->assertFalse($supplier->fresh()->active);
        $this->assertSame('52998224725', Person::findOrFail($supplier->person_id)->document);
        $this->getJson($this->listing($owner).'?status=active')->assertJsonPath('relations.total', 0);

        $this->patchJson($url.'/ativar')->assertNoContent();

        $this->assertTrue($supplier->fresh()->active);
        $this->assertDatabaseCount('suppliers', 1);

        $count = AuditRecord::count();

        $this->patchJson($url.'/ativar')->assertNoContent();

        $this->assertSame($count, AuditRecord::count());
    }

    public function test_documents_validate_checksums_and_support_alphanumeric_cnpj(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        foreach (['11111111111', '52998224724', '123'] as $document) {
            $this->postJson($this->suppliers($owner), $this->payload($document))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('document');
        }

        $payload = [
            ...$this->payload('12.ABC.345/01DE-35'),
            'person_type' => 'company',
            'trade_name' => 'Fantasia',
        ];

        $this->postJson($this->suppliers($owner), $payload)->assertNoContent();

        $this->assertSame('12ABC34501DE35', Person::whereHas('suppliers')->sole()->document);

        // O mesmo documento na conta é reaproveitado pela busca, nunca duplicado.
        $this->postJson($this->suppliers($owner), $payload)->assertUnprocessable()->assertJsonValidationErrors('document');
        $this->postJson($this->suppliers($owner).'/pessoa', ['person_type' => 'company', 'documento' => '12abc34501de35'])
            ->assertOk()
            ->assertJsonPath('person.relation', 'active');
        $this->assertDatabaseCount('suppliers', 1);

        // Outra conta aceita o mesmo documento sem revelar o cadastro existente.
        $other = $this->owner('Empresa da outra conta', '99888777');

        $this->actingAs($other)->postJson($this->suppliers($other), $payload)->assertNoContent();
        $this->assertDatabaseCount('suppliers', 2);

        $this->postJson($this->suppliers($owner), $payload)->assertNotFound();

        $supplierId = Supplier::where('account_id', $this->accountId($owner))->value('id');

        $this->getJson($this->suppliers($owner).'/'.$supplierId.'/visualizar')->assertNotFound();
    }

    public function test_supplier_permissions_are_independent_and_failures_roll_back_all_writes(): void
    {
        $owner = $this->owner();
        $payload = $this->payload();
        $role = Role::create(['account_id' => $this->accountId($owner), 'name' => 'Editor de pessoas']);
        $role->permissions()->sync(Permission::whereIn('slug', [
            'account.people.create',
            'account.people.update',
            'account.people.view',
        ])->pluck('id'));
        $editor = User::factory()->forAccount($this->accountId($owner), $role)->create();
        $count = AuditRecord::count();

        $this->actingAs($editor)->postJson($this->suppliers($owner), $payload)->assertForbidden();

        $this->assertDatabaseMissing('people', ['name' => $payload['name']]);
        $this->assertSame($count, AuditRecord::count());

        $this->actingAs($owner)->postJson($this->suppliers($owner), $payload)->assertNoContent();

        $supplier = Supplier::sole();
        $url = $this->suppliers($owner).'/'.$supplier->id;

        // Sem as permissões de Fornecedores, Pessoas não abre nem altera a relação.
        $this->actingAs($editor)->getJson($url.'/visualizar')->assertForbidden();
        $this->getJson($this->listing($owner))->assertForbidden();
        $this->patchJson($url, ['name' => 'Identidade compartilhada'])->assertForbidden();

        $role->permissions()->attach(Permission::where('slug', 'account.suppliers.update')->value('id'));

        $this->patchJson($url, ['name' => 'Identidade compartilhada'])->assertNoContent();
        $this->assertDatabaseHas('people', ['name' => 'Identidade compartilhada']);

        // Sem envio da situação, a relação não muda; o envio forjado de inativação é recusado e desfeito.
        $count = AuditRecord::count();

        $this->patchJson($url, ['name' => 'Identidade compartilhada', 'tag_ids' => []])->assertNoContent();
        $this->patchJson($url, ['name' => 'Nome revertido', 'active' => '0'])->assertForbidden();

        $this->assertTrue($supplier->fresh()->active);
        $this->assertDatabaseMissing('people', ['name' => 'Nome revertido']);
        $this->assertSame($count, AuditRecord::count());
    }

    public function test_supplier_special_definition_is_protected_and_optional_fields_are_not_required(): void
    {
        $owner = $this->owner();
        $tag = Tag::supplierTag($this->accountId($owner));

        $this->actingAs($owner)
            ->patchJson('/conta/etiquetas/'.$tag->id, [
                'name' => 'Alterada',
                'module' => 'people',
                'color' => '#123456',
                'active' => false,
            ])
            ->assertForbidden();
        $this->deleteJson('/conta/etiquetas/'.$tag->id)->assertForbidden();

        $this->postJson($this->suppliers($owner), ['name' => 'Fornecedor inicial'])->assertNoContent();

        $this->assertNull(Person::whereHas('suppliers')->sole()->document);

        $this->postJson($this->suppliers($owner), [
            'name' => 'Fornecedor inválido',
            'person_type' => 'inválido',
        ])->assertUnprocessable()->assertJsonValidationErrors('person_type');

        $this->assertDatabaseMissing('people', ['name' => 'Fornecedor inválido']);
        $this->artisan('audit:check')->assertSuccessful();
    }

    public function test_supplier_inspection_preserves_platform_author_and_read_only_rejects_writes(): void
    {
        $owner = $this->owner();
        $platform = User::factory()->create(['is_saas_admin' => true]);

        $this->actingAs($platform)
            ->postJson('/saas/contas/'.$this->accountId($owner).'/inspecao', ['user_id' => $owner->id, 'mode' => 'edit'])
            ->assertOk();

        $this->postJson($this->suppliers($owner), $this->payload())->assertNoContent();

        $event = AuditRecord::where('entity_type', 'supplier')->sole();

        $this->assertSame($platform->id, $event->actor_id);
        $this->assertSame($owner->id, $event->represented_user_id);
        $this->assertSame($this->accountId($owner), $event->account_id);
        $this->assertSame('platform-account', $event->context);
        $this->getJson('/conta/auditoria/'.$event->id.'/visualizar')->assertOk()->assertDontSee('52998224725');

        $this->postJson('/inspecao/encerrar')->assertOk();

        $this->actingAs($platform)
            ->postJson('/saas/contas/'.$this->accountId($owner).'/inspecao', ['user_id' => $owner->id, 'mode' => 'read-only'])
            ->assertOk();

        $count = AuditRecord::count();

        $this->postJson($this->suppliers($owner), [...$this->payload(''), 'name' => 'Fornecedor bloqueado'])
            ->assertForbidden();

        $this->assertDatabaseMissing('people', ['name' => 'Fornecedor bloqueado']);
        $this->assertSame($count, AuditRecord::count());
    }

    public function test_supplier_definition_does_not_convert_an_existing_custom_label(): void
    {
        $account = $this->account('Conta com etiqueta comum');
        $company = $this->company($account, 'Empresa com etiqueta comum', '44555666');
        $custom = Tag::create([
            'account_id' => $account->id,
            'module' => 'suppliers',
            'name' => 'Fornecedor',
            'color' => '#123456',
        ]);
        $owner = $this->administrator($account);
        $special = Tag::supplierTag($account->id);

        $this->assertNotSame($custom->id, $special->id);
        $this->assertNull($custom->fresh()->system_key);
        $this->assertSame('supplier', $special->system_key);

        $this->actingAs($owner)
            ->postJson('/conta/empresa/'.$company->code.'/fornecedores', [
                'name' => 'Pessoa com categoria',
                'tag_ids' => [$custom->id],
            ])
            ->assertNoContent();

        $supplier = Supplier::sole();

        $this->assertDatabaseHas('suppliers_tags', ['relation_id' => $supplier->id, 'tag_id' => $custom->id]);
        $this->assertNull($custom->fresh()->system_key);

        // A etiqueta especial não é selecionável pelo formulário.
        $this->postJson('/conta/empresa/'.$company->code.'/fornecedores', [
            'name' => 'Etiqueta especial',
            'tag_ids' => [$special->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('tag_ids');
        $this->assertDatabaseCount('suppliers', 1);
    }

    public function test_identity_and_contacts_belong_to_people_independently_of_their_roles(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $company = $this->companies[$this->accountId($owner)];
        $input = [
            'name' => 'Pessoa compartilhada',
            'person_type' => 'individual',
            'document' => '529.982.247-25',
            'phone' => '(71) 99999-9999',
            'whatsapp' => '(71) 98888-8888',
            'address' => ['city' => 'Salvador', 'state' => 'BA'],
        ];

        $this->postJson('/conta/empresa/'.$company->code.'/clientes', $input)->assertNoContent();

        $person = Person::where('name', $input['name'])->sole();

        $this->assertSame('52998224725', $person->document);
        $this->assertSame('71999999999', $person->phone);
        $this->assertSame('71988888888', $person->whatsapp);
        $this->assertDatabaseCount('suppliers', 0);

        // O fornecedor reaproveita a mesma Pessoa; a relação não guarda identificação nem endereço.
        $this->postJson($this->suppliers($owner), ['person_id' => $person->id])->assertNoContent();

        $supplier = Supplier::sole();

        $this->assertSame(1, Person::where('account_id', $this->accountId($owner))->where('document', '52998224725')->count());
        $this->assertFalse(Schema::hasColumn('suppliers', 'document'));
        $this->assertFalse(Schema::hasColumn('suppliers', 'address'));

        $this->patchJson($this->suppliers($owner).'/'.$supplier->id.'/inativar')->assertNoContent();

        $this->assertSame('52998224725', $person->fresh()->document);
        $this->assertSame('Salvador', $person->fresh()->address['city']);

        $this->postJson($this->suppliers($owner), [...$input, 'name' => 'Duplicada'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document');
        $this->postJson($this->suppliers($owner), [...$input, 'document' => '11111111111'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document');
        $this->postJson($this->suppliers($owner), [...$input, 'name' => 'Documento opcional', 'document' => '---'])
            ->assertNoContent();

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
            'account_id' => $this->accountId($owner),
            'email' => 'legado@example.test',
        ]);
        DB::table('suppliers')->insert([
            'person_id' => $personId,
            'account_id' => $this->accountId($owner),
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
        $this->assertSame($this->companies[$this->accountId($owner)]->id, $supplier->company_id);
        $this->assertSame($count, AuditRecord::count());
        $this->artisan('audit:check')->assertSuccessful();
    }

    public function test_state_and_municipal_registrations_work_for_both_person_types(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->postJson($this->suppliers($owner), [
                'name' => 'Pessoa com inscrições',
                'person_type' => 'individual',
                'state_registration' => '123456789',
                'municipal_registration' => '987654',
            ])
            ->assertNoContent();

        $person = Person::where('name', 'Pessoa com inscrições')->sole();
        $url = $this->suppliers($owner).'/'.$person->suppliers()->sole()->id;

        foreach (['individual', 'company', 'individual'] as $type) {
            $this->patchJson($url, ['person_type' => $type])->assertNoContent();

            $this->assertSame($type, $person->fresh()->person_type);
            $this->assertSame('123456789', $person->fresh()->state_registration);
            $this->assertSame('987654', $person->fresh()->municipal_registration);

            $this->getJson($url.'/visualizar')
                ->assertOk()
                ->assertJsonPath('state_registration', '123456789')
                ->assertJsonPath('municipal_registration', '987654');
        }
    }
}
