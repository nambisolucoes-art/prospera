<?php

namespace Tests\Feature;

use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Domains\Clientes\Domain\Models\Customer;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Fornecedores\Domain\Models\Supplier;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\BuildsCommercialScenario;
use Tests\TestCase;

/**
 * E07: listagens comerciais na Área de Trabalho, escritas com empresa explícita, auditoria e custo (itens 30 a 48).
 */
final class CommercialRelationWorkspaceTest extends TestCase
{
    use BuildsCommercialScenario;
    use RefreshDatabase;

    private Client $gartan;

    private Company $roupas;

    private Company $express;

    private Establishment $guanambi;

    private Establishment $janauba;

    private Establishment $jequie;

    private User $owner;

    private Person $joao;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gartan = $this->account();
        $this->roupas = $this->company($this->gartan, 'Gartan Roupas', '11222333');
        $this->express = $this->company($this->gartan, 'Gartan Express', '44555666');
        $this->guanambi = $this->roupas->primaryEstablishment()->sole();
        $this->janauba = $this->unit($this->roupas, 'Janaúba');
        $this->jequie = $this->express->primaryEstablishment()->sole();
        $this->owner = $this->administrator($this->gartan);

        $this->actingAs($this->owner)->postJson('/conta/empresa/'.$this->roupas->code.'/clientes', [
            'name' => 'João da Silva',
            'document' => '52998224725',
        ])->assertNoContent();
        $this->joao = Person::where('document', '52998224725')->sole();
        $this->postJson('/conta/empresa/'.$this->roupas->code.'/fornecedores', ['person_id' => $this->joao->id])
            ->assertNoContent();
        $this->postJson('/conta/empresa/'.$this->express->code.'/clientes', ['person_id' => $this->joao->id])
            ->assertNoContent();
    }

    /** @param list<Establishment> $units */
    private function consolidated(string $module, array $units): string
    {
        $codes = implode(',', array_map(fn (Establishment $unit) => $unit->code, $units));

        return '/conta/estabelecimento/todos/'.$module.'?unidades='.$codes;
    }

    /** @return list<string> */
    private function companiesOf(string $url): array
    {
        return collect($this->getJson($url)->assertOk()->json('relations.data'))
            ->pluck('company.code')
            ->all();
    }

    public function test_selection_considers_the_companies_of_the_units_without_repeating_relations(): void
    {
        // Itens 30 a 33.
        $this->actingAs($this->owner);
        $this->assertSame([$this->roupas->code], $this->companiesOf('/conta/estabelecimento/'.$this->janauba->code.'/clientes'));

        $sameCompany = $this->consolidated('clientes', [$this->guanambi, $this->janauba]);
        $this->getJson($sameCompany)
            ->assertJsonPath('relations.total', 1)
            ->assertJsonCount(1, 'companies')
            ->assertJsonPath('organization_context.mode', 'consolidated');

        $twoCompanies = $this->consolidated('clientes', [$this->guanambi, $this->janauba, $this->jequie]);
        $this->assertEqualsCanonicalizing([$this->roupas->code, $this->express->code], $this->companiesOf($twoCompanies));
        $this->getJson($twoCompanies)
            ->assertJsonPath('relations.total', 2)
            ->assertJsonPath('relations.data.0.person_id', $this->joao->id)
            ->assertJsonPath('relations.data.1.person_id', $this->joao->id)
            ->assertJsonCount(2, 'companies');
        $this->getJson($twoCompanies.'&empresa='.$this->express->code)->assertJsonPath('relations.total', 1);

        $this->assertSame([$this->roupas->code], $this->companiesOf($this->consolidated('fornecedores', [$this->jequie, $this->guanambi])));
        $this->getJson($this->consolidated('fornecedores', [$this->jequie]))->assertJsonPath('relations.total', 0);
        $this->getJson('/conta/estabelecimento/'.$this->jequie->code.'/fornecedores')->assertJsonPath('relations.total', 0);
    }

    public function test_selected_subset_is_intersected_with_the_operation_permission(): void
    {
        // Itens 34 e 37: Ana lista clientes na Roupas e só opera o estoque de Jequié (sem clientes).
        $customers = $this->role($this->gartan, 'Atendente', [...$this->peopleSlugs(), ...$this->commercialSlugs('customers')]);
        $unitRole = $this->role($this->gartan, 'Operador', ['account.establishments.view']);
        $ana = $this->operator($this->gartan, [
            [$customers, AccessNode::company($this->gartan->id, $this->roupas->id)],
            [$unitRole, AccessNode::establishment($this->gartan->id, $this->express->id, $this->jequie->id)],
        ]);
        $url = $this->consolidated('clientes', [$this->guanambi, $this->jequie]);

        $this->actingAs($ana)->getJson($url)
            ->assertOk()
            ->assertJsonPath('relations.total', 1)
            ->assertJsonPath('relations.data.0.company.code', $this->roupas->code)
            ->assertJsonPath('restrictedCompanies', 1)
            ->assertJsonMissing(['name' => 'Gartan Express']);
        $this->getJson($url.'&empresa='.$this->express->code)->assertNotFound();
        $this->getJson('/conta/estabelecimento/'.$this->jequie->code.'/clientes')
            ->assertOk()
            ->assertJsonPath('relations.total', 0)
            ->assertJsonPath('companies', []);

        // A unidade revogada sai da seleção na requisição seguinte.
        $this->membership($ana)->assignments()->where('establishment_id', $this->jequie->id)->delete();
        $this->getJson($url)->assertNotFound();
        $this->getJson($this->consolidated('clientes', [$this->guanambi]))->assertOk();
    }

    public function test_writes_name_the_company_and_ambiguous_writes_are_rejected(): void
    {
        // Itens 35 e 36.
        $maria = Person::create(['account_id' => $this->gartan->id, 'name' => 'Maria']);
        $this->actingAs($this->owner);

        $this->postJson($this->consolidated('clientes', [$this->guanambi, $this->jequie]), ['person_id' => $maria->id])
            ->assertStatus(405);
        $this->postJson('/conta/estabelecimento/'.$this->guanambi->code.'/clientes', ['person_id' => $maria->id])
            ->assertStatus(405);
        $this->patchJson('/conta/pessoas/'.$maria->id, ['name' => 'Maria', 'relations' => ['customer' => '1']])
            ->assertNotFound();
        $this->assertDatabaseMissing('customers', ['person_id' => $maria->id]);

        $this->postJson('/conta/empresa/'.$this->express->code.'/clientes', ['person_id' => $maria->id])->assertNoContent();
        $this->assertSame([$this->express->id], Customer::where('person_id', $maria->id)->pluck('company_id')->all());

        $this->postJson('/conta/empresa/'.$this->roupas->code.'/fornecedores', ['person_id' => $maria->id])
            ->assertNoContent();
        $this->assertSame([$this->roupas->id], Supplier::where('person_id', $maria->id)->pluck('company_id')->all());
        $this->assertSame(1, Customer::where('person_id', $maria->id)->count());
    }

    public function test_registration_pages_resolve_the_companies_from_the_url_and_require_create_access(): void
    {
        $single = '/conta/estabelecimento/'.$this->guanambi->code;
        $selection = $this->guanambi->code.','.$this->janauba->code.','.$this->jequie->code;

        $this->actingAs($this->owner)->getJson($single.'/clientes/cadastrar')
            ->assertOk()
            ->assertJsonPath('role', 'customer')
            ->assertJsonCount(1, 'companies')
            ->assertJsonPath('companies.0.code', $this->roupas->code)
            ->assertJsonMissingPath('relations');

        $this->getJson('/conta/estabelecimento/todos/fornecedores/cadastrar?unidades='.$selection)
            ->assertOk()
            ->assertJsonPath('role', 'supplier')
            ->assertJsonCount(2, 'companies');

        $listOnly = $this->role($this->gartan, 'Somente listar', ['account.customers.list']);
        $operator = $this->operator($this->gartan, [[
            $listOnly,
            AccessNode::company($this->gartan->id, $this->roupas->id),
        ]]);

        $this->actingAs($operator)->getJson($single.'/clientes/cadastrar')->assertForbidden();
    }

    public function test_commercial_registration_creates_the_person_and_only_the_role_from_the_url(): void
    {
        $base = '/conta/empresa/'.$this->roupas->code;
        $tag = Tag::create([
            'account_id' => $this->gartan->id,
            'name' => 'Contato comercial',
            'module' => 'customers',
            'color' => '#123456',
        ]);

        $this->actingAs($this->owner)->postJson($base.'/clientes', [
            'name' => 'Cliente novo',
            'document' => '39053344705',
            'email' => 'cliente@example.test',
            'address' => ['city' => 'Guanambi'],
            'tag_ids' => [$tag->id],
            'relations' => [$this->express->id => ['supplier' => true]],
        ])->assertNoContent();

        $person = Person::where('account_id', $this->gartan->id)->where('document', '39053344705')->sole();
        $this->assertSame('cliente@example.test', $person->email);
        $this->assertSame('Guanambi', $person->address['city']);
        $this->assertFalse($person->tags()->exists());
        $this->assertDatabaseHas('customers_tags', [
            'relation_id' => $person->customers()->sole()->id,
            'tag_id' => $tag->id,
        ]);
        $this->assertSame([$this->roupas->id], $person->customers()->pluck('company_id')->all());
        $this->assertFalse($person->suppliers()->exists());

        $this->postJson($base.'/fornecedores', ['name' => 'Fornecedor novo'])->assertNoContent();
        $supplier = Person::where('account_id', $this->gartan->id)->where('name', 'Fornecedor novo')->sole();
        $this->assertSame([$this->roupas->id], $supplier->suppliers()->pluck('company_id')->all());
        $this->assertFalse($supplier->customers()->exists());

        $this->postJson($base.'/fornecedores/pessoa', ['person_type' => 'individual', 'documento' => $person->document])
            ->assertOk()
            ->assertJsonPath('person.other_role', 'Cliente')
            ->assertJsonPath('person.central.address.city', 'Guanambi');

        $this->postJson($base.'/fornecedores', [
            'name' => 'Acesso indevido',
            'tag_ids' => [Tag::userTag($this->gartan->id)->id],
        ])->assertJsonValidationErrors('tag_ids');
        $this->assertDatabaseMissing('people', ['name' => 'Acesso indevido']);

        $this->postJson($base.'/fornecedores', [
            'name' => 'Cadastro duplicado',
            'document' => $person->document,
        ])->assertJsonValidationErrors('document');
        $this->postJson($base.'/fornecedores', ['person_id' => $person->id, 'tag_ids' => [$tag->id]])
            ->assertJsonValidationErrors('tag_ids');
        $this->assertFalse($person->suppliers()->exists());
        $this->postJson($base.'/fornecedores', ['person_id' => $person->id])->assertNoContent();
        $this->assertSame([$this->roupas->id], $person->suppliers()->pluck('company_id')->all());
    }

    public function test_commercial_reuse_edits_authorized_fields_and_preserves_identity_and_document(): void
    {
        $endpoint = '/conta/empresa/'.$this->express->code.'/fornecedores';
        $identity = User::factory()->forAccount($this->gartan->id)->create();
        $membership = $this->membership($identity);
        $membership->forceFill(['person_id' => $this->joao->id])->saveOrFail();
        $tag = Tag::userTag($this->gartan->id);
        $this->joao->assignTags([$tag->id]);

        $role = $this->role($this->gartan, 'Cadastro local', [
            'account.suppliers.create', 'account.people.view', 'account.people.update',
        ]);
        $local = $this->operator($this->gartan, [[$role, AccessNode::company($this->gartan->id, $this->express->id)]]);

        $this->actingAs($local)
            ->postJson($endpoint.'/pessoa', ['person_type' => 'individual', 'documento' => $this->joao->document])
            ->assertJsonPath('person.can_edit_identification', false)
            ->assertJsonPath('person.can_edit_contacts', true);

        $this->postJson($endpoint, ['person_id' => $this->joao->id, 'name' => 'Outro nome'])->assertForbidden();
        $this->postJson($endpoint, ['person_id' => $this->joao->id, 'document' => '39053344705'])
            ->assertJsonValidationErrors('document');
        $this->assertFalse($this->joao->suppliers()->where('company_id', $this->express->id)->exists());

        $this->postJson($endpoint, [
            'person_id' => $this->joao->id,
            'address' => ['postal_code' => '46430-000', 'neighborhood' => 'Centro', 'street' => 'Rua de Teste'],
            'email' => 'novo-contato@example.test',
        ])->assertNoContent();

        $person = $this->joao->fresh();
        $this->assertSame('52998224725', $person->document);
        $this->assertSame('46430000', $person->address['postal_code']);
        $this->assertSame('Centro', $person->address['neighborhood']);
        $this->assertSame('Rua de Teste', $person->address['street']);
        $this->assertSame('novo-contato@example.test', $person->email);
        $this->assertSame($identity->email, $identity->fresh()->email);
        $this->assertSame('active', $membership->fresh()->status);
        $this->assertSame([$tag->id], $person->tags()->pluck('tags.id')->all());
        $this->assertTrue($person->suppliers()->where('company_id', $this->express->id)->exists());
    }

    public function test_two_tabs_reload_and_e06_preferences_are_preserved(): void
    {
        // Itens 38 a 40.
        $this->actingAs($this->owner);
        $tabA = '/conta/estabelecimento/'.$this->guanambi->code.'/clientes';
        $tabB = $this->consolidated('clientes', [$this->guanambi, $this->jequie]);

        foreach (range(1, 3) as $_) {
            $this->assertSame([$this->roupas->code], $this->companiesOf($tabA));
            $this->assertCount(2, $this->companiesOf($tabB));
        }

        $this->getJson($tabB)->assertJsonPath('organization_context.selected_codes', [
            $this->guanambi->code,
            $this->jequie->code,
        ]);
        $this->assertNull($this->membership($this->owner)->getAttribute('workspace_establishments'));

        $codes = $this->guanambi->code.','.$this->jequie->code;
        $this->get('/conta/estabelecimento?unidades='.$codes.'&para=clientes')
            ->assertRedirect($this->accountPath('/conta/estabelecimento/todos/clientes?unidades='.$codes));
        $this->get('/conta/estabelecimento?unidades='.$this->janauba->code.'&para=fornecedores')
            ->assertRedirect($this->accountPath('/conta/estabelecimento/'.$this->janauba->code.'/fornecedores'));

        $this->putJson('/conta/area-de-trabalho/padrao', ['unidades' => [$this->janauba->code]])->assertNoContent();
        $this->get('/conta/estabelecimento?para=clientes')
            ->assertRedirect($this->accountPath('/conta/estabelecimento/'.$this->janauba->code.'/clientes'));
        $this->assertSame([$this->janauba->code], json_decode(
            (string) DB::table('account_memberships')->where('user_id', $this->owner->id)->value('workspace_establishments'),
        ));
    }

    public function test_audit_records_the_company_and_skips_operations_without_changes(): void
    {
        // Itens 41, 43 e 44.
        $maria = Person::create(['account_id' => $this->gartan->id, 'name' => 'Maria']);
        $this->actingAs($this->owner)
            ->postJson('/conta/empresa/'.$this->express->code.'/fornecedores', ['person_id' => $maria->id])
            ->assertNoContent();
        $event = AuditRecord::where('entity_type', 'supplier')->where('entity_id', Supplier::where('person_id', $maria->id)->value('id'))->sole();
        $this->assertSame($this->express->id, $event->company_id);
        $this->assertNull($event->establishment_id);
        $this->assertSame('Maria (Gartan Express)', $event->entity_name);
        $this->assertSame($this->owner->id, $event->actor_id);

        $relation = Customer::where('company_id', $this->roupas->id)->sole();
        $activate = '/conta/empresa/'.$this->roupas->code.'/clientes/'.$relation->id.'/ativar';
        $count = AuditRecord::count();
        $this->patchJson($activate)->assertNoContent();
        $this->assertSame($count, AuditRecord::count());

        $this->patchJson('/conta/empresa/'.$this->roupas->code.'/clientes/'.$relation->id.'/inativar')->assertNoContent();
        $change = AuditRecord::where('entity_type', 'customer')->where('event', 'updated')->sole();
        $this->assertSame($this->roupas->id, $change->company_id);
        $this->assertSame(['label' => 'Situação', 'before' => true, 'after' => false], $change->changes['active']);
        $this->assertTrue(Customer::where('company_id', $this->express->id)->sole()->active);

        // Auditor da Roupas vê os eventos da Roupas, nunca os da Express.
        $auditor = $this->operator($this->gartan, [[
            $this->role($this->gartan, 'Auditor', ['account.audit.list', 'account.audit.view']),
            AccessNode::company($this->gartan->id, $this->roupas->id),
        ]]);
        $names = collect($this->actingAs($auditor)->getJson('/conta/auditoria?per_page=100')->json('records.data'))
            ->pluck('name');
        $this->assertTrue($names->contains('João da Silva (Gartan Roupas)'));
        $this->assertFalse($names->contains(fn ($name) => str_contains((string) $name, 'Gartan Express')));
    }

    public function test_listings_have_constant_queries_regardless_of_rows(): void
    {
        // Item 45: sem N+1 nas etiquetas da listagem comercial.
        $this->actingAs($this->owner);
        $count = function (string $url): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson($url)->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $this->createRelations(3);
        $listSmall = $count($this->consolidated('clientes', [$this->guanambi, $this->jequie]).'&per_page=50');
        $this->createRelations(40);
        $listLarge = $count($this->consolidated('clientes', [$this->guanambi, $this->jequie]).'&per_page=50');

        $this->assertSame($listSmall, $listLarge);
        $this->assertLessThanOrEqual(12, $listLarge);
    }

    private function createRelations(int $amount): void
    {
        foreach (range(1, $amount) as $_) {
            $person = Person::create(['account_id' => $this->gartan->id, 'name' => fake()->name()]);

            foreach ([$this->roupas, $this->express] as $company) {
                Customer::create([
                    'account_id' => $this->gartan->id,
                    'company_id' => $company->id,
                    'person_id' => $person->id,
                    'active' => true,
                ]);
            }
        }
    }
}
