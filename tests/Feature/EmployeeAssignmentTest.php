<?php

namespace Tests\Feature;

use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Domains\Colaboradores\Domain\DTOs\Employees\CreateEmployeeDTO;
use App\Domains\Colaboradores\Domain\Models\Department;
use App\Domains\Colaboradores\Domain\Models\Employee;
use App\Domains\Colaboradores\Domain\Models\EmployeeAssignment;
use App\Domains\Colaboradores\Domain\Models\JobTitle;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use App\Support\BusinessDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Concerns\BuildsCommercialScenario;
use Tests\TestCase;

/**
 * S05 do plano 002: lotações do colaborador, vigência, principal, histórico, escopo por unidade e listagem.
 */
class EmployeeAssignmentTest extends TestCase
{
    use BuildsCommercialScenario;
    use RefreshDatabase;

    private Client $gartan;

    private Company $roupas;

    private Company $express;

    private Establishment $sede;

    private Establishment $guanambi;

    private Establishment $caetite;

    private Establishment $expressSede;

    private User $owner;

    private Person $maria;

    private Employee $link;

    private Department $financeiro;

    private Department $vendas;

    private JobTitle $gerente;

    private JobTitle $analista;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-26 12:00:00');

        $this->gartan = $this->account();
        $this->roupas = $this->company($this->gartan, 'Gartan Roupas', '11222333');
        $this->express = $this->company($this->gartan, 'Gartan Express', '44555666');
        $this->sede = $this->roupas->primaryEstablishment()->sole();
        $this->guanambi = $this->unit($this->roupas, 'Guanambi');
        $this->caetite = $this->unit($this->roupas, 'Caetité');
        $this->expressSede = $this->express->primaryEstablishment()->sole();
        $this->owner = $this->administrator($this->gartan);

        $this->financeiro = $this->catalog(Department::class, $this->roupas, 'Financeiro');
        $this->vendas = $this->catalog(Department::class, $this->roupas, 'Vendas');
        $this->gerente = $this->catalog(JobTitle::class, $this->roupas, 'Gerente');
        $this->analista = $this->catalog(JobTitle::class, $this->roupas, 'Analista');

        $this->actingAs($this->owner);
        $this->postJson('/conta/colaboradores', [
            'name' => 'Maria Souza', 'person_type' => 'individual', 'document' => '52998224725',
            'companies' => [$this->roupas->code, $this->express->code],
        ])->assertNoContent();
        $this->maria = Person::where('document', '52998224725')->sole();
        $this->link = Employee::where('company_id', $this->roupas->id)->sole();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @param class-string<Department|JobTitle> $model */
    private function catalog(string $model, Company $company, string $name, bool $active = true): Department|JobTitle
    {
        return $model::create([
            'account_id' => $this->gartan->id,
            'company_id' => $company->id,
            'name' => $name,
            'normalized_name' => mb_strtolower($name),
            'active' => $active,
        ]);
    }

    private function url(?Employee $link = null, string $suffix = '', ?Person $person = null): string
    {
        $link ??= $this->link;
        $person ??= $this->maria;

        return '/conta/colaboradores/'.$person->id.'/vinculos/'.$link->id.'/lotacoes'.$suffix;
    }

    /** @return array<string,mixed> */
    private function body(Establishment $unit, string $start, ?string $end = null, array $extra = []): array
    {
        return [
            'establishment' => $unit->code,
            'starts_on' => $start,
            'ends_on' => $end,
            'job_title_id' => $this->gerente->id,
            'department_id' => $this->financeiro->id,
            ...$extra,
        ];
    }

    private function assign(Establishment $unit, string $start, ?string $end = null, array $extra = []): EmployeeAssignment
    {
        $this->postJson($this->url(), $this->body($unit, $start, $end, $extra))->assertNoContent();

        return EmployeeAssignment::where('establishment_id', $unit->id)->where('starts_on', $start)->sole();
    }

    /** @param list<string> $slugs */
    private function managerOf(Establishment $unit, array $slugs): User
    {
        static $sequence = 0;

        $role = $this->role($this->gartan, 'Gestor de unidade '.++$sequence, $slugs);

        return $this->operator($this->gartan, [[$role, AccessNode::establishment(
            $this->gartan->id,
            $unit->company_id,
            $unit->id,
        )]]);
    }

    public function test_an_employee_can_work_in_two_units_with_different_roles_departments_and_one_primary(): void
    {
        $a = $this->assign($this->guanambi, '2026-01-01', null, ['is_primary' => true]);
        $b = $this->assign($this->caetite, '2026-03-01', null, [
            'job_title_id' => $this->analista->id, 'department_id' => $this->vendas->id,
        ]);

        $this->assertSame($this->gerente->id, $a->job_title_id);
        $this->assertSame($this->analista->id, $b->job_title_id);
        $this->assertSame($this->vendas->id, $b->department_id);
        $this->assertTrue($a->is_primary);
        $this->assertFalse($b->is_primary);

        // A segunda principal sobreposta é recusada e nada é gravado.
        $this->postJson($this->url(), $this->body($this->sede, '2026-05-01', null, ['is_primary' => true]))
            ->assertUnprocessable()->assertJsonValidationErrors('is_primary');
        $this->assertSame(2, EmployeeAssignment::count());

        // Os dados de Pessoa e os contatos centrais não são tocados.
        $this->assertSame('Maria Souza', $this->maria->fresh()->name);
        $this->assertFalse(Schema::hasColumn('employee_assignments', 'document'));
    }

