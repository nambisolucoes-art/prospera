<?php

namespace Tests\Feature;

use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Ajuda\Application\Queries\HelpCatalog;
use App\Domains\Auditoria\Application\Actions\AuditLog;
use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Domains\Clientes\Domain\Models\Customer;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Concerns\BuildsCommercialScenario;
use Tests\TestCase;

class AuditAndGuidanceTest extends TestCase
{
    use BuildsCommercialScenario;
    use RefreshDatabase;

    private function owner(): User
    {
        $account = Client::create(['name' => 'Conta de auditoria', 'active' => true]);

        return User::factory()->asAccountAdministrator($account->id)->create();
    }

    private function customersPath(User $owner): string
    {
        $account = Client::findOrFail($owner->account_id);
        $company = Company::where('account_id', $account->id)->first()
            ?? $this->company($account, 'Comercial', '11222333');

        return '/conta/empresa/'.$company->code.'/clientes';
    }

    public function test_writes_record_actor_account_and_real_before_after_without_noops(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->postJson($this->customersPath($owner), ['name' => 'Pessoa original'])->assertNoContent();
        $person = Person::where('name', 'Pessoa original')->sole();
        $created = AuditRecord::where('entity_type', 'person')->where('entity_id', $person->id)->sole();
        $this->assertSame($owner->id, $created->actor_id);
        $this->assertSame($owner->account_id, $created->account_id);
        $this->assertSame('created', $created->event);
        $this->assertSame('Pessoa original', $created->changes['name']['after']);
        $this->patchJson($this->customersPath($owner).'/'.Customer::where('person_id', $person->id)->sole()->id, [
            'name' => 'Pessoa editada',
        ])->assertNoContent();
        $updated = AuditRecord::where('entity_type', 'person')
            ->where('entity_id', $person->id)
            ->where('event', 'updated')
            ->sole();
        $this->assertSame('Pessoa original', $updated->changes['name']['before']);
        $this->assertSame('Pessoa editada', $updated->changes['name']['after']);
        $count = AuditRecord::count();
        $this->patchJson($this->customersPath($owner).'/'.Customer::where('person_id', $person->id)->sole()->id, [
            'name' => 'Pessoa editada',
        ])->assertNoContent();
        $this->assertSame($count, AuditRecord::count());
        $this->artisan('audit:check')->assertSuccessful();
    }

    public function test_operation_groups_pivot_and_field_changes_and_rolls_back_as_one(): void
    {
        $owner = $this->owner();
        $this
            ->actingAs($owner)
            ->postJson(
                '/conta/cargos',
                [
                    'name' => 'Cargo auditado',
                    'permission_ids' => [Permission::where('slug', 'account.tags.list')->sole()->id],
                ],
            )
            ->assertOk();
        $role = Role::where('name', 'Cargo auditado')->sole();
        $record = AuditRecord::where('entity_type', 'role')->where('entity_id', $role->id)->sole();
        $this->assertSame('Cargo auditado', $record->changes['name']['after']);
        $this->assertContains('Listar: Etiquetas', $record->changes['permission_ids']['after']);
        $before = AuditRecord::count();
        try {
            AuditLog::transaction(function () use ($role) {
                $role->update(['name' => 'Reverter']);
                throw new \RuntimeException('Falha deliberada');
            });
        } catch (\RuntimeException) {
        }
        $this->assertSame('Cargo auditado', $role->fresh()->name);
        $this->assertSame($before, AuditRecord::count());
        // A rendered error response also rolls back, even when the router handles the exception.
        Route::middleware('web')->post('/_audit/rollback', function () use ($role) {
            $role->update(['name' => 'Resposta de erro']);

            return response()->json(['message' => 'Falha'], 422);
        });
        $this->postJson('/_audit/rollback')->assertUnprocessable();
        $this->assertSame('Cargo auditado', $role->fresh()->name);
        $this->assertSame($before, AuditRecord::count());
    }

