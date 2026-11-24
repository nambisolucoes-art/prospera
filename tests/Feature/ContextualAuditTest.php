<?php

namespace Tests\Feature;

use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Auditoria\Domain\Exceptions\AuditWriteFailed;
use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Domains\Clientes\Domain\Models\Customer;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Organizacao\Domain\Models\Establishment;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Concerns\BuildsCommercialScenario;
use Tests\TestCase;

/**
 * E08: auditoria contextual completa (registro, autoria, inspeção, autorização, privacidade e histórico).
 */
final class ContextualAuditTest extends TestCase
{
    use BuildsCommercialScenario;
    use RefreshDatabase;

    private Client $gartan;

    private Company $roupas;

    private Company $express;

    private Establishment $janauba;

    private User $owner;

    private Role $auditor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gartan = $this->account();
        $this->roupas = $this->company($this->gartan, 'Gartan Roupas', '11222333');
        $this->express = $this->company($this->gartan, 'Gartan Express', '44555666');
        $this->janauba = $this->unit($this->roupas, 'Janaúba');
        $this->owner = $this->administrator($this->gartan);
        $this->auditor = $this->role($this->gartan, 'Auditoria', [
            'account.audit.list',
            'account.audit.view',
            'account.audit.technical',
        ]);
    }

    private function companyNode(Company $company): AccessNode
    {
        return AccessNode::company($this->gartan->id, $company->id);
    }

    private function unitNode(Establishment $unit): AccessNode
    {
        return AccessNode::establishment($this->gartan->id, $unit->company_id, $unit->id);
    }

    private function customersUrl(?Person $person = null): string
    {
        $base = '/conta/empresa/'.$this->roupas->code.'/clientes';

        if ($person === null) {
            return $base;
        }

        $relation = Customer::where('account_id', $person->account_id)
            ->where('company_id', $this->roupas->id)
            ->where('person_id', $person->id)
            ->sole();

        return $base.'/'.$relation->id;
    }

    /** Operações reais em cada nível: conta, pessoa compartilhada, empresa, unidade, relação e acesso. */
    private function produceEvents(): Person
    {
        $this->actingAs($this->owner);

        $this->postJson($this->customersUrl(), ['name' => 'Ana Compartilhada'])->assertNoContent();
        $ana = Person::where('name', 'Ana Compartilhada')->sole();
        $this->postJson('/conta/empresa/'.$this->express->code.'/clientes', ['person_id' => $ana->id])
            ->assertNoContent();

        $this->patchJson('/conta/empresas/'.$this->roupas->id, ['name' => 'Gartan Roupas Ltda'])->assertOk();
        $this->patchJson('/conta/estabelecimentos/'.$this->janauba->id, ['name' => 'Janaúba Centro'])->assertOk();
        $this->postJson('/conta/empresa/'.$this->express->code.'/fornecedores', ['person_id' => $ana->id])
            ->assertSuccessful();
        $this->postJson('/conta/etiquetas', ['name' => 'VIP', 'color' => '#2b7de9', 'module' => 'customers'])->assertOk();

        return $ana;
    }

    public function test_events_record_the_node_of_the_affected_resource(): void
    {
        $ana = $this->produceEvents();

        $customer = AuditRecord::where('entity_type', 'customer')
            ->where('company_id', $this->roupas->id)
            ->sole();
        $supplier = AuditRecord::where('entity_type', 'supplier')->sole();
        $company = AuditRecord::where('entity_type', 'company')
            ->where('entity_id', $this->roupas->id)
            ->where('event', 'updated')
            ->sole();
        $unit = AuditRecord::where('entity_type', 'establishment')
            ->where('entity_id', $this->janauba->id)
            ->where('event', 'updated')
            ->sole();
        $person = AuditRecord::where('entity_type', 'person')
            ->where('entity_id', $ana->id)
            ->sole();
        $tag = AuditRecord::where('entity_type', 'tag')
            ->where('entity_name', 'VIP')
            ->sole();

        // Relação comercial: nó da empresa, sem unidade inventada.
        $this->assertSame([$this->gartan->id, $this->roupas->id, null], [
            $customer->account_id,
            $customer->company_id,
            $customer->establishment_id,
        ]);
        $this->assertSame([$this->express->id, null], [$supplier->company_id, $supplier->establishment_id]);
        $this->assertSame([$this->roupas->id, null], [$company->company_id, $company->establishment_id]);
        $this->assertSame([$this->roupas->id, $this->janauba->id], [$unit->company_id, $unit->establishment_id]);

        // Cadastro compartilhado e etiquetas: um único evento da conta, sem empresa.
        $this->assertSame([$this->gartan->id, null, null], [
            $person->account_id,
            $person->company_id,
            $person->establishment_id,
        ]);
        $this->assertSame([null, null], [$tag->company_id, $tag->establishment_id]);

        foreach ([$customer, $supplier, $company, $unit, $person, $tag] as $event) {
            $this->assertSame($this->owner->id, $event->actor_id);
            $this->assertSame('account', $event->context);
            $this->assertNull($event->represented_user_id);
            $this->assertNull($event->inspection_id);
            $this->assertNotNull($event->membership_id);
        }

        // Inativação e reativação são alterações da relação, no nó da empresa.
        $relation = '/conta/empresa/'.$this->roupas->code.'/clientes/'.$customer->entity_id;
        $this->patchJson($relation.'/inativar')->assertSuccessful();
        $this->patchJson($relation.'/ativar')->assertSuccessful();

        $changes = AuditRecord::where('entity_type', 'customer')
            ->where('entity_id', $customer->entity_id)
            ->where('event', 'updated')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $changes);
        $this->assertSame([$this->roupas->id], $changes->pluck('company_id')->unique()->values()->all());
        $this->assertNotSame($changes[0]->operation_id, $changes[1]->operation_id);

        // Cargo da conta, concessão e revogação no nó da atribuição.
        $role = $this->role($this->gartan, 'Conferente', ['account.people.view']);
        $this->patchJson('/conta/cargos/'.$role->id, ['name' => 'Conferente geral'])->assertOk();
        $roleEvent = AuditRecord::where('entity_type', 'role')
            ->where('entity_id', $role->id)
            ->where('event', 'updated')
            ->sole();
        $this->assertSame([null, null], [$roleEvent->company_id, $roleEvent->establishment_id]);

        $target = $this->operator($this->gartan, []);
        $membership = AccountMembership::where('user_id', $target->id)->sole();
        $url = '/conta/usuarios/'.$membership->id.'/atribuicoes';
        $this->patchJson($url, [
            'grants' => [[
                'role_id' => $role->id,
                'scope' => 'establishment',
                'company_id' => $this->roupas->id,
                'establishment_id' => $this->janauba->id,
            ]],
        ])->assertOk();
        $assignment = $membership->assignments()->sole();
        $this->patchJson($url, ['revocations' => [$assignment->id]])->assertOk();

        $grants = AuditRecord::where('entity_type', 'role-assignment')
            ->where('entity_id', $assignment->id)
            ->orderBy('id')
            ->get();

        $this->assertSame(['created', 'deleted'], $grants->pluck('event')->all());

        foreach ($grants as $grant) {
            $this->assertSame([$this->roupas->id, $this->janauba->id], [$grant->company_id, $grant->establishment_id]);
        }
    }

    public function test_shared_person_change_is_one_tenant_event_visible_only_with_tenant_audit(): void
    {
        $ana = $this->produceEvents();
        $before = AuditRecord::count();

        $this->patchJson($this->customersUrl($ana), ['name' => 'Ana Compartilhada', 'phone' => '77998521027'])
            ->assertNoContent();

        $events = AuditRecord::where('id', '>', AuditRecord::orderBy('id')->skip($before - 1)->value('id'))->get();
        $this->assertCount(1, $events, 'A alteração central não é duplicada por empresa relacionada.');
        $this->assertSame(['person', null], [$events[0]->entity_type, $events[0]->company_id]);

        // Acesso à relação comercial não concede o histórico do cadastro compartilhado.
        $companyAuditor = $this->operator($this->gartan, [[$this->auditor, $this->companyNode($this->roupas)]]);
        $this->actingAs($companyAuditor)
            ->getJson('/conta/auditoria/'.$events[0]->id.'/visualizar')
            ->assertNotFound();
        $this->getJson('/conta/auditoria?entity=person')->assertOk()->assertJsonPath('records.total', 0);

        $this->actingAs($this->owner)
            ->getJson('/conta/auditoria/'.$events[0]->id.'/visualizar')
            ->assertOk()
            ->assertJsonPath('level', 'tenant')
            ->assertJsonPath('company', null)
            ->assertJsonPath('changes.0.after', '(77) 99852-1027');
    }

    public function test_historical_identification_survives_later_changes(): void
    {
        $ana = $this->produceEvents();
        $created = AuditRecord::where('entity_type', 'person')->where('entity_id', $ana->id)->sole();
        $ownerName = $this->owner->name;

        // Autor renomeado, vínculo suspenso, pessoa renomeada, empresa renomeada e unidade inativada.
        $this->owner->forceFill(['name' => 'Nome novo do titular'])->saveOrFail();
        $this->patchJson($this->customersUrl($ana), ['name' => 'Ana Renomeada'])->assertNoContent();
        $this->patchJson('/conta/empresas/'.$this->roupas->id, ['name' => 'Roupas Renomeada'])->assertOk();
        $this->patchJson('/conta/estabelecimentos/'.$this->janauba->id, ['active' => '0'])->assertOk();

        $created->refresh();
        $this->assertSame($ownerName, $created->actor_name);
        $this->assertSame('Ana Compartilhada', $created->entity_name);

        $unitEvent = AuditRecord::where('entity_type', 'establishment')
            ->where('entity_id', $this->janauba->id)
            ->orderBy('id')
            ->firstOrFail();

        // O nó organizacional mostra o nome atual; a unidade inativa continua no histórico.
        $this->getJson('/conta/auditoria/'.$unitEvent->id.'/visualizar')
            ->assertOk()
            ->assertJsonPath('company', 'Roupas Renomeada')
            ->assertJsonPath('establishment', 'Janaúba Centro');
        $this->getJson('/conta/auditoria?establishment='.$this->janauba->id)
            ->assertOk()
            ->assertJsonPath('records.total', 3);

        // Removida a identidade do autor, o histórico mantém o nome gravado na operação.
        $removable = $this->operator($this->gartan, [[$this->role($this->gartan, 'Etiquetas', [
            'account.tags.create',
        ]), AccessNode::tenant($this->gartan->id)]]);
        $this->actingAs($removable)->postJson('/conta/etiquetas', ['name' => 'Temporária', 'color' => '#2b7de9', 'module' => 'customers'])
            ->assertOk();
        $name = $removable->name;
        AccountMembership::where('user_id', $removable->id)->update(['status' => 'suspended']);
        $tagEvent = AuditRecord::where('entity_name', 'Temporária')->sole();
        $this->assertSame($name, $tagEvent->actor_name);
    }

    public function test_incoherent_organizational_context_is_refused(): void
    {
        $other = $this->account('Outra conta');
        $foreign = $this->company($other, 'Empresa alheia', '77888999');
        $this->actingAs($this->owner);

        $attempts = [
            'unidade sem empresa' => fn () => AuditLog::record(
                'establishment', 1, $this->gartan->id, 'updated', 'X', [], null, $this->janauba->id,
            ),
            'empresa sem conta' => fn () => AuditLog::record(
                'company', 1, null, 'updated', 'X', [], $this->roupas->id,
            ),
        ];

        foreach ($attempts as $case => $attempt) {
            try {
                $attempt();
                $this->fail('Deveria recusar: '.$case);
            } catch (\LogicException) {
                $this->addToAssertionCount(1);
            }
        }

        $change = ['name' => ['label' => 'Nome', 'before' => 'A', 'after' => 'B']];
        $foreignAttempts = [
            'empresa de outra conta' => fn () => AuditLog::record(
                'company', 1, $this->gartan->id, 'updated', 'X', $change, $foreign->id,
            ),
            'unidade de outra empresa' => fn () => AuditLog::record(
                'establishment', 1, $this->gartan->id, 'updated', 'X', $change, $this->express->id, $this->janauba->id,
            ),
        ];

        foreach ($foreignAttempts as $case => $attempt) {
            try {
                AuditLog::transaction($attempt);
                $this->fail('Deveria recusar: '.$case);
            } catch (AuditWriteFailed $exception) {
                // A falha não leva ao log o SQL nem os valores alterados.
                $this->assertStringNotContainsString('insert into', $exception->getMessage());
                $this->assertNull($exception->getPrevious());
            }
        }

        $this->assertSame(0, AuditRecord::where('entity_name', 'X')->count());
    }

    public function test_no_change_rollback_and_audit_failure_never_produce_misleading_history(): void
    {
        $ana = $this->produceEvents();
        $before = AuditRecord::count();

        // Atualização sem mudança efetiva e requisição recusada.
        $this->patchJson($this->customersUrl($ana), ['name' => 'Ana Compartilhada'])->assertNoContent();
        $this->postJson($this->customersUrl(), ['name' => ''])->assertUnprocessable();
        $this->assertSame($before, AuditRecord::count());

        // Falha na operação depois da escrita: nada do histórico permanece.
        try {
            AuditLog::transaction(function () use ($ana) {
                $ana->forceFill(['name' => 'Nome que não vale'])->saveOrFail();

                throw new \RuntimeException('falha posterior');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame('Ana Compartilhada', $ana->fresh()->name);
        $this->assertSame($before, AuditRecord::count());

        // Falha na gravação obrigatória da auditoria: a mudança de negócio também é desfeita.
        DB::statement(
            "create trigger e08_audit_failure before insert on audit_records begin select raise(abort, 'falha'); end",
        );
        Log::spy();
        $this->postJson($this->customersUrl(), ['name' => 'Sem histórico', 'email' => 'privado@example.test'])
            ->assertServerError()
            ->assertDontSee('privado@example.test');
        DB::statement('drop trigger e08_audit_failure');

        Log::shouldHaveReceived('error')->withArgs(function (string $message, array $context = []): bool {
            $logged = $message.json_encode(array_map(
                fn ($value) => $value instanceof \Throwable ? $value->getMessage() : $value,
                $context,
            ));

            return str_contains($logged, 'Falha ao gravar o histórico de auditoria')
                && ! str_contains($logged, 'privado@example.test')
                && ! str_contains($logged, 'Sem histórico');
        });

        $this->assertFalse(Person::where('name', 'Sem histórico')->exists());
        $this->assertSame($before, AuditRecord::count());
    }

    public function test_history_is_read_only(): void
    {
        $this->produceEvents();
        $record = AuditRecord::firstOrFail();

        foreach (['update', 'delete'] as $operation) {
            try {
                $operation === 'update'
                    ? $record->forceFill(['actor_name' => 'Adulterado'])->save()
                    : $record->delete();
                $this->fail('O histórico não pode ser alterado: '.$operation);
            } catch (\LogicException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertNotSame('Adulterado', $record->fresh()->actor_name);

        $writes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'auditoria'))
            ->flatMap(fn ($route) => $route->methods())
            ->diff(['GET', 'HEAD'])
            ->values()
            ->all();

        $this->assertSame([], $writes);
    }

    public function test_failed_inspection_audit_preserves_the_administrator_session(): void
    {
        $platform = User::factory()->create(['is_saas_admin' => true]);
        DB::statement(
            "create trigger e08_audit_failure before insert on audit_records begin select raise(abort, 'falha'); end",
        );

        $this->actingAs($platform)
            ->postJson('/saas/contas/'.$this->gartan->id.'/inspecao', [
                'user_id' => $this->owner->id,
                'mode' => 'edit',
            ])
            ->assertServerError()
            ->assertSessionMissing('account_inspection');

        $this->assertAuthenticatedAs($platform);
        $this->assertDatabaseMissing('audit_records', ['event' => 'inspection-started']);
    }

    public function test_logout_and_disabled_identity_record_inspection_endings(): void
    {
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $url = '/saas/contas/'.$this->gartan->id.'/inspecao';

        $this->actingAs($platform)->postJson($url, ['user_id' => $this->owner->id, 'mode' => 'read-only'])->assertOk();
        $this->postJson('/logout')->assertSuccessful();
        $this->assertGuest();

        $this->actingAs($platform)->postJson($url, ['user_id' => $this->owner->id, 'mode' => 'read-only'])->assertOk();
        auth()->user()->forceFill(['access_enabled' => false])->saveOrFail();
        $this->getJson('/conta/auditoria')->assertUnauthorized();
        $this->assertGuest();

        $ends = AuditRecord::where('event', 'inspection-ended')->orderBy('id')->get();
        $starts = AuditRecord::where('event', 'inspection-started')->orderBy('id')->get();
        $this->assertCount(2, $ends);
        $this->assertSame($starts->pluck('inspection_id')->all(), $ends->pluck('inspection_id')->all());
        $this->assertSame('Saída da sessão', $ends[0]->changes['ending']['after']);
        $this->assertStringStartsWith('Automático', $ends[1]->changes['ending']['after']);
        $this->assertSame([$platform->id, $platform->id], $ends->pluck('actor_id')->all());
    }

    public function test_events_for_distinct_nodes_are_not_merged(): void
    {
        AuditLog::transaction(function (): void {
            foreach ([$this->roupas, $this->express] as $company) {
                AuditLog::record(
                    'test-context',
                    1,
                    $this->gartan->id,
                    'updated',
                    'Recurso em dois nós',
                    ['value' => ['label' => 'Valor', 'before' => false, 'after' => true]],
                    $company->id,
                );
            }
        });

        $events = AuditRecord::where('entity_type', 'test-context')->orderBy('id')->get();
        $this->assertSame([$this->roupas->id, $this->express->id], $events->pluck('company_id')->all());
        $this->assertCount(1, $events->pluck('operation_id')->unique());
    }

    public function test_inspection_identifies_real_author_represented_user_mode_and_endings(): void
    {
        $platform = User::factory()->create(['is_saas_admin' => true, 'name' => 'Inspetora da plataforma']);
        $ownerName = $this->owner->name;

        // Somente leitura: escrita bloqueada e nenhum evento além do início.
        $this->actingAs($platform)
            ->postJson('/saas/contas/'.$this->gartan->id.'/inspecao', [
                'user_id' => $this->owner->id,
                'mode' => 'read-only',
            ])
            ->assertOk();
        $this->postJson($this->customersUrl(), ['name' => 'Bloqueada'])->assertForbidden();
        $this->assertFalse(AuditRecord::where('entity_name', 'Bloqueada')->exists());
        $this->postJson('/inspecao/encerrar')->assertOk();

        // Leitura e edição: a escrita é do administrador, representando o titular.
        $this->actingAs($platform)
            ->postJson('/saas/contas/'.$this->gartan->id.'/inspecao', ['user_id' => $this->owner->id, 'mode' => 'edit'])
            ->assertOk();
        $this->postJson($this->customersUrl(), ['name' => 'Criada na inspeção'])->assertNoContent();

        $started = AuditRecord::where('event', 'inspection-started')->latest('id')->firstOrFail();
        $write = AuditRecord::where('entity_name', 'Criada na inspeção')->sole();

        $this->assertSame($platform->id, $write->actor_id);
        $this->assertSame('Inspetora da plataforma', $write->actor_name);
        $this->assertSame($this->owner->id, $write->represented_user_id);
        $this->assertSame($ownerName, $write->represented_user_name);
        $this->assertSame('platform-account', $write->context);
        $this->assertNotNull($write->inspection_id);
        $this->assertSame($started->inspection_id, $write->inspection_id);

        $this->postJson('/inspecao/encerrar')->assertOk();
        $ended = AuditRecord::where('event', 'inspection-ended')->latest('id')->firstOrFail();
        $this->assertSame($started->inspection_id, $ended->inspection_id);
        $this->assertSame('Solicitado pelo administrador', $ended->changes['ending']['after']);

        // A ficha mostra autor real, representado e modo, sem expor a identidade atual do representado.
        $this->owner->forceFill(['name' => 'Titular renomeado'])->saveOrFail();
        $this->actingAs($this->owner)
            ->getJson('/conta/auditoria/'.$write->id.'/visualizar')
            ->assertOk()
            ->assertJsonPath('actor', 'Inspetora da plataforma')
            ->assertJsonPath('represented', $ownerName)
            ->assertJsonPath('origin', 'Inspeção da plataforma')
            ->assertJsonPath('inspection_mode', 'Leitura e edição');

        // Revogação durante a inspeção: a próxima requisição encerra e registra o encerramento automático.
        $this->actingAs($platform)
            ->postJson('/saas/contas/'.$this->gartan->id.'/inspecao', ['user_id' => $this->owner->id, 'mode' => 'edit'])
            ->assertOk();
        $platform->forceFill(['is_saas_admin' => false])->saveOrFail();
        $this->postJson($this->customersUrl(), ['name' => 'Após revogação'])->assertUnauthorized();

        $automatic = AuditRecord::where('event', 'inspection-ended')->latest('id')->firstOrFail();
        $this->assertFalse(AuditRecord::where('entity_name', 'Após revogação')->exists());
        $this->assertSame($platform->id, $automatic->actor_id);
        $this->assertSame($this->owner->id, $automatic->represented_user_id);
        $this->assertStringStartsWith('Automático', $automatic->changes['ending']['after']);

        // A requisição normal seguinte não herda autor, representado nem inspeção.
        $this->actingAs($this->owner)->postJson($this->customersUrl(), ['name' => 'Depois da inspeção'])->assertNoContent();
        $normal = AuditRecord::where('entity_name', 'Depois da inspeção')->sole();
        $this->assertSame([$this->owner->id, null, null, null, 'account'], [
            $normal->actor_id,
            $normal->represented_user_id,
            $normal->represented_user_name,
            $normal->inspection_id,
            $normal->context,
        ]);
    }

    public function test_visibility_follows_contextual_rbac_in_list_count_options_search_and_detail(): void
    {
        $this->produceEvents();

        $profiles = [
            'integral' => $this->owner,
            'empresa' => $this->operator($this->gartan, [[$this->auditor, $this->companyNode($this->roupas)]]),
            'unidade' => $this->operator($this->gartan, [[$this->auditor, $this->unitNode($this->janauba)]]),
            'multiplas' => $this->operator($this->gartan, [
                [$this->auditor, $this->companyNode($this->express)],
                [$this->auditor, $this->unitNode($this->janauba)],
            ]),
        ];

        $other = $this->account('Outra conta');
        $otherOwner = $this->administrator($other);
        $this->actingAs($otherOwner)
            ->postJson('/conta/empresa/'.$this->company($other, 'Comércio', '11222333')->code.'/clientes', [
                'name' => 'Pessoa de outra conta',
            ])->assertNoContent();
        $foreign = AuditRecord::where('entity_type', 'person')->where('entity_name', 'Pessoa de outra conta')->sole();

        $account = AuditRecord::where('account_id', $this->gartan->id);
        $expected = [
            'integral' => (clone $account)->pluck('id')->all(),
            'empresa' => (clone $account)->where('company_id', $this->roupas->id)->pluck('id')->all(),
            'unidade' => (clone $account)->where('establishment_id', $this->janauba->id)->pluck('id')->all(),
            'multiplas' => (clone $account)
                ->where(fn ($nodes) => $nodes->where('company_id', $this->express->id)
                    ->orWhere('establishment_id', $this->janauba->id))
                ->pluck('id')
                ->all(),
        ];

        $tenantOnly = (clone $account)->whereNull('company_id')->firstOrFail();

        foreach ($profiles as $label => $user) {
            $this->actingAs($user);
            $page = $this->getJson('/conta/auditoria?per_page=100')->assertOk();
            $ids = array_column($page->json('records.data'), 'id');
            sort($ids);
            $wanted = $expected[$label];
            sort($wanted);

            $this->assertNotSame([], $wanted, $label);
            $this->assertSame($wanted, $ids, $label);
            $this->getJson('/conta/auditoria?per_page=10')->assertJsonPath('records.total', count($wanted));

            // Paginação: percorrer as páginas devolve exatamente o conjunto autorizado.
            $paged = [];

            for ($number = 1; $number <= (int) ceil(count($wanted) / 10); $number++) {
                $data = $this->getJson('/conta/auditoria?per_page=10&page='.$number)->json('records.data');
                $paged = [...$paged, ...array_column($data, 'id')];
            }

            sort($paged);
            $this->assertSame($wanted, $paged, $label);

            // Opções de filtro: só autores, tipos e nós alcançados.
            $options = $page->json('options');
            $this->assertNotContains($otherOwner->name, array_column($options['actors'], 'label'));
            $visibleTypes = AuditRecord::whereIn('id', $wanted)->distinct()->pluck('entity_type')->all();
            $optionTypes = array_column($options['entities'], 'value');
            sort($visibleTypes);
            sort($optionTypes);
            $this->assertSame($visibleTypes, $optionTypes, $label);
            $visibleActors = AuditRecord::whereIn('id', $wanted)
                ->whereNotNull('actor_id')
                ->distinct()
                ->pluck('actor_id')
                ->map('strval')
                ->all();
            $this->assertEqualsCanonicalizing($visibleActors, array_column($options['actors'], 'value'), $label);
            $visibleEvents = AuditRecord::whereIn('id', $wanted)->distinct()->pluck('event')->all();
            $this->assertEqualsCanonicalizing($visibleEvents, array_column($options['events'], 'value'), $label);

            // Busca, detalhe e consulta direta por ID de outra conta.
            $this->getJson('/conta/auditoria?search=Pessoa%20de%20outra')->assertJsonPath('records.total', 0);
            $this->getJson('/conta/auditoria/'.$foreign->id.'/visualizar')->assertNotFound();
            $this->getJson('/conta/auditoria/'.$tenantOnly->id.'/visualizar')
                ->assertStatus($label === 'integral' ? 200 : 404);
        }

        // Empresa e unidade oferecidas como filtro correspondem à autorização.
        $this->actingAs($profiles['empresa']);
        $options = $this->getJson('/conta/auditoria')->json('options');
        $this->assertSame([(string) $this->roupas->id], array_column($options['companies'], 'value'));
        $this->assertEqualsCanonicalizing(
            Establishment::where('company_id', $this->roupas->id)->pluck('id')->map('strval')->all(),
            array_column($options['establishments'], 'value'),
        );
        $this->assertSame(['company', 'establishment'], array_column($options['levels'], 'value'));
        $this->getJson('/conta/auditoria?company='.$this->express->id)->assertJsonPath('records.total', 0);

        $this->actingAs($profiles['unidade']);
        $options = $this->getJson('/conta/auditoria')->json('options');
        $this->assertSame([(string) $this->janauba->id], array_column($options['establishments'], 'value'));
        $this->assertSame(['establishment'], array_column($options['levels'], 'value'));

        // Sem permissão de auditoria, nada é consultado, nem detalhe nem opções.
        $reader = $this->operator($this->gartan, [[
            $this->role($this->gartan, 'Leitura', ['account.people.view']),
            AccessNode::tenant($this->gartan->id),
        ]]);
        $this->actingAs($reader);
        $this->getJson('/conta/auditoria')->assertForbidden()->assertJsonMissingPath('options');
        $this->getJson('/conta/auditoria/'.$tenantOnly->id.'/visualizar')->assertForbidden();

        // Vínculo suspenso e revogação valem na requisição seguinte.
        $unitAuditor = $profiles['unidade'];
        $this->actingAs($unitAuditor)->getJson('/conta/auditoria')->assertOk();
        $membership = AccountMembership::where('user_id', $unitAuditor->id)->sole();
        $membership->assignments()->sole()->delete();
        $this->getJson('/conta/auditoria')->assertForbidden();

        $companyAuditor = $profiles['empresa'];
        $this->actingAs($companyAuditor)->getJson('/conta/auditoria')->assertOk();
        AccountMembership::where('user_id', $companyAuditor->id)->update(['status' => 'suspended']);
        $this->getJson('/conta/auditoria')->assertForbidden();
    }

    public function test_filters_restrict_inside_the_authorized_set_and_ignore_the_workspace(): void
    {
        $this->produceEvents();
        $this->actingAs($this->owner);
        $all = AuditRecord::where('account_id', $this->gartan->id);

        $cases = [
            'company='.$this->roupas->id => (clone $all)->where('company_id', $this->roupas->id)->count(),
            'establishment='.$this->janauba->id => (clone $all)->where('establishment_id', $this->janauba->id)->count(),
            'level=tenant' => (clone $all)->whereNull('company_id')->count(),
            'level=company' => (clone $all)->whereNotNull('company_id')->whereNull('establishment_id')->count(),
            'level=establishment' => (clone $all)->whereNotNull('establishment_id')->count(),
            'origin=account' => (clone $all)->where('context', 'account')->count(),
            'from='.now()->toDateString().'&until='.now()->toDateString() => (clone $all)->count(),
            'until='.now()->subDay()->toDateString() => 0,
        ];

        foreach ($cases as $query => $total) {
            $this->getJson('/conta/auditoria?per_page=10&'.$query)->assertOk()->assertJsonPath('records.total', $total);
        }

        $this->getJson('/conta/auditoria?level=platform')->assertUnprocessable();
        $this->getJson('/conta/auditoria?origin=qualquer')->assertUnprocessable();

        // A unidade padrão da área de trabalho não restringe nem amplia a auditoria.
        $total = $this->getJson('/conta/auditoria')->json('records.total');
        $this->putJson('/conta/area-de-trabalho/padrao', ['unidades' => [$this->janauba->code]])->assertNoContent();
        $this->getJson('/conta/auditoria')->assertJsonPath('records.total', $total);

        // Lista com nomes organizacionais sem consultas por linha.
        DB::enableQueryLog();
        $this->getJson('/conta/auditoria?per_page=2')->assertOk();
        $small = count(DB::getQueryLog());
        DB::flushQueryLog();
        $this->getJson('/conta/auditoria?per_page=50')
            ->assertOk()
            ->assertJsonFragment(['company' => 'Gartan Roupas Ltda', 'establishment' => 'Janaúba Centro']);
        $large = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($small, $large);
    }

    public function test_platform_audit_shows_only_platform_and_administrative_account_events(): void
    {
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $this->produceEvents();
        $business = AuditRecord::where('entity_type', 'person')->firstOrFail();

        $this->actingAs($platform)
            ->postJson('/saas/contas/'.$this->gartan->id.'/inspecao', ['user_id' => $this->owner->id, 'mode' => 'edit'])
            ->assertOk();
        $this->postJson($this->customersUrl(), ['name' => 'Escrita em inspeção'])->assertNoContent();
        $this->postJson('/inspecao/encerrar')->assertOk();
        $inspectionWrite = AuditRecord::where('entity_name', 'Escrita em inspeção')->sole();

        $this->actingAs($platform->fresh());
        $page = $this->getJson('/saas/auditoria?per_page=100')->assertOk();
        $ids = array_column($page->json('records.data'), 'id');
        $types = array_unique(array_column($page->json('records.data'), 'event_key'));

        $this->assertNotContains($business->id, $ids);
        $this->assertNotContains($inspectionWrite->id, $ids);
        $this->assertContains('inspection-started', $types);
        $this->assertContains('inspection-ended', $types);
        $this->assertNotContains($this->owner->name, array_column($page->json('options.actors'), 'label'));
        $this->getJson('/saas/auditoria/'.$business->id.'/visualizar')->assertNotFound();
        $this->getJson('/saas/auditoria?company='.$this->roupas->id)->assertUnprocessable();

        $expected = AuditRecord::where(fn ($events) => $events->whereNull('account_id')
            ->orWhere('entity_type', 'account')
            ->orWhere('context', 'platform'))
            ->count();
        $this->assertSame($expected, $page->json('records.total'));
    }

    public function test_provisioning_access_and_availability_events_follow_the_contract(): void
    {
        $platform = User::factory()->create(['is_saas_admin' => true]);

        // Provisionamento pela plataforma: conta, empresa e unidade com origem plataforma, visíveis na plataforma.
        $this->actingAs($platform)->postJson('/saas/contas', [
            'name' => 'Contratante E08',
            'person_type' => 'company',
            'document' => '99.888.777/0001-00',
            'details' => ['phone' => '(77) 3451-0000'],
            'organization_setup' => 'manual',
            'administrator' => [
                'name' => 'Administrador E08',
                'email' => 'admin@e08.test',
                'mode' => 'manual',
                'password' => 'SenhaForte123!',
                'password_confirmation' => 'SenhaForte123!',
            ],
            'first_company' => [
                'name' => 'Empresa E08',
                'person_type' => 'company',
                'document' => '11.222.333/0001-81',
                'primary' => ['name' => 'Unidade E08', 'classification' => 'branch'],
            ],
        ])->assertOk();

        $account = Client::where('name', 'Contratante E08')->sole();
        $created = AuditRecord::where('entity_type', 'account')->where('entity_id', $account->id)->sole();
        $this->assertSame(['created', 'platform', $platform->id], [$created->event, $created->context, $created->actor_id]);
        $this->assertSame('Documento informado', $created->changes['document']['after']);

        $provisioning = AuditRecord::where('operation_id', $created->operation_id)->pluck('id')->all();
        $platformIds = array_column($this->getJson('/saas/auditoria?per_page=100')->json('records.data'), 'id');
        $this->assertSame([], array_values(array_diff($provisioning, $platformIds)));

        // Entrada e saída: eventos de identidade da conta do vínculo, sem empresa nem unidade.
        auth()->logout();
        $this->postJson('/login', ['email' => 'admin@e08.test', 'password' => 'SenhaForte123!'])->assertSuccessful();
        $this->postJson('/logout')->assertSuccessful();

        $administrator = User::where('email', 'admin@e08.test')->sole();
        $access = AuditRecord::where('entity_type', 'user')
            ->where('entity_id', $administrator->id)
            ->whereIn('event', ['login', 'logout'])
            ->orderBy('id')
            ->get();
        $this->assertSame(['login', 'logout'], $access->pluck('event')->all());

        foreach ($access as $event) {
            $this->assertSame([$account->id, null, null, 'account'], [
                $event->account_id,
                $event->company_id,
                $event->establishment_id,
                $event->context,
            ]);
        }

        // Disponibilidade restrita: evento do nó restrito (E08-D4).
        $role = $this->role($this->gartan, 'Restrito', ['account.people.view']);
        $this->actingAs($this->owner)->patchJson('/conta/cargos/'.$role->id, [
            'name' => $role->name,
            'permission_ids' => $role->permissionIds(),
            'availability' => 'restricted',
            'available_companies' => [$this->express->id],
        ])->assertOk();

        $availability = AuditRecord::where('entity_type', 'role-availability')->latest('id')->firstOrFail();
        $this->assertSame([$this->gartan->id, $this->express->id, null], [
            $availability->account_id,
            $availability->company_id,
            $availability->establishment_id,
        ]);
    }

    public function test_restricted_availability_is_visible_only_at_the_affected_nodes(): void
    {
        $role = $this->role($this->gartan, 'Disponibilidade local', ['account.people.view']);
        $this->actingAs($this->owner)->patchJson('/conta/cargos/'.$role->id, [
            'name' => $role->name,
            'permission_ids' => $role->permissionIds(),
            'availability' => 'restricted',
            'available_companies' => [$this->express->id],
            'available_establishments' => [$this->janauba->id],
        ])->assertOk();

        $events = AuditRecord::where('entity_type', 'role-availability')->orderBy('id')->get();
        $this->assertCount(2, $events);
        $this->assertCount(1, $events->pluck('operation_id')->unique());

        $companyEvent = $events->sole(fn (AuditRecord $record) => $record->company_id === $this->express->id);
        $unitEvent = $events->sole(fn (AuditRecord $record) => $record->establishment_id === $this->janauba->id);
        $companyAuditor = $this->operator($this->gartan, [[$this->auditor, $this->companyNode($this->express)]]);
        $unitAuditor = $this->operator($this->gartan, [[$this->auditor, $this->unitNode($this->janauba)]]);

        $this->actingAs($companyAuditor)->getJson('/conta/auditoria?entity=role-availability')
            ->assertOk()->assertJsonPath('records.total', 1)->assertJsonPath('records.data.0.id', $companyEvent->id);
        $this->getJson('/conta/auditoria/'.$unitEvent->id.'/visualizar')->assertNotFound();

        $this->actingAs($unitAuditor)->getJson('/conta/auditoria?entity=role-availability')
            ->assertOk()->assertJsonPath('records.total', 1)->assertJsonPath('records.data.0.id', $unitEvent->id);
        $this->getJson('/conta/auditoria/'.$companyEvent->id.'/visualizar')->assertNotFound();

        $this->actingAs($this->owner)->patchJson('/conta/cargos/'.$role->id, [
            'name' => $role->name,
            'permission_ids' => $role->permissionIds(),
            'availability' => 'restricted',
            'available_establishments' => [$this->janauba->id],
        ])->assertOk();

        $removed = AuditRecord::where('entity_type', 'role-availability')->where('event', 'deleted')->sole();
        $this->assertSame($companyEvent->entity_id, $removed->entity_id);
        $this->assertSame($this->express->id, $removed->company_id);
        $this->assertNull($removed->establishment_id);
    }

    public function test_sensitive_values_stay_out_of_history_and_discovery_is_not_a_change(): void
    {
        $this->produceEvents();
        $before = AuditRecord::count();

        // Descoberta por documento é consulta: POST sem documento na URL e sem evento de alteração.
        $this->postJson('/conta/empresa/'.$this->roupas->code.'/clientes/pessoa', [
            'person_type' => 'individual',
            'documento' => '52998224725',
        ])->assertOk();
        $this->assertSame($before, AuditRecord::count());

        // Envio do link de redefinição: evento do vínculo, sem token nem e-mail.
        $member = $this->operator($this->gartan, []);
        $membership = AccountMembership::where('user_id', $member->id)->sole();
        $this->postJson('/conta/usuarios/'.$membership->id.'/recuperacao')->assertOk();
        $event = AuditRecord::where('event', 'password-reset-requested')->sole();
        $this->assertSame([], $event->changes);
        $this->assertSame([null, null], [$event->company_id, $event->establishment_id]);

        $token = DB::table('password_reset_tokens')->where('email', $member->email)->value('token');
        $history = AuditRecord::all()->toJson();
        $this->assertStringNotContainsString((string) $token, $history);
        $this->assertStringNotContainsString('52998224725', $history);
        $this->assertStringNotContainsString($member->password, $history);

        // Os dados técnicos trazem identificadores, nunca valores alterados ou segredos.
        $technical = $this->getJson('/conta/auditoria/'.$event->id.'/visualizar')->json('technical');
        $this->assertSame([
            'operation_id',
            'entity_type',
            'entity_id',
            'actor_id',
            'account_id',
            'context',
            'represented_user_id',
            'membership_id',
            'inspection_id',
            'company_id',
            'establishment_id',
        ], array_keys($technical));
    }

    public function test_legacy_events_without_context_are_shown_explicitly(): void
    {
        $this->actingAs($this->owner);

        // Evento anterior à E08: inspeção sem nome do representado e sem referência.
        DB::table('audit_records')->insert([
            'operation_id' => '00000000-0000-4000-8000-000000000001',
            'actor_id' => 999,
            'actor_name' => 'Administrador antigo',
            'account_id' => $this->gartan->id,
            'context' => 'platform-account',
            'represented_user_id' => $this->owner->id,
            'entity_type' => 'person',
            'entity_id' => 1,
            'entity_name' => 'Registro antigo',
            'event' => 'updated',
            'changes' => '{}',
            'created_at' => now()->subYear(),
        ]);
        $legacy = AuditRecord::where('entity_name', 'Registro antigo')->sole();

        $this->getJson('/conta/auditoria/'.$legacy->id.'/visualizar')
            ->assertOk()
            ->assertJsonPath('level', 'tenant')
            ->assertJsonPath('represented', null)
            ->assertJsonPath('represented_user_id', $this->owner->id)
            ->assertJsonPath('inspection_mode', null)
            ->assertJsonPath('company', null);
    }

    public function test_legacy_trails_are_consolidated_without_duplicates(): void
    {
        $removal = require database_path('migrations/2026_11_21_000006_remove_legacy_audit_tables.php');
        $removal->down();

        $migration = require database_path('migrations/2026_11_21_000003_consolidate_contextual_audit.php');
        $this->produceEvents();
        DB::table('permissions')->where('slug', 'account.people.view')->update(['name' => 'Nome anterior']);
        $this->artisan('permissions:sync')->assertSuccessful();
        $person = AuditRecord::where('entity_type', 'person')->firstOrFail();
        $sync = AuditRecord::where('event', 'catalog-synchronized')->firstOrFail();
        $operation = '00000000-0000-4000-8000-000000000002';

        // Uma linha de cada trilha tem evento central equivalente; a outra só existe na trilha legada.
        DB::table('directory_audit_events')->insert([
            [
                'operation_id' => $operation,
                'actor_id' => $this->owner->id,
                'account_id' => $this->gartan->id,
                'event' => 'person.created',
                'entity_type' => 'person',
                'entity_id' => $person->entity_id,
                'changes' => '{}',
                'created_at' => $person->created_at,
            ],
            [
                'operation_id' => $operation,
                'actor_id' => $this->owner->id,
                'account_id' => $this->gartan->id,
                'event' => 'person.updated',
                'entity_type' => 'person',
                'entity_id' => 987654,
                'changes' => '{"after":{"name":"Somente no legado","document":"52998224725"}}',
                'created_at' => now()->subMonth(),
            ],
        ]);
        DB::table('access_catalog_events')->insert([
            [
                'operation_id' => $operation,
                'actor_id' => null,
                'scope' => 'platform',
                'event' => 'permission-catalog.synchronized',
                'changes' => '{}',
                'created_at' => $sync->created_at,
            ],
            [
                'operation_id' => $operation,
                'actor_id' => null,
                'scope' => 'platform',
                'event' => 'permission-catalog.synchronized',
                'changes' => '{}',
                'created_at' => now()->subMonths(2),
            ],
        ]);

        $migration->down();
        $migration->up();

        $imported = AuditRecord::where('legacy_key', 'like', 'e08:%')->orderBy('id')->get();
        $this->assertCount(2, $imported);
        $this->assertSame(['person', 'permission-catalog'], $imported->pluck('entity_type')->all());
        $this->assertSame('Somente no legado', $imported[0]->changes['name']['after']);
        $this->assertArrayNotHasKey('document', $imported[0]->changes);

        // A sincronização do catálogo não grava mais na trilha legada.
        $legacy = DB::table('access_catalog_events')->count();
        $central = AuditRecord::where('event', 'catalog-synchronized')->count();
        DB::table('permissions')->where('slug', 'account.people.update')->update(['name' => 'Nome antigo']);
        $this->artisan('permissions:sync')->assertSuccessful();
        $this->assertSame($central + 1, AuditRecord::where('event', 'catalog-synchronized')->count());
        $this->assertSame($legacy, DB::table('access_catalog_events')->count());

        $removal->up();
        $this->assertFalse(Schema::hasTable('directory_audit_events'));
        $this->assertFalse(Schema::hasTable('access_catalog_events'));
        $this->assertSame($central + 1, AuditRecord::where('event', 'catalog-synchronized')->count());
    }
}