    public function test_periods_adjacent_overlapping_future_current_and_ended_follow_the_contract(): void
    {
        $first = $this->assign($this->guanambi, '2026-01-01', '2026-06-30');

        // Adjacente (primeiro dia após o último) é aceito, um dia sobreposto não.
        $this->postJson($this->url(), $this->body($this->guanambi, '2026-06-30', '2026-08-31'))
            ->assertUnprocessable()->assertJsonValidationErrors('starts_on');
        $second = $this->assign($this->guanambi, '2026-07-01', '2026-08-31');
        $future = $this->assign($this->guanambi, '2027-01-01');

        // Outra unidade pode coexistir no mesmo período.
        $this->assign($this->caetite, '2026-01-01', '2026-06-30');

        // Uma lotação futura sem data final não impede nada antes dela, mas bloqueia o que a cruza.
        $this->postJson($this->url(), $this->body($this->guanambi, '2026-12-01', '2027-02-01'))
            ->assertUnprocessable();

        $this->assertSame('ended', $first->fresh()->statusOn(BusinessDate::todayIn('America/Sao_Paulo')));
        $this->assertSame('ended', $second->fresh()->statusOn(BusinessDate::todayIn('America/Sao_Paulo')));
        $this->assertSame('future', $future->fresh()->statusOn(BusinessDate::todayIn('America/Sao_Paulo')));

        // Um dia de duração é válido, e o fim antes do início é recusado.
        $this->assign($this->sede, '2026-11-26', '2026-11-26');
        $this->postJson($this->url(), $this->body($this->sede, '2026-12-10', '2026-12-01'))
            ->assertUnprocessable()->assertJsonValidationErrors('ends_on');
        $this->assertSame('current', EmployeeAssignment::where('establishment_id', $this->sede->id)->sole()
            ->statusOn('2026-11-26'));

        // Sem data final não significa que já começou.
        $this->assertSame('future', $future->statusOn('2026-11-26'));
    }

    public function test_a_role_change_keeps_history_and_a_same_day_change_is_a_correction(): void
    {
        $old = $this->assign($this->guanambi, '2026-01-01', null, ['is_primary' => true]);

        $this->postJson($this->url(null, '/'.$old->id.'/trocar'), [
            'effective_on' => '2026-09-01', 'job_title_id' => $this->analista->id, 'department_id' => $this->vendas->id,
        ])->assertNoContent();

        $old->refresh();
        $new = EmployeeAssignment::where('starts_on', '2026-09-01')->sole();
        $this->assertSame('2026-08-31', substr($old->ends_on, 0, 10));
        $this->assertSame($this->gerente->id, $old->job_title_id);
        $this->assertSame($this->analista->id, $new->job_title_id);
        $this->assertNull($new->ends_on);
        $this->assertTrue($new->is_primary);

        // Mesmo dia do início: correção do próprio período, sem criar outro nem dia duplicado.
        $this->postJson($this->url(null, '/'.$new->id.'/trocar'), [
            'effective_on' => '2026-09-01', 'job_title_id' => $this->gerente->id, 'department_id' => $this->financeiro->id,
        ])->assertNoContent();
        $this->assertSame(2, EmployeeAssignment::count());
        $this->assertSame($this->gerente->id, $new->fresh()->job_title_id);

        // Antes do início do período a troca é recusada.
        $this->postJson($this->url(null, '/'.$new->id.'/trocar'), ['effective_on' => '2026-08-01'])
            ->assertUnprocessable()->assertJsonValidationErrors('effective_on');

        // Troca e histórico na mesma operação lógica de auditoria.
        $operations = AuditRecord::where('entity_type', 'employee-assignment')->where('entity_id', $old->id)
            ->where('event', 'updated')->pluck('operation_id');
        $created = AuditRecord::where('entity_type', 'employee-assignment')->where('entity_id', $new->id)
            ->where('event', 'created')->pluck('operation_id');
        $this->assertNotEmpty($operations);
        $this->assertSame($operations->last(), $created->first());
    }

    public function test_retroactive_assignment_is_accepted_only_without_overlap_and_is_audited(): void
    {
        $this->assign($this->guanambi, '2026-06-01');

        $this->postJson($this->url(), $this->body($this->guanambi, '2026-01-01', '2026-06-01'))
            ->assertUnprocessable();
        $retro = $this->assign($this->guanambi, '2025-01-01', '2026-05-31');

        $event = AuditRecord::where('entity_type', 'employee-assignment')->where('entity_id', $retro->id)
            ->where('event', 'created')->sole();
        $this->assertSame($this->roupas->id, $event->company_id);
        $this->assertSame($this->guanambi->id, $event->establishment_id);
        $this->assertSame($this->owner->id, $event->actor_id);
        $this->assertStringNotContainsString('52998224725', json_encode($event->toArray()));
    }

