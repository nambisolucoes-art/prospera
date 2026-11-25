<?php

namespace Tests\Feature;

use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Domains\Colaboradores\Domain\Models\Employee;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Concerns\BuildsCommercialScenario;
use Tests\TestCase;

/**
 * S04 do plano 002: colaborador como papel de Pessoa por empresa, numa tela única com uma linha por pessoa.
 */
class EmployeeDirectoryTest extends TestCase
{
    use BuildsCommercialScenario;
    use RefreshDatabase;

    private const ALL = ['list', 'view', 'create', 'update', 'activate', 'deactivate'];

    private Client $gartan;

    private Company $roupas;

    private Company $express;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gartan = $this->account();
        $this->roupas = $this->company($this->gartan, 'Gartan Roupas', '11222333');
        $this->express = $this->company($this->gartan, 'Gartan Express', '44555666');
        $this->owner = $this->administrator($this->gartan);
    }

    /** @param list<string> $extra */
    private function operatorOn(Company $company, array $actions = self::ALL, array $extra = []): User
    {
        static $sequence = 0;

        $slugs = [...array_map(fn (string $action) => "account.employees.$action", $actions), ...$extra];
        $role = $this->role($this->gartan, 'Operador colaboradores '.++$sequence, $slugs);

        return $this->operator($this->gartan, [[$role, AccessNode::company($this->gartan->id, $company->id)]]);
    }

    /** @return array<string,mixed> */
    private function newPerson(string $name = 'Maria Souza', string $document = '52998224725'): array
    {
        return ['name' => $name, 'person_type' => 'individual', 'document' => $document, 'email' => 'maria@example.test'];
    }

    /** @param list<Company> $companies */
    private function register(array $companies, array $extra = []): TestResponse
    {
        return $this->postJson('/conta/colaboradores', [
            ...$this->newPerson(),
            'companies' => array_map(fn (Company $company) => $company->code, $companies),
            ...$extra,
        ]);
    }

    private function maria(): Person
    {
        return Person::where('document', '52998224725')->sole();
    }

    public function test_one_registration_creates_the_person_once_and_one_independent_link_per_company(): void
    {
        $this->actingAs($this->owner);

        $this->register([$this->roupas, $this->express], [
            'work' => [
                $this->roupas->code => ['email' => 'maria@roupas.test', 'phone' => '(71) 3333-1111'],
                $this->express->code => ['email' => 'maria@express.test'],
            ],
        ])->assertNoContent();

        $person = $this->maria();
        $this->assertSame(1, Person::where('document', '52998224725')->count());
        $this->assertSame(2, Employee::where('person_id', $person->id)->count());

        $roupas = Employee::where('company_id', $this->roupas->id)->sole();
        $express = Employee::where('company_id', $this->express->id)->sole();
        $this->assertSame('maria@roupas.test', $roupas->work_email);
        $this->assertSame('7133331111', $roupas->work_phone);
        $this->assertSame('maria@express.test', $express->work_email);
        $this->assertNull($express->work_phone);
        $this->assertTrue($roupas->active && $express->active);

        // Cada empresa recebe o seu evento, e o histórico não leva o CPF.
        $events = AuditRecord::where('entity_type', 'employee')->where('event', 'created')->get();
        $this->assertEqualsCanonicalizing(
            [$this->roupas->id, $this->express->id],
            $events->pluck('company_id')->all(),
        );
        $this->assertStringNotContainsString('52998224725', json_encode($events->all()));

        // Colaborador não é usuário e não concede acesso.
        $this->assertSame(0, AccountMembership::where('person_id', $person->id)->count());
        $this->assertFalse(Schema::hasColumn('employees', 'document'));
        $this->assertFalse(Schema::hasColumn('employees', 'name'));
    }

    public function test_a_single_authorized_company_needs_no_selection(): void
    {
        $operator = $this->operatorOn($this->roupas, extra: $this->peopleSlugs());

        $this->actingAs($operator)->postJson('/conta/colaboradores', $this->newPerson())->assertNoContent();

        $this->assertSame($this->roupas->id, Employee::sole()->company_id);

        $this->getJson('/conta/colaboradores/cadastrar')->assertOk()->assertJsonCount(1, 'companies');
    }

    public function test_an_existing_person_becomes_an_employee_without_duplicating_the_document_and_keeps_other_roles(): void
    {
        $this->actingAs($this->owner);

        $this->postJson('/conta/empresa/'.$this->roupas->code.'/clientes', [
            ...$this->newPerson(), 'tag_ids' => [],
        ])->assertNoContent();
        $person = $this->maria();
        $this->postJson('/conta/empresa/'.$this->roupas->code.'/fornecedores', ['person_id' => $person->id])
            ->assertNoContent();

        $this->postJson('/conta/colaboradores', ['person_id' => $person->id, 'companies' => [$this->roupas->code]])
            ->assertNoContent();

        $this->assertSame(1, Person::where('document', '52998224725')->count());
        $this->assertSame(1, $person->customers()->count());
        $this->assertSame(1, $person->suppliers()->count());
        $this->assertSame(1, $person->employees()->count());

        $this->getJson('/conta/colaboradores/'.$person->id.'/visualizar')
            ->assertOk()
            ->assertJsonCount(3, 'tags');
    }

    public function test_an_unauthorized_or_inactive_company_writes_nothing(): void
    {
        $operator = $this->operatorOn($this->roupas);
        $this->actingAs($operator);

        // Empresa fora do acesso responde como inexistente e não cria nem a pessoa nova.
        $this->register([$this->roupas, $this->express])->assertNotFound();
        $this->assertSame(0, Employee::count());
        $this->assertSame(0, Person::where('document', '52998224725')->count());

        $this->actingAs($this->owner);
        $this->express->forceFill(['active' => false])->saveQuietly();
        $this->register([$this->roupas, $this->express])->assertNotFound();
        $this->assertSame(0, Employee::count());
        $this->assertSame(0, Person::where('document', '52998224725')->count());

        $other = $this->account('Outra conta');
        $foreign = $this->company($other, 'Outra', '77888999');
        $this->register([$foreign])->assertNotFound();
        $this->assertSame(0, Employee::count());
    }

    public function test_a_duplicate_in_one_company_rolls_everything_back(): void
    {
        $this->actingAs($this->owner);
        $this->register([$this->express])->assertNoContent();
        $person = $this->maria();

        $this->postJson('/conta/colaboradores', [
            'person_id' => $person->id,
            'companies' => [$this->roupas->code, $this->express->code],
        ])->assertUnprocessable()->assertJsonValidationErrors('companies');

        $this->assertSame(1, Employee::count());
        $this->assertSame(0, Employee::where('company_id', $this->roupas->id)->count());
    }

    public function test_the_listing_shows_one_row_per_person_and_only_the_links_in_scope(): void
    {
        $this->actingAs($this->owner);
        $this->register([$this->roupas, $this->express])->assertNoContent();
        $this->postJson('/conta/colaboradores', [
            ...$this->newPerson('Bruno Lima', '11144477735'), 'companies' => [$this->express->code],
        ])->assertNoContent();

        $this->getJson('/conta/colaboradores')
            ->assertOk()
            ->assertJsonPath('employees.total', 2)
            ->assertJsonPath('employees.data.0.name', 'Bruno Lima')
            ->assertJsonPath('employees.data.1.name', 'Maria Souza')
            ->assertJsonPath('employees.data.1.links_count', 2)
            ->assertJsonCount(2, 'employees.data.1.links');

        $operator = $this->operatorOn($this->roupas);
        $response = $this->actingAs($operator)->getJson('/conta/colaboradores')->assertOk();

        $response->assertJsonPath('employees.total', 1)
            ->assertJsonPath('employees.data.0.name', 'Maria Souza')
            ->assertJsonPath('employees.data.0.links_count', 1)
            ->assertJsonCount(1, 'companies');
        $this->assertStringNotContainsString('Gartan Express', $response->getContent());
        $this->assertStringNotContainsString('Bruno', $response->getContent());

        // Filtro por empresa fora do acesso e busca pelo outro funcionário não revelam nada.
        $this->getJson('/conta/colaboradores?company='.$this->express->code)->assertJsonPath('employees.total', 0);
        $this->getJson('/conta/colaboradores?search=Bruno')->assertJsonPath('employees.total', 0);
        $this->getJson('/conta/colaboradores?status=inactive')->assertJsonPath('employees.total', 0);
    }

    public function test_search_filters_and_pagination_run_in_the_database(): void
    {
        $this->actingAs($this->owner);

        foreach (range(1, 30) as $index) {
            $person = Person::create([
                'account_id' => $this->gartan->id,
                'name' => sprintf('Pessoa %02d', $index),
                'person_type' => 'individual',
            ]);
            Employee::query()->insert([
                'account_id' => $this->gartan->id,
                'company_id' => $this->roupas->id,
                'person_id' => $person->id,
                'active' => $index % 2 === 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->getJson('/conta/colaboradores?per_page=10')
            ->assertJsonPath('employees.total', 30)
            ->assertJsonCount(10, 'employees.data');
        $this->getJson('/conta/colaboradores?status=active')->assertJsonPath('employees.total', 15);
        $this->getJson('/conta/colaboradores?search=Pessoa 07')->assertJsonPath('employees.total', 1);
    }

    public function test_each_link_is_edited_and_toggled_independently_and_reactivation_does_not_duplicate(): void
    {
        $this->actingAs($this->owner);
        $this->register([$this->roupas, $this->express], [
            'work' => [$this->roupas->code => ['email' => 'a@roupas.test'], $this->express->code => ['email' => 'b@express.test']],
        ])->assertNoContent();
        $person = $this->maria();
        $roupas = Employee::where('company_id', $this->roupas->id)->sole();
        $express = Employee::where('company_id', $this->express->id)->sole();

        $this->patchJson('/conta/colaboradores/'.$person->id.'/vinculos/'.$roupas->id, [
            'active' => '0', 'work_email' => 'novo@roupas.test',
        ])->assertNoContent();

        $this->assertFalse($roupas->fresh()->active);
        $this->assertSame('novo@roupas.test', $roupas->fresh()->work_email);
        $this->assertTrue($express->fresh()->active);
        $this->assertSame('b@express.test', $express->fresh()->work_email);

        $this->patchJson('/conta/colaboradores/'.$person->id.'/vinculos/'.$roupas->id, ['active' => '1'])
            ->assertNoContent();
        $this->assertTrue($roupas->fresh()->active);
        $this->assertSame(2, Employee::count());

        // Cadastrar de novo onde já existe vínculo é recusado, ativo ou não.
        $this->postJson('/conta/colaboradores/'.$person->id.'/vinculos', ['companies' => [$this->roupas->code]])
            ->assertUnprocessable();
        $this->assertSame(2, Employee::count());
    }

    public function test_each_action_checks_its_own_permission(): void
    {
        $this->actingAs($this->owner);
        $this->register([$this->roupas])->assertNoContent();
        $person = $this->maria();
        $link = Employee::sole();

        $viewer = $this->operatorOn($this->roupas, ['list', 'view']);
        $this->actingAs($viewer);
        $this->patchJson('/conta/colaboradores/'.$person->id.'/vinculos/'.$link->id, ['work_email' => 'x@x.test'])
            ->assertForbidden();
        $this->patchJson('/conta/colaboradores/'.$person->id.'/vinculos/'.$link->id, ['active' => '0'])
            ->assertForbidden();
        $this->register([$this->roupas], ['document' => '11144477735'])->assertForbidden();

        $editor = $this->operatorOn($this->roupas, ['list', 'view', 'update']);
        $this->actingAs($editor);
        $this->patchJson('/conta/colaboradores/'.$person->id.'/vinculos/'.$link->id, ['work_email' => 'x@x.test'])
            ->assertNoContent();
        $this->patchJson('/conta/colaboradores/'.$person->id.'/vinculos/'.$link->id, ['active' => '0'])
            ->assertForbidden();
        $this->assertTrue($link->fresh()->active);
    }

    public function test_a_unit_assignment_does_not_open_the_module(): void
    {
        $unit = $this->roupas->primaryEstablishment()->sole();
        $role = $this->role($this->gartan, 'Gerente de unidade', ['account.employees.list', 'account.employees.view']);
        $manager = $this->operator($this->gartan, [[$role, AccessNode::establishment($this->gartan->id, $this->roupas->id, $unit->id)]]);

        $this->actingAs($this->owner);
        $this->register([$this->roupas])->assertNoContent();

        $this->actingAs($manager)->getJson('/conta/colaboradores')->assertForbidden();
        $this->getJson('/conta/colaboradores/'.$this->maria()->id.'/editar')->assertForbidden();
    }

    public function test_access_to_other_accounts_and_hidden_links_is_refused(): void
    {
        $this->actingAs($this->owner);
        $this->register([$this->express])->assertNoContent();
        $person = $this->maria();
        $link = Employee::sole();

        // Operador da Roupas: a pessoa só tem vínculo na Express, então não existe para ele.
        $operator = $this->operatorOn($this->roupas);
        $this->actingAs($operator);
        $this->getJson('/conta/colaboradores/'.$person->id.'/visualizar')->assertNotFound();
        $this->getJson('/conta/colaboradores/'.$person->id.'/editar')->assertNotFound();
        $this->patchJson('/conta/colaboradores/'.$person->id.'/vinculos/'.$link->id, ['active' => '0'])->assertNotFound();
        $this->patchJson('/conta/colaboradores/'.$person->id, ['name' => 'Outra'])->assertNotFound();
        $this->assertSame('Maria Souza', $person->fresh()->name);

        // Outra conta não alcança nada.
        $other = $this->account('Outra conta');
        $this->company($other, 'Outra', '77888999');
        $stranger = $this->administrator($other);
        $this->actingAs($stranger)->getJson('/conta/colaboradores')->assertOk()->assertJsonPath('employees.total', 0);
        $this->getJson('/conta/colaboradores/'.$person->id.'/editar')->assertNotFound();
    }

    public function test_the_access_indicator_exists_only_for_who_lists_users(): void
    {
        $this->actingAs($this->owner);
        $this->register([$this->roupas])->assertNoContent();
        $person = $this->maria();

        $login = User::factory()->forAccount($this->gartan->id)->create();
        $this->membership($login)->forceFill(['person_id' => $person->id])->saveOrFail();

        $this->getJson('/conta/colaboradores')
            ->assertJsonPath('canSeeAccess', true)
            ->assertJsonPath('employees.data.0.has_access', true);
        $this->getJson('/conta/colaboradores?access=no')->assertJsonPath('employees.total', 0);
        $this->getJson('/conta/colaboradores/'.$person->id.'/editar')->assertJsonPath('access', true);

        $blind = $this->operatorOn($this->roupas);
        $this->actingAs($blind);
        $list = $this->getJson('/conta/colaboradores')->assertOk();
        $this->assertArrayNotHasKey('has_access', $list->json('employees.data.0'));
        $list->assertJsonPath('canSeeAccess', false);

        // O filtro é ignorado, sem diferença de resposta, e a ficha não traz o indicador.
        $this->getJson('/conta/colaboradores?access=no')->assertJsonPath('employees.total', 1);
        $this->getJson('/conta/colaboradores?access=yes')->assertJsonPath('employees.total', 1);
        $this->getJson('/conta/colaboradores/'.$person->id.'/editar')
            ->assertJsonPath('access', null)
            ->assertJsonPath('canSeeAccess', false);
    }

    public function test_registering_and_deactivating_an_employee_never_touch_login_or_permissions(): void
    {
        $this->actingAs($this->owner);
        $person = Person::create(['account_id' => $this->gartan->id, 'name' => 'Com login', 'person_type' => 'individual']);
        $login = User::factory()->forAccount($this->gartan->id)->create();
        $membership = $this->membership($login);
        $membership->forceFill(['person_id' => $person->id])->saveOrFail();
        $assignments = $membership->assignments()->count();

        $this->postJson('/conta/colaboradores', ['person_id' => $person->id, 'companies' => [$this->roupas->code]])
            ->assertNoContent();
        $this->assertSame($assignments, $membership->assignments()->count());
        $this->assertSame('active', $membership->fresh()->status);

        $this->patchJson('/conta/colaboradores/'.$person->id.'/vinculos/'.Employee::sole()->id, ['active' => '0'])
            ->assertNoContent();
        $this->assertSame('active', $membership->fresh()->status);
        $this->assertSame($assignments, $membership->assignments()->count());
        $this->assertSame(1, User::where('id', $login->id)->count());

        // E há usuário sem colaborador: a pessoa sem vínculo não aparece na listagem.
        $this->assertSame(1, User::whereKey($login->id)->count());
        $this->assertSame(1, Employee::count());
    }

    public function test_the_special_tag_is_derived_read_only_and_scoped(): void
    {
        $this->actingAs($this->owner);
        $this->register([$this->express])->assertNoContent();
        $person = $this->maria();

        $tag = Tag::where('account_id', $this->gartan->id)->where('system_key', 'employee')->sole();
        $this->assertSame('Colaborador', $tag->name);
        $this->assertSame(0, DB::table('person_tag')->where('tag_id', $tag->id)->count());

        $this->getJson('/conta/colaboradores/'.$person->id.'/visualizar')->assertJsonPath('tags.0.name', 'Colaborador');

        // Não se edita nem se apaga, e as rotas de etiqueta convencional não a atribuem.
        $this->patchJson('/conta/etiquetas/'.$tag->id, ['name' => 'Outra', 'color' => '#000000', 'module' => 'people'])
            ->assertStatus(403);
        $this->deleteJson('/conta/etiquetas/'.$tag->id)->assertStatus(403);
        $this->assertSame('Colaborador', $tag->fresh()->name);

        // Quem só alcança a Roupas não vê a etiqueta nem a pessoa da Express.
        $operator = $this->operatorOn($this->roupas, ['list', 'view'], ['account.customers.list', 'account.customers.view']);
        $this->actingAs($operator)->getJson('/conta/colaboradores/'.$person->id.'/visualizar')->assertNotFound();

        // O provisionamento é idempotente por conta.
        $this->assertSame($tag->id, Tag::employeeTag($this->gartan->id)->id);
        $this->assertSame(1, Tag::where('system_key', 'employee')->where('account_id', $this->gartan->id)->count());
    }

    public function test_document_lookup_is_confined_to_the_account_and_authorized_companies(): void
    {
        $this->actingAs($this->owner);
        $this->register([$this->express])->assertNoContent();

        $operator = $this->operatorOn($this->roupas);
        $this->actingAs($operator);
        $found = $this->postJson('/conta/colaboradores/pessoa', ['documento' => '52998224725', 'person_type' => 'individual'])
            ->assertOk();
        $found->assertJsonPath('person.name', 'Maria Souza')->assertJsonPath('person.links', []);

        $other = $this->account('Outra conta');
        $this->company($other, 'Outra', '77888999');
        $this->actingAs($this->administrator($other))
            ->postJson('/conta/colaboradores/pessoa', ['documento' => '52998224725', 'person_type' => 'individual'])
            ->assertOk()
            ->assertJsonPath('person', null);
    }

    public function test_saas_inspection_blocks_writes_in_read_only_mode(): void
    {
        $ana = $this->operatorOn($this->roupas, extra: $this->peopleSlugs());
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $start = '/saas/contas/'.$this->gartan->id.'/inspecao';

        $this->actingAs($platform)->postJson($start, ['user_id' => $ana->id, 'mode' => 'read-only'])->assertOk();
        $this->getJson('/conta/colaboradores')->assertOk();
        $this->register([$this->roupas])->assertForbidden();
        $this->assertSame(0, Employee::count());
        $this->postJson('/inspecao/encerrar')->assertOk();

        $this->actingAs($platform)->postJson($start, ['user_id' => $ana->id, 'mode' => 'edit'])->assertOk();
        $this->register([$this->roupas])->assertNoContent();
        $event = AuditRecord::where('entity_type', 'employee')->where('event', 'created')->sole();
        $this->assertSame($platform->id, $event->actor_id);
        $this->assertSame($ana->id, $event->represented_user_id);
    }

    public function test_the_special_tag_is_provisioned_for_new_accounts_and_by_the_migration_for_existing_ones(): void
    {
        // Conta nova: o primeiro acesso provisiona as três etiquetas especiais.
        $keys = Tag::where('account_id', $this->gartan->id)->pluck('system_key')->all();
        $this->assertContains('employee', $keys);
        $this->assertContains('customer', $keys);
        $this->assertContains('supplier', $keys);

        // Conta existente: a migration cria a etiqueta de forma idempotente e a down() só remove as sem uso.
        $migration = require base_path('database/migrations/2026_11_25_000001_create_employees.php');
        Tag::where('system_key', 'employee')->delete();
        Schema::drop('employees');

        $migration->up();
        $this->assertSame(1, Tag::where('account_id', $this->gartan->id)->where('system_key', 'employee')->count());

        $migration->down();
        $this->assertSame(0, Tag::where('system_key', 'employee')->count());
        $this->assertFalse(Schema::hasTable('employees'));

        $migration->up();
        $this->assertTrue(Schema::hasTable('employees'));
    }

    public function test_central_person_data_needs_people_update_and_employees_update_is_not_enough(): void
    {
        $this->actingAs($this->owner);
        $this->register([$this->roupas])->assertNoContent();
        $person = $this->maria();
        $link = Employee::sole();

        // Só colaboradores: vê e edita o vínculo, mas um envio forjado não altera nome, CPF nem contatos.
        $only = $this->operatorOn($this->roupas, extra: ['account.people.view']);
        $this->actingAs($only);
        $this->patchJson('/conta/colaboradores/'.$person->id, ['name' => 'Forjado', 'person_type' => 'individual',
            'document' => '11144477735', 'email' => 'forjado@example.test'])->assertForbidden();
        $this->patchJson('/conta/colaboradores/'.$person->id, ['name' => 'Maria Souza', 'person_type' => 'individual',
            'document' => '52998224725', 'email' => 'outro@example.test'])->assertForbidden();

        $fresh = $person->fresh();
        $this->assertSame('Maria Souza', $fresh->name);
        $this->assertSame('52998224725', $fresh->document);
        $this->assertSame('maria@example.test', $fresh->email);

        $form = $this->getJson('/conta/colaboradores/'.$person->id.'/editar')->assertOk();
        $form->assertJsonPath('person.can_edit_identification', false)->assertJsonPath('person.can_edit_contacts', false);
        $form->assertJsonPath('links.0.can_update', true);

        // O vínculo continua editável e não toca em Pessoa.
        $this->patchJson('/conta/colaboradores/'.$person->id.'/vinculos/'.$link->id, ['work_email' => 'v@roupas.test'])
            ->assertNoContent();
        $this->assertSame('maria@example.test', $person->fresh()->email);

        // Só pessoas: edita Pessoa em geral, mas sem editar colaboradores não passa pela ficha.
        $people = $this->operatorOn($this->roupas, ['list', 'view'], $this->peopleSlugs());
        $this->actingAs($people)->patchJson('/conta/colaboradores/'.$person->id, ['name' => 'Maria S.',
            'person_type' => 'individual', 'document' => '52998224725'])->assertForbidden();
        $this->assertSame('Maria Souza', $person->fresh()->name);

        // Com as duas permissões, a edição central é aceita.
        $both = $this->operatorOn($this->roupas, extra: $this->peopleSlugs());
        $this->actingAs($both)->patchJson('/conta/colaboradores/'.$person->id, ['name' => 'Maria S. Souza',
            'person_type' => 'individual', 'document' => '52998224725', 'email' => 'maria@example.test'])
            ->assertNoContent();
        $this->assertSame('Maria S. Souza', $person->fresh()->name);
    }

    public function test_the_derived_tag_follows_active_visible_links(): void
    {
        $this->actingAs($this->owner);
        $this->register([$this->roupas, $this->express])->assertNoContent();
        $person = $this->maria();
        $roupas = Employee::where('company_id', $this->roupas->id)->sole();
        $express = Employee::where('company_id', $this->express->id)->sole();
        $tags = fn () => collect($this->getJson('/conta/colaboradores/'.$person->id.'/visualizar')->json('tags'))->pluck('name')->all();
        $toggle = fn (Employee $link, string $active) => $this->patchJson(
            '/conta/colaboradores/'.$person->id.'/vinculos/'.$link->id, ['active' => $active],
        )->assertNoContent();

        $this->assertSame(['Colaborador'], $tags());

        $toggle($roupas, '0');
        $this->assertSame(['Colaborador'], $tags());

        $toggle($express, '0');
        $this->assertSame([], $tags());
        $this->assertSame(0, DB::table('person_tag')->where('person_id', $person->id)->count());

        $toggle($roupas, '1');
        $this->assertSame(['Colaborador'], $tags());
        $this->assertSame(2, Employee::count());

        // Quem só alcança a Express, onde o vínculo está inativo, não herda a etiqueta da Roupas.
        $express = $this->operatorOn($this->express);
        $this->actingAs($express);
        $this->assertSame([], $tags());
        $this->getJson('/conta/colaboradores')->assertJsonPath('employees.data.0.links_count', 1);

        // Quem alcança a Roupas vê a etiqueta, sem saber do vínculo da Express.
        $roupasOperator = $this->operatorOn($this->roupas);
        $response = $this->actingAs($roupasOperator)->getJson('/conta/colaboradores/'.$person->id.'/visualizar')->assertOk();
        $response->assertJsonPath('tags.0.name', 'Colaborador');
        $this->assertStringNotContainsString('Express', $response->getContent());

        // Etiqueta não é atribuída por nenhuma rota de Pessoas e Clientes e Fornecedores seguem independentes.
        $this->assertSame(0, DB::table('person_tag')->where('person_id', $person->id)->count());
        $this->assertSame(0, $person->customers()->count() + $person->suppliers()->count());
    }

    public function test_conventional_tags_of_the_employees_module_belong_to_each_link(): void
    {
        $this->actingAs($this->owner);
        $this->postJson('/conta/etiquetas', ['name' => 'Equipe de loja', 'module' => 'employees', 'color' => '#336699'])
            ->assertSuccessful();
        $this->postJson('/conta/etiquetas', ['name' => 'Cliente VIP', 'module' => 'customers', 'color' => '#336699'])
            ->assertSuccessful();
        $store = Tag::where('name', 'Equipe de loja')->sole();
        $customerTag = Tag::where('name', 'Cliente VIP')->sole();
        $this->assertSame('employees', $store->module);

        // Etiqueta de outro módulo é recusada e nada é gravado.
        $this->register([$this->roupas], ['tag_ids' => [$customerTag->id]])->assertUnprocessable()
            ->assertJsonValidationErrors('tag_ids');
        $this->assertSame(0, Employee::count());

        $this->register([$this->roupas, $this->express], ['tag_ids' => [$store->id]])->assertNoContent();
        $this->assertSame(2, DB::table('employees_tags')->where('tag_id', $store->id)->count());

        // A seleção pertence ao vínculo: limpar numa empresa não altera a outra.
        $person = $this->maria();
        $roupas = Employee::where('company_id', $this->roupas->id)->sole();
        $this->patchJson('/conta/colaboradores/'.$person->id.'/vinculos/'.$roupas->id, ['edit_tags' => '1'])
            ->assertNoContent();
        $this->assertSame(0, DB::table('employees_tags')->where('relation_id', $roupas->id)->count());
        $this->assertSame(1, DB::table('employees_tags')->where('tag_id', $store->id)->count());

        $form = $this->getJson('/conta/colaboradores/'.$person->id.'/editar')->assertOk();
        $this->assertSame([[$store->id], []], collect($form->json('links'))->sortByDesc(
            fn ($link) => count($link['tag_ids']),
        )->pluck('tag_ids')->values()->all());
        $form->assertJsonCount(1, 'registrationTags');

        // Editar etiquetas exige editar colaboradores, e a etiqueta em uso não pode ser excluída.
        $viewer = $this->operatorOn($this->express, ['list', 'view', 'deactivate']);
        $express = Employee::where('company_id', $this->express->id)->sole();
        $this->actingAs($viewer)->patchJson('/conta/colaboradores/'.$person->id.'/vinculos/'.$express->id, ['edit_tags' => '1'])
            ->assertForbidden();
        $this->assertSame(1, DB::table('employees_tags')->where('relation_id', $express->id)->count());

        $this->actingAs($this->owner)->deleteJson('/conta/etiquetas/'.$store->id)->assertStatus(422);
        $this->assertSame(0, DB::table('person_tag')->where('tag_id', $store->id)->count());
    }
}
