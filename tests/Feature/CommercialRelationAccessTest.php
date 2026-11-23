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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Concerns\BuildsCommercialScenario;
use Tests\TestCase;

/**
 * E07: autorização contextual das relações comerciais e proteção dos dados centrais (itens 12 a 29).
 */
final class CommercialRelationAccessTest extends TestCase
{
    use BuildsCommercialScenario;
    use RefreshDatabase;

    private Client $gartan;

    private Company $roupas;

    private Company $express;

    private Establishment $janauba;

    private User $owner;

    private Person $joao;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gartan = $this->account();
        $this->roupas = $this->company($this->gartan, 'Gartan Roupas', '11222333');
        $this->express = $this->company($this->gartan, 'Gartan Express', '44555666');
        $this->janauba = $this->unit($this->roupas, 'Janaúba');
        $this->owner = $this->administrator($this->gartan);

        // João: cliente e fornecedor da Gartan Roupas, cliente da Gartan Express, pelas entradas comerciais.
        $this->actingAs($this->owner)->postJson('/conta/empresa/'.$this->roupas->code.'/clientes', [
            'name' => 'João da Silva',
            'document' => '52998224725',
            'email' => 'joao@example.test',
            'add_companies' => [$this->express->code],
        ])->assertNoContent();
        $this->joao = Person::where('document', '52998224725')->sole();