    public function test_ending_the_primary_requires_the_new_primary_and_transfers_the_mark(): void
    {
        $a = $this->assign($this->guanambi, '2026-01-01', null, ['is_primary' => true]);
        $b = $this->assign($this->caetite, '2026-02-01');

        $this->patchJson($this->url(null, '/'.$a->id.'/encerrar'), ['ends_on' => '2026-12-31'])
            ->assertUnprocessable()->assertJsonValidationErrors('new_primary_id');
        $this->assertNull($a->fresh()->ends_on);

        $this->patchJson($this->url(null, '/'.$a->id.'/encerrar'), ['ends_on' => '2026-12-31', 'new_primary_id' => $b->id])
            ->assertNoContent();

        $this->assertSame('2026-12-31', substr($a->fresh()->ends_on, 0, 10));
        $this->assertFalse($a->fresh()->is_primary);
        $this->assertTrue($b->fresh()->is_primary);

        // Sem outra lotação, o colaborador fica sem principal e o histórico segue.
        $this->patchJson($this->url(null, '/'.$b->id.'/encerrar'), ['ends_on' => '2026-12-31'])->assertNoContent();
        $this->assertSame(0, EmployeeAssignment::whereNull('ends_on')->count());
        $this->assertSame(2, EmployeeAssignment::count());

        // Data final fora do período é recusada.
        $this->patchJson($this->url(null, '/'.$b->id.'/encerrar'), ['ends_on' => '2025-01-01'])->assertUnprocessable();
        $this->patchJson($this->url(null, '/'.$b->id.'/encerrar'), ['ends_on' => '2027-06-01'])->assertUnprocessable();
    }

    public function test_setting_the_primary_moves_the_mark_in_one_operation(): void
    {
        $a = $this->assign($this->guanambi, '2026-01-01', null, ['is_primary' => true]);
        $b = $this->assign($this->caetite, '2026-02-01');

        $this->patchJson($this->url(null, '/'.$b->id.'/principal'))->assertNoContent();

        $this->assertFalse($a->fresh()->is_primary);
        $this->assertTrue($b->fresh()->is_primary);
        $this->assertSame(1, EmployeeAssignment::where('is_primary', true)->whereNull('ends_on')->count());
    }

    public function test_inactivation_ends_open_assignments_keeps_history_and_reactivation_does_not_reopen(): void
    {
        $login = User::factory()->forAccount($this->gartan->id)->create();
        $this->membership($login)->forceFill(['person_id' => $this->maria->id])->saveOrFail();

        $current = $this->assign($this->guanambi, '2026-01-01', null, ['is_primary' => true]);
        $closed = $this->assign($this->caetite, '2025-01-01', '2025-12-31');
        $future = $this->assign($this->sede, '2027-01-01');
        $customer = Employee::where('company_id', $this->express->id)->sole();
        $this->postJson($this->url($customer), $this->body($this->expressSede, '2026-01-01', null, [
            'job_title_id' => null, 'department_id' => null,
        ]))->assertNoContent();
        $expressAssignment = EmployeeAssignment::where('employee_id', $customer->id)->sole();

        $this->patchJson('/conta/colaboradores/'.$this->maria->id.'/vinculos/'.$this->link->id, ['active' => '0'])
            ->assertNoContent();

        $this->assertSame('2026-11-26', substr($current->fresh()->ends_on, 0, 10));
        $this->assertSame('2025-12-31', substr($closed->fresh()->ends_on, 0, 10));
        $this->assertNull($future->fresh()->ends_on);
        $this->assertNull($expressAssignment->fresh()->ends_on);
        $this->assertSame(3, EmployeeAssignment::where('employee_id', $this->link->id)->count());
        $this->assertTrue($customer->fresh()->active);
        $this->assertSame('active', $this->membership($login)->fresh()->status);

        // Inativar e criar lotação nova: o vínculo inativo recusa.
        $this->postJson($this->url(), $this->body($this->guanambi, '2026-11-27'))
            ->assertUnprocessable()->assertJsonValidationErrors('employee');

        // Reativar não reabre nada nem duplica o vínculo.
        $this->patchJson('/conta/colaboradores/'.$this->maria->id.'/vinculos/'.$this->link->id, ['active' => '1'])
            ->assertNoContent();
        $this->assertSame('2026-11-26', substr($current->fresh()->ends_on, 0, 10));
        $this->assertSame(2, Employee::count());
        $this->assertSame(0, EmployeeAssignment::where('employee_id', $this->link->id)->whereNull('ends_on')
            ->where('starts_on', '<=', '2026-11-26')->count());

        $this->assign($this->guanambi, '2026-11-27');

        // Evento de auditoria do encerramento, com empresa e unidade.
        $event = AuditRecord::where('entity_type', 'employee-assignment')->where('entity_id', $current->id)
            ->where('event', 'updated')->latest('id')->first();
        $this->assertSame($this->guanambi->id, $event->establishment_id);
    }

