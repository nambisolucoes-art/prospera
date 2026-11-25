<?php

namespace Tests\Feature;

use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Acessos\Domain\Models\RoleAssignment;
use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Domains\Colaboradores\Domain\DTOs\Departments\CreateDepartmentDTO;
use App\Domains\Colaboradores\Domain\DTOs\JobTitles\CreateJobTitleDTO;
use App\Domains\Colaboradores\Domain\Models\Department;
use App\Domains\Colaboradores\Domain\Models\JobTitle;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\BuildsCommercialScenario;
use Tests\TestCase;

/**
 * S03 do plano 002: departamentos e funções da empresa. Os dois cadastros seguem o mesmo contrato,
 * então os cenários rodam para os dois.
 */
class CompanyCatalogTest extends TestCase
{
    use BuildsCommercialScenario;
    use RefreshDatabase;

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

    /** @return array<string, array{0: string, 1: string, 2: string, 3: class-string<Model>, 4: string}> */
    public static function catalogs(): array
    {
        return [
            'departamentos' => ['departamentos', 'departments', 'departments', Department::class, 'department'],
            'funcoes' => ['funcoes', 'job-titles', 'job_titles', JobTitle::class, 'job-title'],
        ];
    }

    private function url(string $segment, string $suffix = ''): string
    {
        return '/conta/'.$segment.$suffix;
    }

    /**
     * @param  list<Company>  $companies
     * @return array<string,mixed>
     */
    private function body(string $name, array $companies, array $extra = []): array
    {
        return ['name' => $name, 'companies' => array_map(fn (Company $company) => $company->code, $companies), ...$extra];
    }

    /** @param list<string> $actions */
    private function operatorOn(Company $company, string $module, array $actions, ?Client $account = null): User
    {
        $account ??= $this->gartan;
        static $sequence = 0;
        $role = $this->role($account, 'Operador '.$module.' '.++$sequence, array_map(fn (string $action) => "account.$module.$action", $actions));

        return $this->operator($account, [[$role, AccessNode::company($account->id, $company->id)]]);
    }

    #[DataProvider('catalogs')]
    public function test_item_is_created_in_the_chosen_company_with_a_normalized_name(
        string $segment,
        string $module,
        string $table,
        string $model,
        string $type,
    ): void {
        $this->actingAs($this->owner)
            ->postJson($this->url($segment), $this->body("  Gerência   Comercial \n", [$this->roupas]))
            ->assertOk();

        $item = $model::where('company_id', $this->roupas->id)->sole();

        $this->assertSame($this->gartan->id, $item->account_id);
        $this->assertSame('Gerência Comercial', $item->name);
        $this->assertSame('gerencia comercial', $item->normalized_name);
        $this->assertTrue($item->active);
        $this->assertSame(0, $model::where('company_id', $this->express->id)->count());
    }