        $this->postJson('/conta/empresa/'.$this->roupas->code.'/fornecedores', ['person_id' => $this->joao->id])
            ->assertNoContent();
    }

    private function relationUrl(Company $company, string $segment = 'clientes'): string
    {
        $relations = $segment === 'clientes' ? $this->joao->customers() : $this->joao->suppliers();
        $relation = $relations->where('company_id', $company->id)->sole();

        return '/conta/empresa/'.$company->code.'/'.$segment.'/'.$relation->id;
    }

    private function editForm(Company $company, string $segment = 'clientes'): string
    {
        $relations = $segment === 'clientes' ? $this->joao->customers() : $this->joao->suppliers();
        $relation = $relations->where('company_id', $company->id)->sole();

        return '/conta/estabelecimento/'.$this->unitCode($company).'/'.$segment.'/'.$company->code.'/'.$relation->id.'/editar';
    }

    private function companyNode(Company $company): AccessNode
    {
        return AccessNode::company($this->gartan->id, $company->id);
    }

    private function unitCode(Company $company): string
    {
        return $company->primaryEstablishment()->sole()->code;
    }

    /**
     * Dados centrais de João editados pela ficha de cliente da Gartan Roupas.
     *
     * @param  array<string,mixed>  $changes
     */
    private function editJoao(array $changes): TestResponse
    {
        return $this->patchJson($this->relationUrl($this->roupas), $changes);
    }

    public function test_full_administrator_sees_and_maintains_every_company(): void
    {
        // Item 12.
        $this->actingAs($this->owner);

        foreach ([[$this->roupas, 'clientes'], [$this->roupas, 'fornecedores'], [$this->express, 'clientes']] as [$company, $segment]) {
            $this->getJson($this->relationUrl($company, $segment).'/visualizar')
                ->assertOk()
                ->assertJsonPath('document', '52998224725');
        }

        $this->getJson('/conta/estabelecimento/'.$this->unitCode($this->roupas).'/clientes')
            ->assertJsonPath('relations.total', 1);
        $this->getJson('/conta/estabelecimento/'.$this->unitCode($this->express).'/clientes')
            ->assertJsonPath('relations.total', 1);
        $this->getJson($this->editForm($this->roupas))
            ->assertJsonPath('editing.can_edit_identification', true)
            ->assertJsonCount(2, 'companyOptions');

        $this->editJoao(['name' => 'João Silva'])->assertNoContent();

        $this->assertSame('João Silva', $this->joao->fresh()->name);
    }

    public function test_person_edit_only_prefills_tags_that_can_be_submitted(): void
    {
        $identity = User::factory()->forAccount($this->gartan->id)->create();
        $membership = $this->membership($identity);
        $membership->forceFill(['person_id' => $this->joao->id])->saveOrFail();
        $userTag = Tag::userTag($this->gartan->id);
        $this->joao->tags()->syncWithoutDetaching([$userTag->id]);

        // A ficha comercial só oferece e preenche etiquetas da relação; Usuário nunca é enviada por ela.
        $form = $this->actingAs($this->owner)->getJson($this->editForm($this->roupas))
            ->assertOk()
            ->assertJsonPath('editing.tag_ids', [])
            ->assertJsonMissing(['id' => $userTag->id, 'name' => $userTag->name]);

        $this->editJoao(['tag_ids' => $form->json('editing.tag_ids')])->assertNoContent();

        $this->assertSame('active', $membership->fresh()->status);
        $this->assertTrue($this->joao->tags()->whereKey($userTag->id)->exists());

        $this->getJson($this->relationUrl($this->roupas).'/visualizar')
            ->assertOk()
            ->assertJsonPath('tags.0.name', 'Cliente');
        $this->getJson($this->relationUrl($this->roupas, 'fornecedores').'/visualizar')
            ->assertOk()
            ->assertJsonPath('tags.0.name', 'Fornecedor');
    }

    public function test_company_administrator_maintains_only_its_company_and_shared_contacts(): void
    {
        // Itens 13, 21 a 23: Ana administra clientes e pessoas na Gartan Roupas.
        $role = $this->role($this->gartan, 'Gerente comercial', [
            ...$this->peopleSlugs(),
            ...$this->commercialSlugs('customers'),
            'account.customers.update',
            'account.companies.update',
        ]);
        $ana = $this->operator($this->gartan, [[$role, $this->companyNode($this->roupas)]]);

        $this->actingAs($ana)->getJson($this->relationUrl($this->roupas).'/visualizar')->assertOk();
        $this->getJson($this->relationUrl($this->roupas, 'fornecedores').'/visualizar')->assertForbidden();
        $this->getJson($this->relationUrl($this->express).'/visualizar')->assertNotFound();

        $this->getJson($this->editForm($this->roupas))
            ->assertOk()
            ->assertJsonPath('editing.can_edit_identification', false)
            ->assertJsonPath('editing.can_edit_contacts', true)
            ->assertJsonCount(1, 'companyOptions')
            ->assertJsonPath('companyOptions.0.code', $this->roupas->code);

        // Identificação compartilhada com a Gartan Express: recusada mesmo com o campo enviado à força.
        $this->editJoao(['name' => 'Nome trocado'])->assertForbidden();
        $this->editJoao(['document' => '39053344705'])->assertForbidden();
        $this->assertSame('João da Silva', $this->joao->fresh()->name);

        // Contato central: uma empresa coberta basta, e a mudança vale para todas.
        $this->editJoao(['phone' => '71988887777'])->assertNoContent();
        $this->assertSame('71988887777', $this->joao->fresh()->phone);

        // Relação de outra empresa: pela seleção forjada (403) e por ID na URL (404), sem efeito.
        $this->editJoao(['remove_companies' => [$this->express->code]])->assertForbidden();
        $expressCustomer = Customer::where('company_id', $this->express->id)->sole();
        $this->patchJson('/conta/empresa/'.$this->roupas->code.'/clientes/'.$expressCustomer->id.'/inativar')
            ->assertNotFound();
        $this->patchJson('/conta/empresa/'.$this->express->code.'/clientes/'.$expressCustomer->id.'/inativar')
            ->assertNotFound();
        $this->assertTrue($expressCustomer->fresh()->active);

        // Na própria empresa, a relação muda sozinha.
        $this->patchJson($this->relationUrl($this->roupas).'/inativar')->assertNoContent();
        $this->assertFalse(Customer::where('company_id', $this->roupas->id)->sole()->active);
        $this->assertTrue($expressCustomer->fresh()->active);
        $this->assertTrue(Supplier::sole()->active);

        // Consulta da outra empresa: a unidade não é navegável e o filtro de empresa é recusado.
        $this->getJson('/conta/estabelecimento/'.$this->unitCode($this->express).'/clientes')->assertNotFound();
        $this->getJson('/conta/estabelecimento/'.$this->unitCode($this->roupas).'/clientes?empresa='.$this->express->code)
            ->assertNotFound();
    }

    public function test_central_authority_with_three_companies_inactive_relations_and_linked_identity(): void
    {
        $third = $this->company($this->gartan, 'Gartan Terceira', '66777888');
        $this->actingAs($this->owner)
            ->postJson('/conta/empresa/'.$third->code.'/fornecedores', ['person_id' => $this->joao->id])
            ->assertNoContent();
        $thirdSupplier = $this->relationUrl($third, 'fornecedores');

        $identity = User::factory()->forAccount($this->gartan->id)->create();
        $this->membership($identity)->forceFill(['person_id' => $this->joao->id])->saveOrFail();

        $role = $this->role($this->gartan, 'Pessoas da Roupas', [...$this->peopleSlugs(), 'account.customers.update']);
        $ana = $this->operator($this->gartan, [[$role, $this->companyNode($this->roupas)]]);

        $this->actingAs($ana);
        foreach ([
            ['name' => 'Nome indevido'],
            ['document' => '39053344705'],
            ['state_registration' => '12345'],
            ['municipal_registration' => '67890'],
        ] as $fields) {
            $this->editJoao($fields)->assertForbidden();
        }

        $this->editJoao([
            'phone' => '71988887777',
            'email' => 'novo-contato@example.test',
            'address' => ['city' => 'Guanambi'],
        ])->assertNoContent();
        $this->assertSame('novo-contato@example.test', $this->joao->fresh()->email);
        $this->assertSame($identity->email, $identity->fresh()->email);
        $this->assertSame($identity->name, $identity->fresh()->name);
        $this->assertSame('active', $this->membership($identity)->status);

        // Os contatos centrais valem para todas as empresas que atendem João.
        $readerRole = $this->role($this->gartan, 'Leitura comercial', [
            'account.people.view', 'account.customers.view', 'account.suppliers.view',
        ]);
        foreach ([[$this->roupas, 'clientes'], [$this->express, 'clientes'], [$third, 'fornecedores']] as [$company, $segment]) {
            $reader = $this->operator($this->gartan, [[$readerRole, $this->companyNode($company)]]);
            $this->actingAs($reader)->getJson($this->relationUrl($company, $segment).'/visualizar')
                ->assertOk()
                ->assertJsonPath('email', 'novo-contato@example.test')
                ->assertJsonPath('phone', '71988887777')
                ->assertJsonPath('address.city', 'Guanambi');
        }

        // Inativas as relações das outras empresas, a Roupas cobre todas e Ana altera a identificação.
        $this->actingAs($this->owner);
        $this->patchJson($this->relationUrl($this->express).'/inativar')->assertNoContent();
        $this->patchJson($thirdSupplier.'/inativar')->assertNoContent();

        $this->actingAs($ana)->editJoao(['name' => 'João Silva'])->assertNoContent();
        $this->assertSame('João Silva', $this->joao->fresh()->name);
        $this->assertSame($identity->name, $identity->fresh()->name);

        $this->actingAs($this->owner)->editJoao([
            'person_type' => 'company',
            'document' => '11222333000181',
            'trade_name' => 'Nome fantasia central',
        ])->assertNoContent();
        $this->patchJson($thirdSupplier.'/ativar')->assertNoContent();

        $this->actingAs($ana)->editJoao(['trade_name' => 'Nome fantasia indevido'])->assertForbidden();
        $this->assertSame('Nome fantasia central', $this->joao->fresh()->trade_name);

        // Só visualizar Pessoas não altera nem cadastra.
        $readOnly = $this->role($this->gartan, 'Leitura central', ['account.people.view']);
        $reader = $this->operator($this->gartan, [[$readOnly, $this->companyNode($this->roupas)]]);
        $this->actingAs($reader)->editJoao(['name' => 'Bloqueado'])->assertForbidden();
        $this->postJson('/conta/empresa/'.$this->roupas->code.'/clientes', ['name' => 'Bloqueado'])->assertForbidden();
        $this->assertSame('João Silva', $this->joao->fresh()->name);
        $this->assertDatabaseMissing('people', ['name' => 'Bloqueado']);
    }

    public function test_people_role_filters_require_list_and_view_on_the_same_company(): void
    {
        // Listar e visualizar são avaliados na mesma empresa da linha.
        $list = $this->role($this->gartan, 'Lista comercial', ['account.customers.list', 'account.suppliers.list']);
        $view = $this->role($this->gartan, 'Visualização comercial', ['account.customers.view', 'account.suppliers.view']);
        $operator = $this->operator($this->gartan, [
            [$list, $this->companyNode($this->roupas)],
            [$view, $this->companyNode($this->express)],
        ]);

        $this->actingAs($operator)->getJson('/conta/estabelecimento/'.$this->unitCode($this->roupas).'/clientes')
            ->assertOk()
            ->assertJsonPath('relations.total', 1)
            ->assertJsonPath('relations.data.0.can_view', false);
        $this->getJson($this->relationUrl($this->roupas).'/visualizar')->assertForbidden();
        $this->getJson('/conta/estabelecimento/'.$this->unitCode($this->express).'/clientes')
            ->assertJsonPath('relations.total', 0);

        $both = $this->role($this->gartan, 'Lista com visualização', [
            'account.customers.list', 'account.customers.view',
        ]);
        $operator = $this->operator($this->gartan, [
            [$list, $this->companyNode($this->roupas)],
            [$both, $this->companyNode($this->express)],
        ]);

        $this->actingAs($operator)->getJson('/conta/estabelecimento/'.$this->unitCode($this->express).'/clientes')
            ->assertJsonPath('relations.total', 1)
            ->assertJsonPath('relations.data.0.person_id', $this->joao->id)
            ->assertJsonPath('relations.data.0.can_view', true)
            ->assertJsonPath('relations.data.0.tags.0.name', 'Cliente');
        $this->getJson('/conta/estabelecimento/'.$this->unitCode($this->express).'/clientes?empresa='.$this->roupas->code)
            ->assertNotFound();
    }

    public function test_establishment_administrator_does_not_receive_company_commercial_authority(): void
    {
        // Item 14: o alvo das relações é a empresa; a atribuição da unidade não o alcança.
        $role = $this->role($this->gartan, 'Administrador da unidade', [
            ...$this->peopleSlugs(),
            ...$this->commercialSlugs('customers'),
            'account.customers.update',
            'account.establishments.update',
        ]);
        $node = AccessNode::establishment($this->gartan->id, $this->roupas->id, $this->janauba->id);
        $unitAdmin = $this->operator($this->gartan, [[$role, $node]]);

        $this->actingAs($unitAdmin)->getJson('/conta/estabelecimento/'.$this->janauba->code.'/clientes')->assertForbidden();
        $this->getJson($this->relationUrl($this->roupas).'/visualizar')->assertNotFound();
        $this->postJson('/conta/empresa/'.$this->roupas->code.'/clientes', ['person_id' => $this->joao->id])
            ->assertNotFound();
        $this->patchJson($this->relationUrl($this->roupas).'/inativar')->assertNotFound();
        $this->editJoao(['whatsapp' => '71977776666'])->assertNotFound();

        $this->assertTrue(Customer::where('company_id', $this->roupas->id)->sole()->active);
        $this->assertNull($this->joao->fresh()->whatsapp);
    }

    public function test_role_specific_operators_and_different_roles_per_company(): void
    {
        // Itens 15 a 17: clientes na Roupas, fornecedores na Express.
        $customers = $this->role($this->gartan, 'Atendente', [...$this->peopleSlugs(), ...$this->commercialSlugs('customers')]);
        $suppliers = $this->role($this->gartan, 'Compras', [...$this->peopleSlugs(), ...$this->commercialSlugs('suppliers')]);
        $ana = $this->operator($this->gartan, [
            [$customers, $this->companyNode($this->roupas)],
            [$suppliers, $this->companyNode($this->express)],
        ]);

        $this->actingAs($ana)->getJson($this->relationUrl($this->roupas).'/visualizar')->assertOk();
        $this->getJson($this->relationUrl($this->roupas, 'fornecedores').'/visualizar')->assertForbidden();
        // Na Express, João é só cliente: Ana não vê essa relação.
        $this->getJson($this->relationUrl($this->express).'/visualizar')->assertForbidden();

        $this->getJson('/conta/estabelecimento/'.$this->unitCode($this->express).'/fornecedores')
            ->assertJsonPath('relations.total', 0);
        $this->getJson('/conta/estabelecimento/'.$this->unitCode($this->roupas).'/clientes')
            ->assertJsonPath('relations.total', 1);

        // Fornecedor na Express: criado pela própria Ana, sem tocar no cliente da Express.
        $this->postJson('/conta/empresa/'.$this->express->code.'/fornecedores', ['person_id' => $this->joao->id])
            ->assertNoContent();
        $this->assertTrue(Supplier::where('company_id', $this->express->id)->sole()->active);
        $this->patchJson($this->relationUrl($this->express).'/inativar')->assertForbidden();
        $this->assertTrue(Customer::where('company_id', $this->express->id)->sole()->active);
        $this->getJson('/conta/estabelecimento/'.$this->unitCode($this->express).'/fornecedores')
            ->assertJsonPath('relations.total', 1);
    }

    public function test_unauthorized_suspended_and_revoked_operators_lose_access_on_the_next_request(): void
    {
        // Itens 18 a 20.
        $reader = $this->role($this->gartan, 'Leitor de pessoas', ['account.people.view']);
        $viewer = $this->operator($this->gartan, [[$reader, $this->companyNode($this->roupas)]]);
        $this->actingAs($viewer)->getJson($this->relationUrl($this->roupas).'/visualizar')->assertForbidden();
        $this->getJson('/conta/estabelecimento/'.$this->unitCode($this->roupas).'/clientes')->assertForbidden();
        $base = '/conta/empresa/'.$this->roupas->code.'/clientes';
        $relation = Customer::where('company_id', $this->roupas->id)->sole();
        $this->patchJson($base.'/'.$relation->id.'/ativar')->assertForbidden();
        $this->patchJson($base.'/999999/ativar')->assertForbidden();
        $this->postJson($base, ['person_id' => $this->joao->id])->assertForbidden();
        $this->postJson($base, ['person_id' => 999999])->assertForbidden();
        $this->assertTrue($relation->fresh()->active);

        $role = $this->role($this->gartan, 'Atendente', [...$this->peopleSlugs(), ...$this->commercialSlugs('customers')]);
        $ana = $this->operator($this->gartan, [[$role, $this->companyNode($this->roupas)]]);
        $url = '/conta/estabelecimento/'.$this->unitCode($this->roupas).'/clientes';
        $this->actingAs($ana)->getJson($url)->assertOk()->assertJsonPath('relations.total', 1);

        $this->membership($ana)->assignments()->delete();
        $this->getJson($url)->assertNotFound();
        $this->getJson($this->relationUrl($this->roupas).'/visualizar')->assertNotFound();

        $this->operator($this->gartan, []);
        $suspended = $this->operator($this->gartan, [[$role, $this->companyNode($this->roupas)]]);
        $this->membership($suspended)->forceFill(['status' => 'suspended'])->saveOrFail();
        $this->actingAs($suspended)->getJson($url)->assertForbidden();
        $this->getJson($this->relationUrl($this->roupas).'/visualizar')->assertForbidden();
    }

    public function test_parameters_from_other_accounts_or_forged_values_are_rejected(): void
    {
        // Item 24.
        $other = $this->account('Outra conta');
        $foreign = $this->company($other, 'Empresa de fora', '12312312');
        $outsider = Person::create(['account_id' => $other->id, 'name' => 'Pessoa de fora']);
        $this->actingAs($this->owner);
        $count = AuditRecord::count();

        $this->editJoao(['add_companies' => [$foreign->code]])->assertNotFound();
        $this->editJoao(['add_companies' => ['../x']])->assertUnprocessable()->assertJsonValidationErrors('add_companies.0');
        $this->editJoao(['add_companies' => [$this->roupas->code]])->assertUnprocessable();
        $this->editJoao(['add_companies' => 'abc'])->assertUnprocessable();
        $this->editJoao(['active' => 'talvez'])->assertUnprocessable()->assertJsonValidationErrors('active');
        $this->getJson('/conta/estabelecimento/'.$this->unitCode($this->roupas).'/clientes?empresa='.$this->express->code)
            ->assertNotFound();
        $this->getJson('/conta/estabelecimento/'.$this->unitCode($this->roupas).'/clientes?empresa=../x')
            ->assertNotFound();
        $this->postJson('/conta/empresa/'.$foreign->code.'/clientes', ['person_id' => $this->joao->id])
            ->assertNotFound();

        $this->postJson('/conta/empresa/'.$this->express->code.'/fornecedores', ['person_id' => $outsider->id])
            ->assertNotFound();

        $this->assertSame($count, AuditRecord::count());
        $this->assertDatabaseCount('customers', 2);
        $this->assertDatabaseCount('suppliers', 1);
    }

    public function test_safe_discovery_reuses_the_central_person_without_revealing_other_companies(): void
    {
        // Item 25: operador da Gartan Express cadastra João como fornecedor sem duplicar a pessoa.
        $role = $this->role($this->gartan, 'Compras Express', ['account.suppliers.create', 'account.suppliers.list']);
        $operator = $this->operator($this->gartan, [[$role, $this->companyNode($this->express)]]);
        $people = Person::count();
        $base = '/conta/empresa/'.$this->express->code.'/fornecedores';

        $found = $this->actingAs($operator)->postJson($base.'/pessoa', ['person_type' => 'individual', 'documento' => '529.982.247-25'])->assertOk();
        $found->assertHeader('Cache-Control', 'no-store, private')->assertExactJson(['person' => [
            'id' => $this->joao->id,
            'name' => 'João da Silva',
            'person_type' => 'individual',
            'relation' => null,
        ]]);
        $this->postJson($base.'/pessoa', ['person_type' => 'individual', 'documento' => '111.111.111-11'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('documento');
        $this->postJson($base.'/pessoa', ['person_type' => 'individual', 'documento' => ['52998224725']])->assertJsonValidationErrors('documento');
        $this->postJson($base.'/pessoa', ['person_type' => 'individual', 'documento' => '39053344705'])->assertExactJson(['person' => null]);

        // Sem permissão de cadastrar clientes, a descoberta de clientes não responde.
        $this->postJson('/conta/empresa/'.$this->express->code.'/clientes/pessoa', ['person_type' => 'individual', 'documento' => 'inválido'])
            ->assertForbidden();

        $this->postJson($base, ['name' => 'Pessoa não autorizada'])->assertForbidden();
        $this->postJson($base, ['person_id' => $this->joao->id])->assertNoContent();
        $this->assertSame($people, Person::count());
        $this->assertTrue(Supplier::where('company_id', $this->express->id)->sole()->active);
        $this->postJson($base, ['person_id' => $this->joao->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('person_id');
        $this->postJson($base.'/pessoa', ['person_type' => 'individual', 'documento' => '52998224725'])->assertJsonPath('person.relation', 'active');
        $this->assertDatabaseCount('suppliers', 2);

        // A listagem do operador mostra só a relação da própria empresa.
        $this->getJson('/conta/estabelecimento/'.$this->unitCode($this->express).'/fornecedores')
            ->assertOk()
            ->assertJsonPath('relations.total', 1)
            ->assertJsonPath('relations.data.0.company.code', $this->express->code)
            ->assertJsonMissing(['name' => 'Gartan Roupas']);
    }

    public function test_commercial_edit_preserves_status_and_uses_independent_company_permissions(): void
    {
        $customer = $this->joao->customers()->where('company_id', $this->roupas->id)->sole();
        $otherCustomer = $this->joao->customers()->where('company_id', $this->express->id)->sole();
        $supplier = $this->joao->suppliers()->sole();
        $tag = Tag::create([
            'account_id' => $this->gartan->id,
            'name' => 'Preferencial',
            'color' => '#123456',
            'module' => 'customers',
            'active' => true,
        ]);
        $companyUrl = '/conta/empresa/'.$this->roupas->code;
        $unitUrl = '/conta/estabelecimento/'.$this->unitCode($this->roupas);
        $customerUrl = $companyUrl.'/clientes/'.$customer->id;
        $customerForm = $unitUrl.'/clientes/'.$this->roupas->code.'/'.$customer->id.'/editar';
        $supplierUrl = $companyUrl.'/fornecedores/'.$supplier->id;

        $this->actingAs($this->owner)->getJson($customerForm)
            ->assertOk()
            ->assertJsonPath('editing.central.name', $this->joao->name);
        $this->getJson($unitUrl.'/fornecedores/'.$this->roupas->code.'/'.$supplier->id.'/editar')
            ->assertOk()
            ->assertJsonPath('editing.id', $supplier->id);
        $this->getJson($unitUrl.'/clientes/'.$this->express->code.'/'.$otherCustomer->id.'/editar')->assertNotFound();
        $this->patchJson($companyUrl.'/clientes/'.$otherCustomer->id, ['tag_ids' => []])->assertNotFound();

        $role = $this->role($this->gartan, 'Edição de clientes', ['account.customers.update']);
        $operator = $this->operator($this->gartan, [[$role, $this->companyNode($this->roupas)]]);
        $this->actingAs($operator)->getJson($customerForm)->assertOk()->assertJsonPath('editing.central', null);
        $this->patchJson($supplierUrl, ['tag_ids' => []])->assertForbidden();
        $this->patchJson($customerUrl, ['name' => 'Alteração proibida'])->assertForbidden();
        // Desde a edição com situação, inativar exige a própria permissão e desfaz o envio inteiro.
        $this->patchJson($customerUrl, ['tag_ids' => [$tag->id], 'active' => false])->assertForbidden();
        $this->assertDatabaseMissing('customers_tags', ['relation_id' => $customer->id]);
        $this->patchJson($customerUrl, ['tag_ids' => [$tag->id]])->assertNoContent();
        $this->assertTrue($customer->refresh()->active);
        $this->assertTrue($otherCustomer->refresh()->active);
        $this->assertTrue($supplier->refresh()->active);
        $this->assertDatabaseHas('customers_tags', [
            'relation_id' => $customer->id,
            'company_id' => $this->roupas->id,
            'tag_id' => $tag->id,
        ]);
        $this->assertDatabaseHas('audit_records', [
            'entity_type' => 'customer',
            'company_id' => $this->roupas->id,
            'entity_id' => $customer->id,
        ]);
        $this->patchJson($customerUrl, ['edit_tags' => true])->assertNoContent();
        $this->assertDatabaseMissing('customers_tags', ['relation_id' => $customer->id]);

        $this->actingAs($this->owner)->patchJson($supplierUrl, ['phone' => '77999991111'])->assertNoContent();
        $this->assertSame('77999991111', $this->joao->refresh()->phone);
    }

    public function test_discovery_uses_the_selected_person_type_for_cpf_and_cnpj(): void
    {
        $companyPerson = Person::create([
            'account_id' => $this->gartan->id,
            'name' => 'Empresa de teste',
            'person_type' => 'company',
            'document' => '12ABC34501DE35',
        ]);
        $endpoint = '/conta/empresa/'.$this->roupas->code.'/clientes/pessoa';
        $this->actingAs($this->owner);

        $this->postJson($endpoint, ['person_type' => 'company', 'documento' => '529.982.247-25'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.documento.0', 'Informe um CNPJ válido.')
            ->assertJsonMissingPath('person');
        $this->postJson($endpoint, ['person_type' => 'individual', 'documento' => '12.abc.345/01de-35'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.documento.0', 'Informe um CPF válido.')
            ->assertJsonMissingPath('person');
        $this->postJson($endpoint, ['person_type' => 'company', 'documento' => '12.abc.345/01de-35'])
            ->assertOk()
            ->assertJsonPath('person.id', $companyPerson->id)
            ->assertJsonPath('person.person_type', 'company');
        $this->postJson($endpoint, ['documento' => '52998224725'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('person_type');
    }

    public function test_discovery_requires_a_body_and_keeps_the_document_out_of_errors_and_audit(): void
    {
        $endpoint = '/conta/empresa/'.$this->roupas->code.'/clientes/pessoa';
        $document = '52998224725';

        $this->actingAsGuest();
        $this->postJson($endpoint, ['person_type' => 'individual', 'documento' => $document])->assertUnauthorized();
        $this->actingAs($this->owner)->getJson($endpoint.'?documento='.$document)->assertMethodNotAllowed();
        $this->postJson($endpoint.'?documento='.$document, ['person_type' => 'individual'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('documento')
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->post($endpoint, ['person_type' => 'individual', 'documento' => '11111111111'])
            ->assertUnprocessable()
            ->assertDontSee('11111111111')
            ->assertSessionMissing('_old_input.documento');

        $auditCount = AuditRecord::count();
        Log::spy();

        DB::beforeExecuting(function (string $sql, array $bindings) use ($document): void {
            if (in_array($document, $bindings, true)) {
                throw new QueryException('sqlite', $sql, $bindings, new \RuntimeException('Falha simulada.'));
            }
        });

        $this->postJson($endpoint, ['person_type' => 'individual', 'documento' => $document])
            ->assertStatus(503)
            ->assertExactJson(['message' => 'Não foi possível buscar a pessoa.'])
            ->assertHeader('Cache-Control', 'no-store, private');

        Log::shouldHaveReceived('error')->once()->with(
            'Falha na consulta de pessoa para vínculo comercial.',
            ['account_id' => $this->gartan->id, 'company_id' => $this->roupas->id, 'role' => 'customer'],
        );
        $this->assertSame($auditCount, AuditRecord::count());
    }

    public function test_counts_filters_and_pagination_only_include_authorized_companies(): void
    {
        // Item 26: 30 clientes na Roupas e 5 na Express; Ana atende só a Roupas.
        foreach (range(1, 35) as $index) {
            $person = Person::create(['account_id' => $this->gartan->id, 'name' => sprintf('Cliente %02d', $index)]);
            Customer::create([
                'account_id' => $this->gartan->id,
                'company_id' => $index <= 30 ? $this->roupas->id : $this->express->id,
                'person_id' => $person->id,
                'active' => $index % 10 !== 0,
            ]);
        }

        $role = $this->role($this->gartan, 'Atendente', [...$this->peopleSlugs(), ...$this->commercialSlugs('customers')]);
        $ana = $this->operator($this->gartan, [[$role, $this->companyNode($this->roupas)]]);
        $url = '/conta/estabelecimento/'.$this->unitCode($this->roupas).'/clientes';

        $this->actingAs($ana)->getJson($url.'?per_page=10')
            ->assertJsonPath('relations.total', 31)
            ->assertJsonPath('relations.last_page', 4)
            ->assertJsonCount(10, 'relations.data');
        $this->getJson($url.'?status=inactive')->assertJsonPath('relations.total', 3);
        $this->getJson($url.'?search=Cliente 3')->assertJsonPath('relations.total', 1);
        $this->getJson($url.'?search=529.982.247-25')
            ->assertJsonPath('relations.total', 1)
            ->assertJsonPath('relations.data.0.document', '529.982.247-25');

        // Clientes da Express não aparecem nem pela busca nem pelo filtro de situação.
        $this->getJson($url.'?search=Cliente 31')->assertJsonPath('relations.total', 0);
        $this->getJson($url.'?status=active&per_page=10')->assertJsonPath('relations.total', 28);
    }

    public function test_saas_inspection_follows_the_represented_authority_and_ends_on_revocation(): void
    {
        // Itens 27 a 29.
        $role = $this->role($this->gartan, 'Atendente', [...$this->peopleSlugs(), ...$this->commercialSlugs('customers')]);
        $ana = $this->operator($this->gartan, [[$role, $this->companyNode($this->roupas)]]);
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $start = '/saas/contas/'.$this->gartan->id.'/inspecao';
        $relation = Customer::where('company_id', $this->roupas->id)->sole();
        $deactivate = '/conta/empresa/'.$this->roupas->code.'/clientes/'.$relation->id.'/inativar';
        $listing = '/conta/estabelecimento/'.$this->unitCode($this->roupas).'/clientes';

        $this->actingAs($platform)->postJson($start, ['user_id' => $ana->id, 'mode' => 'read-only'])->assertOk();
        $this->getJson($listing)->assertOk()->assertJsonPath('relations.total', 1);
        $this->getJson('/conta/estabelecimento/'.$this->unitCode($this->express).'/clientes')->assertNotFound();
        $lookup = '/conta/empresa/'.$this->roupas->code.'/clientes/pessoa';
        $auditCount = AuditRecord::count();
        $this->postJson($lookup, ['person_type' => 'individual', 'documento' => '52998224725'])
            ->assertOk()
            ->assertJsonPath('person.relation', 'active');
        $this->assertSame($auditCount, AuditRecord::count());

        $this->patchJson($deactivate)->assertForbidden();
        $this->assertTrue($relation->fresh()->active);
        $this->postJson('/conta/inspecao/encerrar')->assertOk();

        $this->actingAs($platform)->postJson($start, ['user_id' => $ana->id, 'mode' => 'edit'])->assertOk();
        $this->patchJson($deactivate)->assertNoContent();
        $this->assertFalse($relation->fresh()->active);
        $event = AuditRecord::where('entity_type', 'customer')->where('event', 'updated')->sole();
        $this->assertSame($platform->id, $event->actor_id);
        $this->assertSame($ana->id, $event->represented_user_id);
        $this->assertSame($this->roupas->id, $event->company_id);
        $this->assertNull($event->establishment_id);

        // A seleção de outra empresa na inspeção não amplia a autoridade do representado.
        $expressRelation = Customer::where('company_id', $this->express->id)->sole();
        $this->patchJson('/conta/empresa/'.$this->express->code.'/clientes/'.$expressRelation->id.'/inativar')
            ->assertNotFound();
        $this->assertTrue($expressRelation->fresh()->active);

        $platform->forceFill(['is_saas_admin' => false])->saveOrFail();
        $this->getJson($listing)->assertUnauthorized();
        $this->assertGuest();
    }
}