    public function test_inactive_catalog_items_and_other_companies_are_not_available(): void
    {
        $inactive = $this->catalog(Department::class, $this->roupas, 'Antigo', false);
        $inactiveJob = $this->catalog(JobTitle::class, $this->roupas, 'Extinta', false);
        $foreignJob = $this->catalog(JobTitle::class, $this->express, 'Gerente Express');
        $foreignDepartment = $this->catalog(Department::class, $this->express, 'Financeiro Express');

        $this->postJson($this->url(), $this->body($this->guanambi, '2026-01-01', null, ['department_id' => $inactive->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('department_id');
        $this->postJson($this->url(), $this->body($this->guanambi, '2026-01-01', null, ['job_title_id' => $inactiveJob->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('job_title_id');
        $this->postJson($this->url(), $this->body($this->guanambi, '2026-01-01', null, ['job_title_id' => $foreignJob->id]))
            ->assertUnprocessable();
        $this->postJson($this->url(), $this->body($this->guanambi, '2026-01-01', null, ['department_id' => $foreignDepartment->id]))
            ->assertUnprocessable();

        // Estabelecimento de outra empresa responde como inexistente.
        $this->postJson($this->url(), $this->body($this->expressSede, '2026-01-01'))->assertNotFound();
        $this->assertSame(0, EmployeeAssignment::count());

        // O item que ficou inativo continua na lotação antiga e nas opções dela.
        $assignment = $this->assign($this->guanambi, '2026-01-01');
        $this->financeiro->forceFill(['active' => false])->saveQuietly();
        $this->patchJson($this->url(null, '/'.$assignment->id), [
            'starts_on' => '2026-01-01', 'job_title_id' => $this->gerente->id, 'department_id' => $this->financeiro->id,
        ])->assertNoContent();
        $this->assertSame($this->financeiro->id, $assignment->fresh()->department_id);
    }

    public function test_a_unit_manager_only_reaches_assignments_of_the_own_unit(): void
    {
        $this->assign($this->guanambi, '2026-01-01', null, ['is_primary' => true]);
        $caetiteAssignment = $this->assign($this->caetite, '2026-02-01');
        $other = Person::create(['account_id' => $this->gartan->id, 'name' => 'Pedro Caetité', 'person_type' => 'individual']);
        $otherLink = Employee::register(
            new CreateEmployeeDTO(null, null, null),
            $this->gartan->id,
            $this->roupas->id,
            $other->id,
        );
        $otherLink->saveOrFail();
        DB::table('employee_assignments')->insert([
            'account_id' => $this->gartan->id, 'company_id' => $this->roupas->id, 'employee_id' => $otherLink->id,
            'establishment_id' => $this->caetite->id, 'starts_on' => '2026-03-01', 'is_primary' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $manager = $this->managerOf($this->guanambi, [
            'account.employees.list', 'account.employees.view', 'account.employees.assign',
        ]);
        $this->actingAs($manager);

        // Lista: só a pessoa lotada na unidade dele, sem o vínculo da Express nem a lotação de Caetité.
        $list = $this->getJson('/conta/colaboradores')->assertOk();
        $list->assertJsonPath('employees.total', 1)->assertJsonPath('employees.data.0.name', 'Maria Souza');
        $list->assertJsonPath('employees.data.0.links_count', 1);
        $this->assertStringNotContainsString('Caetité', $list->getContent());
        $this->assertStringNotContainsString('Pedro', $list->getContent());
        $this->assertStringNotContainsString('Express', $list->getContent());
        $list->assertJsonPath('employees.data.0.can_edit', true);

        // Ficha: lotações da própria unidade, sem dados da relação nem permissão de editar o vínculo.
        $form = $this->getJson('/conta/colaboradores/'.$this->maria->id.'/editar')->assertOk();
        $form->assertJsonPath('links.0.scope', 'unit')
            ->assertJsonPath('links.0.work_email', null)
            ->assertJsonPath('links.0.can_update', false)
            ->assertJsonPath('links.0.can_change_status', false)
            ->assertJsonCount(1, 'links.0.assignments')
            ->assertJsonPath('links.0.assignments.0.establishment_name', 'Guanambi')
            ->assertJsonCount(1, 'links.0.assignment_options.establishments');
        $this->assertStringNotContainsString('Caetité', $form->getContent());

        // A lotação de Caetité e o colaborador só de Caetité não existem para ele.
        $this->patchJson($this->url(null, '/'.$caetiteAssignment->id.'/encerrar'), ['ends_on' => '2026-12-31'])
            ->assertNotFound();
        $this->getJson('/conta/colaboradores/'.$other->id.'/editar')->assertNotFound();
        $this->postJson($this->url(), $this->body($this->caetite, '2026-04-01'))->assertNotFound();
        $this->postJson($this->url(), $this->body($this->sede, '2026-04-01'))->assertNotFound();
        $this->assertNull($caetiteAssignment->fresh()->ends_on);

        // Mas gerencia a própria unidade e não alcança a empresa inteira.
        // Encerrar a principal com lotação vigente só em unidade que ele não vê é recusado, sem alterar nada.
        $own = EmployeeAssignment::where('establishment_id', $this->guanambi->id)->sole();
        $this->patchJson($this->url(null, '/'.$own->id.'/encerrar'), ['ends_on' => '2026-12-31'])
            ->assertUnprocessable()->assertJsonValidationErrors('new_primary_id');
        $this->assertNull($own->fresh()->ends_on);
        $this->assertNull($caetiteAssignment->fresh()->ends_on);
        $this->patchJson($this->url(null, '/'.$own->id), [
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'job_title_id' => $this->gerente->id,
            'department_id' => $this->financeiro->id,
        ])->assertNoContent();
        $this->patchJson('/conta/colaboradores/'.$this->maria->id.'/vinculos/'.$this->link->id, ['active' => '0'])
            ->assertForbidden();
        $this->postJson('/conta/colaboradores', [
            'name' => 'Novo', 'person_type' => 'individual', 'document' => '11144477735', 'companies' => [$this->roupas->code],
        ])->assertForbidden();
        $this->getJson('/conta/colaboradores/cadastrar')->assertForbidden();

        // A etiqueta Colaborador aparece pela lotação na unidade coberta.
        $this->getJson('/conta/colaboradores/'.$this->maria->id.'/visualizar')->assertOk()
            ->assertJsonPath('tags.0.name', 'Colaborador');
    }

    public function test_assigning_needs_the_assign_permission_and_is_blocked_in_read_only_inspection(): void
    {
        $viewer = $this->managerOf($this->guanambi, ['account.employees.list', 'account.employees.view']);
        $this->assign($this->guanambi, '2026-01-01');

        $this->actingAs($viewer);
        $this->getJson('/conta/colaboradores/'.$this->maria->id.'/editar')->assertOk()
            ->assertJsonPath('links.0.assignment_options', null)
            ->assertJsonPath('links.0.assignments.0.can_manage', false);
        $this->postJson($this->url(), $this->body($this->guanambi, '2027-01-01'))->assertForbidden();
        $this->patchJson($this->url(null, '/'.EmployeeAssignment::sole()->id.'/encerrar'), ['ends_on' => '2026-12-31'])
            ->assertForbidden();

        // Empresa inteira com assign, mas inspeção somente leitura.
        $role = $this->role($this->gartan, 'Gestor da Roupas', [
            'account.employees.list', 'account.employees.view', 'account.employees.assign',
        ]);
        $ana = $this->operator($this->gartan, [[$role, AccessNode::company($this->gartan->id, $this->roupas->id)]]);
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $start = '/saas/contas/'.$this->gartan->id.'/inspecao';

        $this->actingAs($platform)->postJson($start, ['user_id' => $ana->id, 'mode' => 'read-only'])->assertOk();
        $this->postJson($this->url(), $this->body($this->caetite, '2027-01-01'))->assertForbidden();
        $this->postJson('/inspecao/encerrar')->assertOk();

        $this->actingAs($platform)->postJson($start, ['user_id' => $ana->id, 'mode' => 'edit'])->assertOk();
        $this->postJson($this->url(), $this->body($this->caetite, '2027-01-01'))->assertNoContent();
        $event = AuditRecord::where('entity_type', 'employee-assignment')->where('event', 'created')->latest('id')->first();
        $this->assertSame($platform->id, $event->actor_id);
        $this->assertSame($ana->id, $event->represented_user_id);
    }

    public function test_tenants_do_not_share_units_departments_or_employees(): void
    {
        $other = $this->account('Outro Grupo');
        $foreign = $this->company($other, 'Outro', '77888999');
        $stranger = $this->administrator($other);

        $this->actingAs($stranger);
        $this->postJson($this->url(), $this->body($foreign->primaryEstablishment()->sole(), '2026-01-01'))->assertNotFound();
        $this->getJson('/conta/colaboradores/'.$this->maria->id.'/editar')->assertNotFound();
        $this->getJson('/conta/colaboradores')->assertOk()->assertJsonPath('employees.total', 0);
        $this->assertSame(0, EmployeeAssignment::count());
    }

    public function test_the_listing_has_one_row_per_person_and_combined_filters_match_the_same_assignment(): void
    {
        $this->assign($this->guanambi, '2026-01-01', null, ['job_title_id' => $this->gerente->id, 'department_id' => $this->financeiro->id]);
        $this->assign($this->caetite, '2026-01-01', null, ['job_title_id' => $this->analista->id, 'department_id' => $this->vendas->id]);
        $this->assign($this->sede, '2027-02-01');
        $this->actingAs($this->owner);

        $all = $this->getJson('/conta/colaboradores')->assertOk();
        $all->assertJsonPath('employees.total', 1)->assertJsonCount(3, 'employees.data.0.units');
        $this->assertSame(1, collect($all->json('employees.data'))->where('name', 'Maria Souza')->count());
        $all->assertJsonCount(3, 'filterOptions.establishments');

        $this->getJson('/conta/colaboradores?establishment='.$this->guanambi->code)->assertJsonPath('employees.total', 1);

        // Gerente só existe em Guanambi: em Caetité (analista), a combinação não corresponde a nenhuma lotação.
        $this->getJson('/conta/colaboradores?establishment='.$this->caetite->code.'&job_title=gerente')
            ->assertJsonPath('employees.total', 0);
        $this->getJson('/conta/colaboradores?establishment='.$this->guanambi->code.'&job_title=gerente&department=financeiro')
            ->assertJsonPath('employees.total', 1);
        $this->getJson('/conta/colaboradores?establishment='.$this->caetite->code.'&department=financeiro')
            ->assertJsonPath('employees.total', 0);

        // Situação da lotação: a futura é da sede, e ninguém está encerrado nem sem lotação.
        $this->getJson('/conta/colaboradores?assignment=future&establishment='.$this->sede->code)->assertJsonPath('employees.total', 1);
        $this->getJson('/conta/colaboradores?assignment=future&establishment='.$this->guanambi->code)->assertJsonPath('employees.total', 0);
        $this->getJson('/conta/colaboradores?assignment=ended')->assertJsonPath('employees.total', 0);
        $this->getJson('/conta/colaboradores?assignment=current&job_title=analista')->assertJsonPath('employees.total', 1);

        // Colaborador do vínculo da Express não tem lotação: aparece em "sem lotação" só pelo vínculo dele.
        $this->getJson('/conta/colaboradores?assignment=none&company='.$this->express->code)->assertJsonPath('employees.total', 1);
        $this->getJson('/conta/colaboradores?assignment=none&company='.$this->roupas->code)->assertJsonPath('employees.total', 0);
    }

    public function test_listing_filters_pagination_and_counts_respect_the_scope_and_avoid_n_plus_one(): void
    {
        $this->assign($this->guanambi, '2026-01-01');
        $this->assign($this->caetite, '2026-01-01');

        foreach (range(1, 12) as $index) {
            $person = Person::create(['account_id' => $this->gartan->id, 'name' => sprintf('Pessoa %02d', $index), 'person_type' => 'individual']);
            $link = Employee::register(
                new CreateEmployeeDTO(null, null, null),
                $this->gartan->id,
                $this->roupas->id,
                $person->id,
            );
            $link->saveOrFail();
            foreach ([$this->guanambi, $this->caetite] as $unit) {
                EmployeeAssignment::insert([
                    'account_id' => $this->gartan->id, 'company_id' => $this->roupas->id, 'employee_id' => $link->id,
                    'establishment_id' => $unit->id, 'starts_on' => '2026-01-01', 'is_primary' => false,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        $manager = $this->managerOf($this->guanambi, ['account.employees.list', 'account.employees.view']);
        $this->actingAs($manager);

        $page = $this->getJson('/conta/colaboradores?per_page=10')->assertOk();
        $page->assertJsonPath('employees.total', 13)->assertJsonCount(10, 'employees.data');
        $this->assertStringNotContainsString('Caetité', $page->getContent());
        $this->getJson('/conta/colaboradores?establishment='.$this->caetite->code)->assertJsonPath('employees.total', 0);

        // Consultas por página não dependem da quantidade de pessoas.
        DB::enableQueryLog();
        $this->getJson('/conta/colaboradores?per_page=5')->assertOk();
        $small = count(DB::getQueryLog());
        DB::flushQueryLog();
        $this->getJson('/conta/colaboradores?per_page=25')->assertOk();
        $large = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame($small, $large);
    }

    public function test_institutional_check_reports_what_the_database_cannot_guarantee(): void
    {
        $a = $this->assign($this->guanambi, '2026-01-01', '2026-06-30', ['is_primary' => true]);
        $this->assertSame(0, Artisan::call('institutional:check'));

        // Sobreposição e principais duplicadas gravadas por fora da regra de domínio.
        DB::table('employee_assignments')->insert([
            'account_id' => $this->gartan->id, 'company_id' => $this->roupas->id, 'employee_id' => $this->link->id,
            'establishment_id' => $this->guanambi->id, 'starts_on' => '2026-06-01', 'ends_on' => '2026-09-30',
            'is_primary' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(1, Artisan::call('institutional:check'));
        $output = Artisan::output();
        $this->assertStringContainsString('se sobrepõem na mesma unidade', $output);
        $this->assertStringContainsString('principais', $output);
        $this->assertSame(2, EmployeeAssignment::count());
        $this->assertNotNull($a->fresh());
    }

    public function test_assignment_changes_never_touch_login_permissions_or_other_roles(): void
    {
        $login = User::factory()->forAccount($this->gartan->id)->create();
        $membership = $this->membership($login);
        $membership->forceFill(['person_id' => $this->maria->id])->saveOrFail();
        $assignments = $membership->assignments()->count();

        $a = $this->assign($this->guanambi, '2026-01-01', null, ['is_primary' => true]);
        $this->patchJson($this->url(null, '/'.$a->id.'/encerrar'), ['ends_on' => '2026-12-31'])->assertNoContent();

        $this->assertSame($assignments, $membership->assignments()->count());
        $this->assertSame('active', $membership->fresh()->status);
        $this->assertSame(2, Employee::count());
        $this->assertSame('Maria Souza', $this->maria->fresh()->name);
    }

    private function managerWithAssign(Establishment $unit): User
    {
        return $this->managerOf($unit, ['account.employees.list', 'account.employees.view', 'account.employees.assign']);
    }

    public function test_work_contacts_inherit_override_or_stay_empty_on_purpose_and_default_changes_do_not_overwrite(): void
    {
        $this->link->forceFill([
            'work_email' => 'padrao@roupas.test', 'work_phone' => '7133330000', 'work_whatsapp' => null,
        ])->saveQuietly();
        $this->maria->forceFill(['email' => 'pessoal@example.test', 'phone' => '71999990000'])->saveQuietly();

        // Sem personalização, a lotação herda os contatos do vínculo, nunca os da Pessoa.
        $inherit = $this->assign($this->guanambi, '2026-01-01');
        $this->assertSame('inherit', $inherit->contact_mode);
        $this->assertNull($inherit->work_contacts);
        $effective = $inherit->fresh()->effectiveContacts($this->link->fresh());
        $this->assertSame('padrao@roupas.test', $effective['email']);
        $this->assertSame('inherit', $effective['source']);

        $custom = $this->assign($this->caetite, '2026-01-01', null, [
            'contact_mode' => 'override', 'work_email' => 'Caetite@Roupas.test', 'work_phone' => '(77) 3333-1111',
        ]);
        $this->assertSame('override', $custom->contact_mode);
        $this->assertSame('caetite@roupas.test', $custom->work_contacts['email']);
        $this->assertSame('7733331111', $custom->work_contacts['phone']);

        // Ausência intencional: personalizado e vazio, sem herdar nada.
        $none = $this->assign($this->sede, '2026-01-01', null, ['contact_mode' => 'override']);
        $this->assertSame(['email' => null, 'phone' => null, 'whatsapp' => null], $none->work_contacts);
        $this->assertSame('none', $none->fresh()->effectiveContacts($this->link->fresh())['source']);
        $this->assertNull($none->fresh()->effectiveContacts($this->link->fresh())['email']);

        // Mudar o contato padrão acompanha só quem herda.
        $this->patchJson('/conta/colaboradores/'.$this->maria->id.'/vinculos/'.$this->link->id, [
            'work_email' => 'novo@roupas.test',
        ])->assertNoContent();
        $link = $this->link->fresh();
        $this->assertSame('novo@roupas.test', $inherit->fresh()->effectiveContacts($link)['email']);
        $this->assertSame('caetite@roupas.test', $custom->fresh()->effectiveContacts($link)['email']);
        $this->assertNull($none->fresh()->effectiveContacts($link)['email']);
        $this->assertSame('pessoal@example.test', $this->maria->fresh()->email);

        // A ficha mostra a origem de cada contato.
        $form = $this->getJson('/conta/colaboradores/'.$this->maria->id.'/editar')->assertOk();
        $rows = collect(collect($form->json('links'))->firstWhere('company_code', $this->roupas->code)['assignments'])
            ->keyBy('establishment_code');
        $this->assertSame('inherit', $rows[$this->guanambi->code]['effective_contacts']['source']);
        $this->assertSame('override', $rows[$this->caetite->code]['effective_contacts']['source']);
        $this->assertSame('none', $rows[$this->sede->code]['effective_contacts']['source']);
    }

    public function test_work_contacts_are_validated_preserved_by_corrections_and_follow_a_role_change(): void
    {
        $this->postJson($this->url(), $this->body($this->guanambi, '2026-01-01', null, [
            'contact_mode' => 'override', 'work_email' => 'invalido',
        ]))->assertUnprocessable()->assertJsonValidationErrors('work_email');
        $this->postJson($this->url(), $this->body($this->guanambi, '2026-01-01', null, ['contact_mode' => 'outro']))
            ->assertUnprocessable()->assertJsonValidationErrors('contact_mode');
        $this->assertSame(0, EmployeeAssignment::count());

        $a = $this->assign($this->guanambi, '2026-01-01', null, [
            'contact_mode' => 'override', 'work_email' => 'gua@roupas.test',
        ]);

        // Corrigir datas sem informar contatos nunca apaga a personalização.
        $this->patchJson($this->url(null, '/'.$a->id), ['starts_on' => '2026-01-02', 'job_title_id' => $this->gerente->id])
            ->assertNoContent();
        $this->assertSame('gua@roupas.test', $a->fresh()->work_contacts['email']);

        // Informar o modo volta a herdar e apaga o valor guardado.
        $this->patchJson($this->url(null, '/'.$a->id), ['starts_on' => '2026-01-02', 'contact_mode' => 'inherit'])
            ->assertNoContent();
        $this->assertSame('inherit', $a->fresh()->contact_mode);
        $this->assertNull($a->fresh()->work_contacts);

        // A troca de função leva os contatos da lotação para o período novo.
        $this->patchJson($this->url(null, '/'.$a->id), [
            'starts_on' => '2026-01-02', 'contact_mode' => 'override', 'work_phone' => '77999990000',
        ])->assertNoContent();
        $this->postJson($this->url(null, '/'.$a->id.'/trocar'), [
            'effective_on' => '2026-06-01', 'job_title_id' => $this->analista->id,
        ])->assertNoContent();
        $new = EmployeeAssignment::where('starts_on', '2026-06-01')->sole();
        $this->assertSame('override', $new->contact_mode);
        $this->assertSame('77999990000', $new->work_contacts['phone']);

        // Auditoria da alteração de contatos, com a unidade.
        $event = AuditRecord::where('entity_type', 'employee-assignment')->where('entity_id', $a->id)
            ->where('event', 'updated')->get()->first(fn ($record) => str_contains(json_encode($record->toArray()), 'work_contacts'));
        $this->assertNotNull($event);
        $this->assertSame($this->guanambi->id, $event->establishment_id);
    }

    public function test_a_unit_manager_edits_only_the_contacts_of_the_own_unit_and_sees_the_effective_inherited_value(): void
    {
        $this->link->forceFill(['work_email' => 'padrao@roupas.test'])->saveQuietly();
        $own = $this->assign($this->guanambi, '2026-01-01');
        $other = $this->assign($this->caetite, '2026-01-01', null, ['contact_mode' => 'override', 'work_phone' => '77111112222']);

        $this->actingAs($this->managerWithAssign($this->guanambi));

        $form = $this->getJson('/conta/colaboradores/'.$this->maria->id.'/editar')->assertOk();
        $form->assertJsonPath('links.0.work_email', null)
            ->assertJsonPath('links.0.assignments.0.effective_contacts.email', 'padrao@roupas.test')
            ->assertJsonPath('links.0.assignments.0.effective_contacts.source', 'inherit');
        $this->assertStringNotContainsString('77111112222', $form->getContent());

        $this->patchJson($this->url(null, '/'.$own->id), [
            'starts_on' => '2026-01-01', 'contact_mode' => 'override', 'work_email' => 'gua@roupas.test',
        ])->assertNoContent();
        $this->assertSame('gua@roupas.test', $own->fresh()->work_contacts['email']);

        // A lotação de outra unidade não existe para ele e nada nela muda.
        $this->patchJson($this->url(null, '/'.$other->id), [
            'starts_on' => '2026-01-01', 'contact_mode' => 'inherit',
        ])->assertNotFound();
        $this->assertSame('override', $other->fresh()->contact_mode);

        // Sem a permissão de gerenciar lotações, nem a própria unidade pode ser editada.
        $viewer = $this->managerOf($this->guanambi, ['account.employees.list', 'account.employees.view']);
        $this->actingAs($viewer)->patchJson($this->url(null, '/'.$own->id), [
            'starts_on' => '2026-01-01', 'contact_mode' => 'inherit',
        ])->assertForbidden();
        $this->assertSame('override', $own->fresh()->contact_mode);
    }

    public function test_the_primary_is_never_moved_or_ended_through_a_unit_the_manager_cannot_reach(): void
    {
        $hidden = $this->assign($this->caetite, '2026-01-01', null, ['is_primary' => true]);
        $own = $this->assign($this->guanambi, '2026-02-01', '2026-06-30');

        $manager = $this->managerWithAssign($this->guanambi);
        $this->actingAs($manager);

        // Tornar principal esbarra na principal de Caetité: recusado, e a marca oculta segue intacta.
        $this->patchJson($this->url(null, '/'.$own->id.'/principal'))
            ->assertUnprocessable()->assertJsonValidationErrors('is_primary');
        $this->assertTrue($hidden->fresh()->is_primary);
        $this->assertFalse($own->fresh()->is_primary);
        $this->assertSame(1, EmployeeAssignment::where('is_primary', true)->count());

        // Criar já como principal também é recusado.
        $this->postJson($this->url(), $this->body($this->guanambi, '2026-09-01', '2026-10-31', ['is_primary' => true]))
            ->assertUnprocessable()->assertJsonValidationErrors('is_primary');

        // O gerente da empresa transfere: é a autoridade que alcança as duas unidades.
        $role = $this->role($this->gartan, 'Gestor da Roupas', [
            'account.employees.list', 'account.employees.view', 'account.employees.assign',
        ]);
        $company = $this->operator($this->gartan, [[$role, AccessNode::company($this->gartan->id, $this->roupas->id)]]);
        $this->actingAs($company)->patchJson($this->url(null, '/'.$own->id.'/principal'))->assertNoContent();
        $this->assertFalse($hidden->fresh()->is_primary);
        $this->assertTrue($own->fresh()->is_primary);
        $this->assertSame(1, EmployeeAssignment::where('is_primary', true)->count());

        // Com a principal na unidade dele e outra lotação só na unidade oculta, encerrar é recusado.
        $this->actingAs($manager)->patchJson($this->url(null, '/'.$own->id.'/encerrar'), ['ends_on' => '2026-06-15'])
            ->assertUnprocessable()->assertJsonValidationErrors('new_primary_id');
        $this->assertSame('2026-06-30', substr($own->fresh()->ends_on, 0, 10));
        $this->assertNull($hidden->fresh()->ends_on);
        $this->assertTrue($own->fresh()->is_primary);
        $this->assertSame(0, DB::table('employee_assignments')->where('is_primary', true)
            ->whereNull('ends_on')->where('id', '!=', $own->id)->count());
    }

    public function test_a_unit_manager_can_end_the_primary_when_the_heir_is_inside_the_reach(): void
    {
        $this->assign($this->caetite, '2026-01-01');
        $a = $this->assign($this->guanambi, '2026-01-01', null, ['is_primary' => true]);
        $b = $this->assign($this->sede, '2026-01-01');

        $manager = $this->managerOf($this->guanambi, ['account.employees.list', 'account.employees.view', 'account.employees.assign']);
        $role = $this->role($this->gartan, 'Gestor duas unidades', [
            'account.employees.list', 'account.employees.view', 'account.employees.assign',
        ]);
        $two = $this->operator($this->gartan, [
            [$role, AccessNode::establishment($this->gartan->id, $this->roupas->id, $this->guanambi->id)],
            [$role, AccessNode::establishment($this->gartan->id, $this->roupas->id, $this->sede->id)],
        ]);

        $hiddenAssignment = EmployeeAssignment::where('establishment_id', $this->caetite->id)->sole();

        // Escolher a lotação oculta como herdeira é recusado, e nada muda.
        $this->actingAs($manager)->patchJson($this->url(null, '/'.$a->id.'/encerrar'), [
            'ends_on' => '2026-12-31', 'new_primary_id' => $hiddenAssignment->id,
        ])->assertUnprocessable();
        $this->assertNull($a->fresh()->ends_on);
        $this->assertTrue($a->fresh()->is_primary);

        // Com a herdeira dentro do alcance, a transferência acontece e a unidade oculta não muda.
        $this->actingAs($two)->patchJson($this->url(null, '/'.$a->id.'/encerrar'), [
            'ends_on' => '2026-12-31', 'new_primary_id' => $b->id,
        ])->assertNoContent();
        $this->assertTrue($b->fresh()->is_primary);
        $this->assertFalse($a->fresh()->is_primary);
        $this->assertFalse($hiddenAssignment->fresh()->is_primary);
        $this->assertNull($hiddenAssignment->fresh()->ends_on);
    }

    public function test_institutional_check_reports_incoherent_contact_modes(): void
    {
        $a = $this->assign($this->guanambi, '2026-01-01');
        $this->assertSame(0, Artisan::call('institutional:check'));

        DB::table('employee_assignments')->where('id', $a->id)->update(['work_contacts' => json_encode(['email' => 'x@x.test'])]);

        $this->assertSame(1, Artisan::call('institutional:check'));
        $this->assertStringContainsString('modo de contato incoerente', Artisan::output());
    }
}