    public function test_history_and_all_filter_options_are_scoped_and_technical_access_is_separate(): void
    {
        $owner = $this->owner();
        $other = $this->owner();
        $this->actingAs($other)->postJson($this->customersPath($other), ['name' => 'Pessoa de outra conta'])->assertNoContent();
        $foreign = AuditRecord::where('entity_name', 'Pessoa de outra conta')->sole();
        $this
            ->actingAs($owner)
            ->getJson('/conta/auditoria')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Pessoa de outra conta']);
        $this->getJson('/conta/auditoria/'.$foreign->id.'/visualizar')->assertNotFound();
        $this->getJson('/conta/auditoria?account='.$other->account_id)->assertUnprocessable();
        $role = Role::create(['name' => 'Leitor', 'account_id' => $owner->account_id]);
        $role
            ->permissions()
            ->sync(Permission::whereIn('slug', ['account.audit.list', 'account.audit.view'])->pluck('id'));
        $reader = User::factory()->forAccount($owner->account_id, $role)->create();
        $local = AuditRecord::where('entity_type', 'account')->where('entity_id', $owner->account_id)->sole();
        $this
            ->actingAs($reader)
            ->getJson('/conta/auditoria/'.$local->id.'/visualizar')
            ->assertOk()
            ->assertJsonPath('technical', null)
            ->assertJsonPath('event_key', $local->event);
        $this->getJson('/saas/auditoria')->assertForbidden();
        $role->permissions()->detach();
        $this->getJson('/conta/auditoria')->assertForbidden();
        $this->getJson('/conta/auditoria/'.$local->id.'/visualizar')->assertForbidden();
        $platform = User::factory()->create(['is_saas_admin' => true]);

        // PD-02 (E08): fora da inspeção, a plataforma não vê os eventos de negócio das contas.
        $this
            ->actingAs($platform)
            ->getJson('/saas/auditoria?account='.$other->account_id.'&entity=person')
            ->assertOk()
            ->assertJsonPath('records.total', 0)
            ->assertJsonMissing(['name' => 'Pessoa de outra conta']);
        $this->getJson('/saas/auditoria/'.$foreign->id.'/visualizar')->assertNotFound();

        $accountEvent = AuditRecord::where('entity_type', 'account')
            ->where('entity_id', $other->account_id)
            ->sole();
        $this
            ->getJson('/saas/auditoria/'.$accountEvent->id.'/visualizar')
            ->assertOk()
            ->assertJsonPath('technical.entity_id', $other->account_id);
    }

    public function test_secrets_and_photo_contents_never_enter_either_audit_view(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        AuditLog::transaction(function () use ($owner) {
            $owner->password = 'PrivatePassword123';
            $owner->two_factor_secret = encrypt('PrivateSecret');
            $owner->two_factor_recovery_codes = encrypt('["PrivateRecovery"]');
            $owner->profile_photo = 'private-photo-path';
            $owner->save();
        });
        $r = AuditRecord::where('entity_type', 'user')
            ->where('entity_id', $owner->id)
            ->where('event', 'updated')
            ->sole();
        foreach ([
            'PrivatePassword123',
            'PrivateSecret',
            'PrivateRecovery',
            'private-photo-path',
            $owner->password,
            $owner->two_factor_secret,
        ] as $secret) {
            $this->assertStringNotContainsString($secret, $r->toJson());
            $this->getJson('/conta/auditoria/'.$r->id.'/visualizar')->assertOk()->assertDontSee($secret);
        }
        $this->assertSame('Atualizado', $r->changes['password']['after']);
        $this->assertSame('Foto atualizada', $r->changes['profile_photo']['after']);
    }