    #[DataProvider('catalogs')]
    public function test_one_registration_creates_a_record_in_each_chosen_company(
        string $segment,
        string $module,
        string $table,
        string $model,
        string $type,
    ): void {
        $this->actingAs($this->owner)
            ->postJson($this->url($segment), $this->body('Financeiro', [$this->roupas, $this->express]))
            ->assertOk();

        $this->assertSame(2, $model::count());
        $this->assertSame(1, $model::where('company_id', $this->roupas->id)->count());
        $this->assertSame(1, $model::where('company_id', $this->express->id)->count());
        $this->assertSame(2, AuditRecord::where('entity_type', $type)->where('event', 'created')->count());

        // Uma empresa com o nome já usado recusa o cadastro inteiro, sem gravar nas demais.
        $third = $this->company($this->gartan, 'Gartan Calçados', '99111222');
        $this->postJson($this->url($segment), $this->body('FINANCEIRO', [$third, $this->express]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
        $this->assertSame(2, $model::count());

        // A escolha de empresas é obrigatória quando há mais de uma autorizada.
        $this->postJson($this->url($segment), ['name' => 'Sem empresa'])->assertJsonValidationErrors('companies');
        $this->postJson($this->url($segment), ['name' => 'Sem empresa', 'companies' => []])
            ->assertJsonValidationErrors('companies');
        $this->assertSame(2, $model::count());

        // Empresas e nome faltando: os dois erros voltam juntos.
        $this->postJson($this->url($segment), ['name' => ''])->assertJsonValidationErrors(['companies', 'name']);
    }

    #[DataProvider('catalogs')]
    public function test_an_account_with_one_company_needs_no_selector(
        string $segment,
        string $module,
        string $table,
        string $model,
        string $type,
    ): void {
        $solo = $this->account('Conta pequena');
        $company = $this->company($solo, 'Empresa única', '12345678');
        $small = $this->administrator($solo);

        $this->actingAs($small)->postJson($this->url($segment), ['name' => 'Fiscal'])->assertOk();

        $this->assertSame($company->id, $model::sole()->company_id);
        $this->getJson($this->url($segment))->assertOk()->assertJsonCount(1, 'creatable');
    }

    #[DataProvider('catalogs')]
    public function test_name_is_unique_per_company_ignoring_case_accents_and_spaces(
        string $segment,
        string $module,
        string $table,
        string $model,
        string $type,
    ): void {
        $this->actingAs($this->owner);
        $this->postJson($this->url($segment), $this->body('Gerência Comercial', [$this->roupas]))->assertOk();

        foreach (['gerencia  comercial', 'GERÊNCIA COMERCIAL', ' Gerencia Comercial '] as $name) {
            $this->postJson($this->url($segment), $this->body($name, [$this->roupas]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('name');
        }

        $this->assertSame(1, $model::where('company_id', $this->roupas->id)->count());

        // Outra empresa da mesma conta pode usar o mesmo nome.
        $this->postJson($this->url($segment), $this->body('Gerência Comercial', [$this->express]))->assertOk();
        $this->assertSame(1, $model::where('company_id', $this->express->id)->count());
    }

    #[DataProvider('catalogs')]
    public function test_name_is_validated_and_the_database_also_refuses_a_duplicate(
        string $segment,
        string $module,
        string $table,
        string $model,
        string $type,
    ): void {
        $this->actingAs($this->owner);
        $at = [$this->roupas];

        $this->postJson($this->url($segment), $this->body('', $at))->assertJsonValidationErrors('name');
        $this->postJson($this->url($segment), $this->body(str_repeat('a', 101), $at))->assertJsonValidationErrors('name');
        $this->postJson($this->url($segment), ['name' => ['x'], 'companies' => [$this->roupas->code]])
            ->assertJsonValidationErrors('name');
        $this->postJson($this->url($segment), $this->body('Ok', $at, ['active' => 'talvez']))
            ->assertJsonValidationErrors('active');
        $this->postJson($this->url($segment), $this->body(str_repeat('a', 100), $at))->assertOk();

        $row = DB::table($table)->first();
        $this->expectException(UniqueConstraintViolationException::class);
        DB::table($table)->insert([
            'account_id' => $row->account_id,
            'company_id' => $row->company_id,
            'name' => 'Outro',
            'normalized_name' => $row->normalized_name,
        ]);
    }

    #[DataProvider('catalogs')]
    public function test_update_keeps_the_own_name_and_refuses_the_name_of_another_item(
        string $segment,
        string $module,
        string $table,
        string $model,
        string $type,
    ): void {
        $this->actingAs($this->owner);
        $this->postJson($this->url($segment), $this->body('Financeiro', [$this->roupas]))->assertOk();
        $this->postJson($this->url($segment), $this->body('Comercial', [$this->roupas]))->assertOk();
        $financeiro = $model::where('name', 'Financeiro')->sole();

        $this->patchJson($this->url($segment, '/'.$financeiro->id), ['name' => 'FINANCEIRO'])->assertOk();
        $this->assertSame('FINANCEIRO', $financeiro->fresh()->name);

        $this->patchJson($this->url($segment, '/'.$financeiro->id), ['name' => 'comercial'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
        $this->assertSame('FINANCEIRO', $financeiro->fresh()->name);

        // A empresa do registro nunca muda por edição.
        $this->patchJson($this->url($segment, '/'.$financeiro->id), ['name' => 'Financeiro', 'companies' => [$this->express->code]])
            ->assertOk();
        $this->assertSame($this->roupas->id, $financeiro->fresh()->company_id);
    }

    #[DataProvider('catalogs')]
    public function test_the_list_follows_the_access_and_other_companies_and_accounts_answer_404(
        string $segment,
        string $module,
        string $table,
        string $model,
        string $type,
    ): void {
        $this->actingAs($this->owner);
        $this->postJson($this->url($segment), $this->body('Logística', [$this->express]))->assertOk();
        $this->postJson($this->url($segment), $this->body('Fiscal', [$this->roupas]))->assertOk();
        $foreign = $model::where('company_id', $this->express->id)->sole();

        // Acesso a toda a conta: a mesma tela traz as duas empresas e o filtro restringe a lista.
        $this->getJson($this->url($segment))->assertOk()
            ->assertJsonPath($table.'.total', 2)
            ->assertJsonCount(2, 'companies')
            ->assertJsonCount(2, 'creatable');
        $this->getJson($this->url($segment).'?company='.$this->express->code)->assertOk()
            ->assertJsonPath($table.'.total', 1)
            ->assertJsonPath($table.'.data.0.company_code', $this->express->code)
            ->assertJsonPath($table.'.data.0.company', 'Gartan Express');
        $this->getJson($this->url($segment).'?company=inexistente')->assertJsonPath($table.'.total', 0);

        // Acesso só à Roupas: a lista, a ficha e a edição não alcançam a Express.
        $scoped = $this->operatorOn($this->roupas, $module, ['list', 'view', 'update', 'create']);
        $this->actingAs($scoped)->getJson($this->url($segment))->assertOk()
            ->assertJsonPath($table.'.total', 1)
            ->assertJsonPath($table.'.data.0.name', 'Fiscal')
            ->assertJsonCount(1, 'companies');
        $this->getJson($this->url($segment).'?company='.$this->express->code)->assertJsonPath($table.'.total', 0);
        $this->getJson($this->url($segment, '/'.$foreign->id.'/visualizar'))->assertNotFound();
        $this->patchJson($this->url($segment, '/'.$foreign->id), ['name' => 'Invadido'])->assertNotFound();
        $this->assertSame('Logística', $foreign->fresh()->name);

        // Empresa fora do acesso no cadastro responde como inexistente e nada é gravado.
        $this->postJson($this->url($segment), $this->body('Invasor', [$this->roupas, $this->express]))->assertNotFound();
        $this->assertSame(2, $model::count());

        // Outra conta: nenhum registro, nenhuma empresa.
        $other = $this->account('Outra conta');
        $this->company($other, 'Empresa alheia', '77888999');
        $stranger = $this->administrator($other);
        $this->actingAs($stranger)->getJson($this->url($segment))->assertOk()->assertJsonPath($table.'.total', 0);
        $this->getJson($this->url($segment, '/'.$foreign->id.'/visualizar'))->assertNotFound();
        $this->postJson($this->url($segment), $this->body('Intruso', [$this->roupas]))->assertNotFound();
        $this->patchJson($this->url($segment, '/'.$foreign->id), ['name' => 'Intruso'])->assertNotFound();
        $this->assertSame(2, $model::count());
    }

    #[DataProvider('catalogs')]
    public function test_each_action_needs_its_own_permission_on_the_company(
        string $segment,
        string $module,
        string $table,
        string $model,
        string $type,
    ): void {
        $this->actingAs($this->owner)->postJson($this->url($segment), $this->body('Fiscal', [$this->roupas]))->assertOk();
        $item = $model::sole();
        $show = $this->url($segment, '/'.$item->id.'/visualizar');

        $nothing = $this->operatorOn($this->roupas, $module, ['create']);
        $this->actingAs($nothing)->getJson($this->url($segment))->assertForbidden();
        $this->getJson($show)->assertForbidden();
        $this->patchJson($this->url($segment, '/'.$item->id), ['name' => 'X'])->assertForbidden();
        $this->postJson($this->url($segment), $this->body('Novo', [$this->roupas]))->assertOk();

        $reader = $this->operatorOn($this->roupas, $module, ['list', 'view']);
        $this->actingAs($reader)->getJson($this->url($segment))->assertOk()->assertJsonCount(0, 'creatable');
        $this->getJson($show)->assertOk()->assertJsonPath('name', 'Fiscal');
        $this->postJson($this->url($segment), $this->body('Outro', [$this->roupas]))->assertForbidden();
        $this->patchJson($this->url($segment, '/'.$item->id), ['name' => 'X'])->assertForbidden();

        $viewless = $this->operatorOn($this->roupas, $module, ['list']);
        $this->actingAs($viewless)->getJson($show)->assertForbidden();

        $this->assertSame(2, $model::count());
        $this->assertSame('Fiscal', $item->fresh()->name);
    }

    #[DataProvider('catalogs')]
    public function test_activating_and_deactivating_use_separate_permissions_and_keep_the_record(
        string $segment,
        string $module,
        string $table,
        string $model,
        string $type,
    ): void {
        $this->actingAs($this->owner)->postJson($this->url($segment), $this->body('Fiscal', [$this->roupas]))->assertOk();
        $item = $model::sole();
        $url = $this->url($segment, '/'.$item->id);

        $editor = $this->operatorOn($this->roupas, $module, ['list', 'update']);
        $this->actingAs($editor)->patchJson($url, ['name' => 'Fiscal', 'active' => false])->assertForbidden();
        $this->assertTrue($item->fresh()->active);
        $this->patchJson($url, ['name' => 'Fiscal e Tributário', 'active' => true])->assertOk();

        $deactivator = $this->operatorOn($this->roupas, $module, ['list', 'update', 'deactivate']);
        $this->actingAs($deactivator)->patchJson($url, ['name' => 'Fiscal e Tributário', 'active' => false])->assertOk();
        $this->assertFalse($item->fresh()->active);

        // Reativar exige a permissão de ativar, e o nome continua protegido pela unicidade.
        $this->patchJson($url, ['name' => 'Fiscal e Tributário', 'active' => true])->assertForbidden();
        $this->assertFalse($item->fresh()->active);

        $activator = $this->operatorOn($this->roupas, $module, ['list', 'update', 'activate']);
        $this->actingAs($activator)->patchJson($url, ['name' => 'Fiscal e Tributário', 'active' => true])->assertOk();
        $this->assertTrue($item->fresh()->active);

        // Cadastrar já inativo exige a permissão de inativar.
        $creator = $this->operatorOn($this->roupas, $module, ['create']);
        $this->actingAs($creator)
            ->postJson($this->url($segment), $this->body('Arquivo', [$this->roupas], ['active' => false]))
            ->assertForbidden();
        $this->assertSame(1, $model::count());

        // Nada é excluído: não existe rota de exclusão.
        $this->actingAs($this->owner)->deleteJson($url)->assertStatus(405);
        $this->assertSame(1, $model::count());
    }

    #[DataProvider('catalogs')]
    public function test_permissions_are_checked_company_by_company_in_a_multiple_registration(
        string $segment,
        string $module,
        string $table,
        string $model,
        string $type,
    ): void {
        // Cadastrar só na Roupas: a Express não aparece como opção e escolhê-la responde 404.
        $creator = $this->operatorOn($this->roupas, $module, ['list', 'create']);
        $this->actingAs($creator)->getJson($this->url($segment))->assertOk()->assertJsonCount(1, 'creatable');
        $this->postJson($this->url($segment), $this->body('Fiscal', [$this->roupas, $this->express]))->assertNotFound();
        $this->assertSame(0, $model::count());

        // Com cargos nas duas empresas, o cadastro múltiplo grava nas duas.
        $both = $this->role($this->gartan, 'Cadastrador '.$module, ["account.$module.list", "account.$module.create"]);
        $multi = $this->operator($this->gartan, [
            [$both, AccessNode::company($this->gartan->id, $this->roupas->id)],
            [$both, AccessNode::company($this->gartan->id, $this->express->id)],
        ]);
        $this->actingAs($multi)->postJson($this->url($segment), $this->body('Fiscal', [$this->roupas, $this->express]))->assertOk();
        $this->assertSame(2, $model::count());
    }

    #[DataProvider('catalogs')]
    public function test_inactive_items_stay_listed_and_the_filters_work_in_the_database(
        string $segment,
        string $module,
        string $table,
        string $model,
        string $type,
    ): void {
        $this->actingAs($this->owner);

        foreach (['Atendimento', 'Comercial', 'Gerência', 'Logística'] as $name) {
            $this->postJson($this->url($segment), $this->body($name, [$this->roupas]))->assertOk();
        }
        $this->patchJson(
            $this->url($segment, '/'.$model::where('name', 'Logística')->value('id')),
            ['name' => 'Logística', 'active' => false],
        )->assertOk();

        $list = $this->url($segment);
        $this->getJson($list)->assertJsonPath($table.'.total', 4)
            ->assertJsonPath($table.'.data.0.name', 'Atendimento')
            ->assertJsonMissingPath($table.'.data.0.normalized_name');
        $this->getJson($list.'?status=inactive')->assertJsonPath($table.'.total', 1);
        $this->getJson($list.'?status=active')->assertJsonPath($table.'.total', 3);
        // Busca sem acento e sem caixa.
        $this->getJson($list.'?search=GERENCIA')->assertJsonPath($table.'.total', 1);
        $this->getJson($list.'?search=logistica&status=inactive')->assertJsonPath($table.'.total', 1);
        $this->getJson($list.'?search=%25')->assertJsonPath($table.'.total', 0);
        $this->getJson($list.'?per_page=10&status=bogus')->assertJsonPath($table.'.per_page', 10)
            ->assertJsonPath('filters.status', '');
    }

    #[DataProvider('catalogs')]
    public function test_listing_does_not_grow_in_queries_with_the_number_of_rows_or_companies(
        string $segment,
        string $module,
        string $table,
        string $model,
        string $type,
    ): void {
        $this->actingAs($this->owner);
        $this->getJson($this->url($segment))->assertOk();

        $measure = function () use ($segment): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson($this->url($segment).'?per_page=100')->assertOk();

            return count(DB::getQueryLog());
        };

        $this->postJson($this->url($segment), $this->body('Primeiro', [$this->roupas]))->assertOk();
        $few = $measure();

        for ($i = 0; $i < 30; $i++) {
            $model::register(
                $model === Department::class ? new CreateDepartmentDTO("Item $i", true) : new CreateJobTitleDTO("Item $i", true),
                $this->gartan->id,
                $i % 2 === 0 ? $this->roupas->id : $this->express->id,
            )->saveOrFail();
        }

        $this->assertSame($few, $measure());
    }

    #[DataProvider('catalogs')]
    public function test_events_are_audited_on_the_company_node_without_the_derived_name(
        string $segment,
        string $module,
        string $table,
        string $model,
        string $type,
    ): void {
        $this->actingAs($this->owner);
        $this->postJson($this->url($segment), $this->body('Financeiro', [$this->roupas]))->assertOk();
        $item = $model::sole();

        $created = AuditRecord::where('entity_type', $type)->where('event', 'created')->sole();
        $this->assertSame($this->gartan->id, $created->account_id);
        $this->assertSame($this->roupas->id, $created->company_id);
        $this->assertNull($created->establishment_id);
        $this->assertSame($this->owner->id, $created->actor_id);
        $this->assertSame('Financeiro', $created->entity_name);
        $this->assertArrayNotHasKey('normalized_name', $created->changes);

        $this->patchJson($this->url($segment, '/'.$item->id), ['name' => 'Financeiro'])->assertOk();
        $this->assertSame(0, AuditRecord::where('entity_type', $type)->where('event', 'updated')->count());

        $this->patchJson($this->url($segment, '/'.$item->id), ['name' => 'Financeiro', 'active' => false])->assertOk();
        $updated = AuditRecord::where('entity_type', $type)->where('event', 'updated')->sole();
        $this->assertSame(['active'], array_keys($updated->changes));
        $this->assertSame($this->roupas->id, $updated->company_id);

        // Validação recusada não deixa histórico.
        $before = AuditRecord::count();
        $this->postJson($this->url($segment), $this->body('financeiro', [$this->roupas]))->assertUnprocessable();
        $this->assertSame($before, AuditRecord::count());
    }

    #[DataProvider('catalogs')]
    public function test_the_auditor_of_another_company_does_not_see_the_events(
        string $segment,
        string $module,
        string $table,
        string $model,
        string $type,
    ): void {
        $this->actingAs($this->owner)->postJson($this->url($segment), $this->body('Financeiro', [$this->roupas]))->assertOk();

        $role = $this->role($this->gartan, 'Auditor', ['account.audit.list', 'account.audit.view']);
        $auditor = $this->operator($this->gartan, [[$role, AccessNode::company($this->gartan->id, $this->express->id)]]);

        $this->actingAs($auditor)->getJson('/conta/auditoria?entity='.$type)->assertOk()->assertJsonPath('records.total', 0);

        $own = $this->operator($this->gartan, [[$role, AccessNode::company($this->gartan->id, $this->roupas->id)]]);
        $this->actingAs($own)->getJson('/conta/auditoria?entity='.$type)->assertOk()->assertJsonPath('records.total', 1);
    }

    #[DataProvider('catalogs')]
    public function test_saas_inspection_blocks_writes_in_read_only_and_keeps_the_real_author_in_edit_mode(
        string $segment,
        string $module,
        string $table,
        string $model,
        string $type,
    ): void {
        $role = $this->role($this->gartan, 'Gestor', array_map(
            fn (string $action) => "account.$module.$action",
            ['list', 'view', 'create', 'update', 'activate', 'deactivate'],
        ));
        $ana = $this->operator($this->gartan, [[$role, AccessNode::company($this->gartan->id, $this->roupas->id)]]);
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $start = '/saas/contas/'.$this->gartan->id.'/inspecao';

        $this->actingAs($platform)->postJson($start, ['user_id' => $ana->id, 'mode' => 'read-only'])->assertOk();
        $this->getJson($this->url($segment))->assertOk();
        $this->postJson($this->url($segment), $this->body('Bloqueado', [$this->roupas]))->assertForbidden();
        $this->assertSame(0, $model::count());
        $this->postJson('/inspecao/encerrar')->assertOk();

        $this->actingAs($platform)->postJson($start, ['user_id' => $ana->id, 'mode' => 'edit'])->assertOk();
        $this->postJson($this->url($segment), $this->body('Permitido', [$this->roupas]))->assertOk();
        $event = AuditRecord::where('entity_type', $type)->where('event', 'created')->sole();
        $this->assertSame($platform->id, $event->actor_id);
        $this->assertSame($ana->id, $event->represented_user_id);
        $this->assertSame($this->roupas->id, $event->company_id);

        // A autoridade é a do representado: a outra empresa continua fechada.
        $this->postJson($this->url($segment), $this->body('Fora', [$this->express]))->assertNotFound();
    }

    public function test_a_unit_assignment_does_not_open_company_modules(): void
    {
        $unit = $this->roupas->primaryEstablishment()->sole();
        $role = $this->role($this->gartan, 'Gerente de unidade', [
            'account.departments.list',
            'account.departments.create',
            'account.job-titles.list',
        ]);
        $manager = $this->operator($this->gartan, [[$role, AccessNode::establishment($this->gartan->id, $this->roupas->id, $unit->id)]]);

        $this->actingAs($manager)->getJson($this->url('departamentos'))->assertForbidden();
        $this->postJson($this->url('departamentos'), $this->body('Fiscal', [$this->roupas]))->assertForbidden();
        $this->assertSame(0, Department::count());
    }

    #[DataProvider('catalogs')]
    public function test_an_inactive_company_is_not_offered_and_cannot_receive_records(
        string $segment,
        string $module,
        string $table,
        string $model,
        string $type,
    ): void {
        $this->actingAs($this->owner);
        $this->express->forceFill(['active' => false])->saveQuietly();

        $this->getJson($this->url($segment))->assertOk()->assertJsonCount(1, 'creatable');
        $this->postJson($this->url($segment), $this->body('Fiscal', [$this->express]))->assertNotFound();
        $this->assertSame(0, $model::count());
    }

    public function test_catalogs_are_independent_of_access_roles(): void
    {
        $roles = Role::count();
        $assignments = RoleAssignment::count();
        $grants = DB::table('permission_role')->count();

        $this->actingAs($this->owner);
        $this->postJson($this->url('funcoes'), $this->body('Gerente comercial', [$this->roupas]))->assertOk();
        $this->postJson($this->url('departamentos'), $this->body('Gerente comercial', [$this->roupas]))->assertOk();

        $this->assertSame($roles, Role::count());
        $this->assertSame($assignments, RoleAssignment::count());
        $this->assertSame($grants, DB::table('permission_role')->count());
        $this->assertSame(0, Role::where('name', 'Gerente comercial')->count());
        $this->assertSame(1, JobTitle::count());
        $this->assertSame(1, Department::count());

        // Função e departamento são catálogos separados: o mesmo nome convive nos dois.
        $this->assertSame(JobTitle::first()->name, Department::first()->name);
    }

    public function test_the_administrator_receives_the_new_permissions_with_company_targets(): void
    {
        $this->assertTrue($this->enterAccount($this->owner));
        $visible = app(EffectivePermissions::class)->visibleSlugsFor($this->owner);

        foreach (['departments', 'job-titles'] as $module) {
            foreach (['list', 'view', 'create', 'update', 'activate', 'deactivate'] as $action) {
                $slug = "account.$module.$action";

                $this->assertSame(['company'], config('permissions.targets')[$slug], $slug);
                $this->assertTrue(Permission::where('slug', $slug)->exists(), $slug);
                $this->assertContains($slug, $visible, $slug);
            }
        }
    }

    public function test_the_help_article_follows_the_permission(): void
    {
        $help = $this->role($this->gartan, 'Ajuda', ['self.help.list', 'self.help.view']);
        $plain = $this->operator($this->gartan, [[$help, AccessNode::tenant($this->gartan->id)]]);
        $reader = $this->operatorOn($this->roupas, 'job-titles', ['list']);

        $this->actingAs($this->owner)->getJson('/conta/ajuda/departamentos-funcoes')->assertOk()
            ->assertJsonPath('article.title', 'Departamentos e Funções');
        $this->actingAs($plain)->getJson('/conta/ajuda/departamentos-funcoes')->assertNotFound();
        $this->assertNotNull($reader);
    }
}