    public function test_inspection_keeps_real_platform_actor_and_represented_user(): void
    {
        $owner = $this->owner();
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $this
            ->actingAs($platform)
            ->postJson('/saas/contas/'.$owner->account_id.'/inspecao', ['user_id' => $owner->id, 'mode' => 'edit'])
            ->assertOk();
        $this->postJson($this->customersPath($owner), ['name' => 'Criada por inspeção'])->assertNoContent();
        $r = AuditRecord::where('entity_name', 'Criada por inspeção')->sole();
        $this->assertSame($platform->id, $r->actor_id);
        $this->assertSame($platform->name, $r->actor_name);
        $this->assertSame($owner->id, $r->represented_user_id);
        $this->assertSame('platform-account', $r->context);
        $this->postJson('/conta/inspecao/encerrar')->assertOk();
        foreach (['inspection-started', 'inspection-ended'] as $event) {
            $this->assertDatabaseHas('audit_records', [
                'event' => $event,
                'actor_id' => $platform->id,
                'represented_user_id' => $owner->id,
            ]);
        }
        // The next ordinary request must not inherit the inspection context.
        $this->actingAs($owner)->postJson($this->customersPath($owner), ['name' => 'Criada pela conta'])->assertNoContent();
        $normal = AuditRecord::where('entity_name', 'Criada pela conta')->sole();
        $this->assertSame($owner->id, $normal->actor_id);
        $this->assertNull($normal->represented_user_id);
    }

    public function test_audit_formats_legacy_values_without_rewriting_history_or_free_text(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        foreach (['edit' => 'Leitura e edição', 'read-only' => 'Somente leitura'] as $mode => $label) {
            AuditLog::record('account', $owner->account_id, $owner->account_id, 'inspection-started', 'Conta', [
                'mode' => ['label' => 'Modo de inspeção', 'before' => null, 'after' => $mode],
                'name' => ['label' => 'Nome', 'before' => 'read-only', 'after' => 'edit'],
                'person_type' => ['label' => 'Tipo de pessoa', 'before' => 'individual', 'after' => 'company'],
            ]);
            $record = AuditRecord::latest('id')->firstOrFail();
            $this->getJson('/conta/auditoria/'.$record->id.'/visualizar')->assertOk()
                ->assertJsonPath('changes.0.after', $label)->assertJsonPath('changes.0.before', null)
                ->assertJsonPath('changes.1.before', 'read-only')->assertJsonPath('changes.1.after', 'edit')
                ->assertJsonPath('changes.2.before', 'Pessoa física')
                ->assertJsonPath('changes.2.after', 'Pessoa jurídica');
            $this->assertSame($mode, $record->fresh()->changes['mode']['after']);
        }
        AuditLog::record('tag', 999, $owner->account_id, 'updated', 'Etiqueta', [
            'module' => ['label' => 'Módulo', 'before' => null, 'after' => 'people'],
            'active' => ['label' => 'Ativa', 'before' => true, 'after' => false],
        ]);
        $record = AuditRecord::latest('id')->firstOrFail();
        $this->getJson('/conta/auditoria/'.$record->id.'/visualizar')->assertOk()
            ->assertJsonPath('changes.0.after', 'Cadastro anterior')
            ->assertJsonPath('changes.1.before', 'Ativa')
            ->assertJsonPath('changes.1.after', 'Inativa');
        $this->assertTrue($record->fresh()->changes['active']['before']);

        foreach (['customers' => 'Clientes', 'suppliers' => 'Fornecedores'] as $module => $label) {
            AuditLog::record('tag', 999, $owner->account_id, 'updated', 'Etiqueta', [
                'module' => ['label' => 'Módulo', 'before' => 'people', 'after' => $module],
                'name' => ['label' => 'Nome', 'before' => 'people', 'after' => $module],
            ]);
            $record = AuditRecord::latest('id')->firstOrFail();

            $this->getJson('/conta/auditoria/'.$record->id.'/visualizar')
                ->assertOk()
                ->assertJsonPath('changes.0.before', 'Cadastro anterior')
                ->assertJsonPath('changes.0.after', $label)
                ->assertJsonPath('changes.1.before', 'people')
                ->assertJsonPath('changes.1.after', $module);
            $this->assertSame($module, $record->fresh()->changes['module']['after']);
        }

    }

    public function test_help_search_and_direct_articles_enforce_context_and_permissions(): void
    {
        $owner = $this->owner();
        $this
            ->actingAs($owner)
            ->getJson('/ajuda')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'clientes'])
            ->assertJsonFragment(['slug' => 'central-de-ajuda'])
            ->assertJsonMissing(['slug' => 'contas']);
        $this->getJson('/ajuda?search=fornecedor')->assertOk()->assertJsonFragment(['slug' => 'clientes']);
        $this->getJson('/ajuda/clientes')->assertOk()->assertJsonPath('article.title', 'Clientes');
        $this->getJson('/ajuda/pessoas')->assertNotFound();
        $this->getJson('/ajuda/contas')->assertNotFound();
        $this->getJson('/ajuda?search=termoimpossivel')->assertOk()->assertJsonCount(0, 'articles');
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $this
            ->actingAs($platform)
            ->getJson('/ajuda')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'contas'])
            ->assertJsonMissing(['slug' => 'clientes']);
        $this->getJson('/ajuda/pessoas')->assertNotFound();
        $role = Role::create(['name' => 'Sem pessoas', 'account_id' => $owner->account_id]);
        $role->permissions()->sync(Permission::whereIn('slug', ['self.help.list', 'self.help.view'])->pluck('id'));
        $reader = User::factory()->forAccount($owner->account_id, $role)->create();
        $this->actingAs($reader)->getJson('/ajuda')->assertOk()->assertJsonMissing(['slug' => 'clientes']);
        $this->getJson('/ajuda/pessoas')->assertNotFound();
        $this->getJson('/ajuda/central-de-ajuda')->assertOk();
        $role->permissions()->detach();
        $this->getJson('/ajuda')->assertForbidden();
    }

    public function test_help_html_is_sanitized_and_search_links_to_a_section(): void
    {
        $owner = $this->owner();
        $article = app(HelpCatalog::class)->render([
            'body' => "## Uma seção\n<script>alert('x')</script>\n[Link](javascript:alert(1))\n",
        ], $owner);
        $this->assertStringNotContainsString('<script', $article['html']);
        $this->assertStringNotContainsString('href="javascript:', $article['html']);
        $this->assertStringContainsString('id="uma-secao"', $article['html']);
        $this
            ->actingAs($owner)
            ->getJson('/ajuda?search=reativar')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'clientes', 'anchor' => 'editar-ou-reativar']);
        $this->getJson('/conta/auditoria?until=2030-01-01')->assertOk();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_catalog_sync_audits_individual_changes_and_unchanged_sync_is_silent(): void
    {
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $this->actingAs($platform);
        $permission = Permission::where('slug', 'account.tags.list')->sole();
        $permission->update(['name' => 'Rótulo antigo']);
        $this->artisan('permissions:sync')->assertSuccessful();
        $record = AuditRecord::where('event', 'catalog-synchronized')->latest('id')->firstOrFail();
        $this->assertSame('Rótulo antigo', $record->changes['account.tags.list']['before']);
        $this->assertSame('Listar: Etiquetas', $record->changes['account.tags.list']['after']);
        $this->assertSame($platform->id, $record->actor_id);
        $before = AuditRecord::count();
        $this->artisan('permissions:sync')->assertSuccessful();
        $this->assertSame($before, AuditRecord::count());
    }

    public function test_catalog_metadata_changes_record_the_actual_fields_without_a_false_name_change(): void
    {
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $this->actingAs($platform);
        $permission = Permission::where('slug', 'account.tags.list')->sole();
        $permission->update(['scope' => 'platform', 'module' => 'anterior', 'is_system' => false]);

        $this->artisan('permissions:sync')->assertSuccessful();
        $record = AuditRecord::where('event', 'catalog-synchronized')->latest('id')->firstOrFail();

        $this->assertArrayNotHasKey('account.tags.list', $record->changes);
        $this->assertSame('platform', $record->changes['account.tags.list.scope']['before']);
        $this->assertSame('account', $record->changes['account.tags.list.scope']['after']);
        $this->assertSame('anterior', $record->changes['account.tags.list.module']['before']);
        $this->assertSame('tags', $record->changes['account.tags.list.module']['after']);
        $this->assertFalse($record->changes['account.tags.list.is_system']['before']);
        $this->assertTrue($record->changes['account.tags.list.is_system']['after']);
        $this->assertSame($platform->id, $record->actor_id);
        $this->assertNull($record->account_id);

        $count = AuditRecord::count();
        $this->artisan('permissions:sync')->assertSuccessful();
        $this->assertSame($count, AuditRecord::count());
    }
}
