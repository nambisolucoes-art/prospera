<?php

namespace Tests\Feature;

use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Domains\Clientes\Domain\Models\Customer;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\BuildsCommercialScenario;
use Tests\TestCase;

/**
 * Pessoas sem tela própria (remoção do cadastro avulso): o acesso nasce em Usuários, os papéis comerciais em
 * Clientes e Fornecedores, e as etiquetas convencionais ficam na relação comercial da empresa.
 */
class PeopleDirectoryTest extends TestCase
{
    use BuildsCommercialScenario;
    use RefreshDatabase;

    /** @var array<int, Company> */
    private array $companies = [];

    private function owner(string $company = 'Empresa de pessoas', string $root = '11222333'): User
    {
        $account = Client::create(['name' => 'Conta teste', 'active' => true]);
        $this->companies[$account->id] = $this->company($account, $company, $root);

        return User::factory()->asAccountAdministrator($account->id)->create();
    }

    private function customers(User $owner): string
    {
        return '/conta/empresa/'.$this->companies[$this->accountId($owner)]->code.'/clientes';
    }

    private function unit(User $owner): string
    {
        return '/conta/estabelecimento/'.$this->companies[$this->accountId($owner)]->primaryEstablishment()->sole()->code;
    }

    private function memberRole(User $owner): int
    {
        return (int) Role::where('account_id', $this->accountId($owner))->where('system_key', 'member')->value('id');
    }

    public function test_person_can_gain_access_without_creating_another_person(): void
    {
        Notification::fake();
        $owner = $this->owner();
        $this->actingAs($owner);

        $this->postJson($this->customers($owner), [
            'name' => 'Maria',
            'email' => 'maria@example.test',
        ])->assertNoContent();

        $person = Person::where('name', 'Maria')->sole();
        $people = Person::count();

        // O acesso nasce em Usuários sobre a Pessoa existente, sem duplicar o cadastro.
        $this->postJson('/conta/usuarios', [
            'person_registration' => 'existing',
            'person_id' => $person->id,
            'mode' => 'invite',
            'role_id' => $this->memberRole($owner),
            'scope' => 'tenant',
        ])->assertOk();

        $this->assertSame($people, Person::count());

        $user = $person->user()->sole();

        $this->assertSame('maria@example.test', $user->email);
        $this->assertSame($this->accountId($owner), $this->accountId($user));
        $this->assertTrue($person->tags()->where('system_key', 'user')->exists());
        $this->assertTrue($person->customers()->sole()->active);
        $this->assertDatabaseHas('audit_records', [
            'entity_type' => 'user',
            'actor_id' => $owner->id,
            'account_id' => $this->accountId($owner),
            'event' => 'created',
        ]);
    }

    public function test_directory_rejects_other_accounts_and_manual_special_tags(): void
    {
        $a = $this->owner();
        $b = $this->owner('Empresa da outra conta', '99888777');
        $this->actingAs($a);

        // A Pessoa de outra conta não é encontrada para receber acesso.
        $this->postJson('/conta/usuarios', [
            'person_registration' => 'existing',
            'person_id' => $this->membership($b)->person_id,
            'mode' => 'invite',
            'role_id' => $this->memberRole($a),
            'scope' => 'tenant',
        ])->assertNotFound();

        $special = Tag::userTag($this->accountId($a));

        $this->patch('/conta/etiquetas/'.$special->id, [
            'name' => 'Outro',
            'module' => 'people',
            'color' => '#123456',
        ])->assertForbidden();

        $this->postJson($this->customers($a), ['name' => 'Teste', 'tag_ids' => [$special->id]])
            ->assertUnprocessable();

        $this->actingAs($b)->post('/conta/etiquetas', [
            'name' => 'Externo',
            'module' => 'customers',
            'color' => '#123456',
        ]);

        $external = Tag::where('name', 'Externo')->sole();

        $this->actingAs($a)
            ->postJson($this->customers($a), ['name' => 'Teste', 'tag_ids' => [$external->id]])
            ->assertUnprocessable();
        $this->assertDatabaseMissing('people', ['name' => 'Teste']);

        $member = User::factory()->forAccount($this->accountId($a))->create();

        $this->actingAs($member->fresh())->getJson($this->unit($a).'/clientes')->assertForbidden();
    }

    public function test_duplicate_access_rolls_back_person_and_audit(): void
    {
        $a = $this->owner();
        $this->actingAs($a);

        // A resposta do tenant é a mesma quando a identidade já existe, sem criar Pessoa nem evento.
        $this->postJson('/conta/usuarios', [
            'name' => 'Duplicado',
            'email' => $a->email,
            'mode' => 'invite',
            'role_id' => $this->memberRole($a),
            'scope' => 'tenant',
        ])->assertOk();

        $this->assertDatabaseMissing('people', ['name' => 'Duplicado']);
        $this->assertDatabaseMissing('audit_records', ['entity_type' => 'person', 'entity_name' => 'Duplicado']);
    }

    public function test_identity_updates_keep_the_same_user_and_noop_saves_are_not_audited(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        $this->postJson($this->customers($owner), ['person_id' => $this->membership($owner)->person_id])
            ->assertNoContent();

        $url = $this->customers($owner).'/'.Customer::sole()->id;
        $count = AuditRecord::count();

        $this->patchJson($url, ['name' => $owner->name, 'email' => $owner->email])->assertNoContent();

        $this->assertSame($count, AuditRecord::count());

        $this->patchJson($url, [
            'name' => 'Novo nome',
            'email' => 'alterado@example.test',
            'phone' => '71999999999',
        ])->assertNoContent();

        $person = $this->membership($owner)->person()->sole();

        // A Pessoa muda; a identidade de login continua a mesma.
        $this->assertSame($owner->name, $owner->fresh()->name);
        $this->assertSame($owner->email, $owner->fresh()->email);
        $this->assertNotNull($owner->fresh()->email_verified_at);
        $this->assertSame('Novo nome', $person->name);
        $this->assertSame('alterado@example.test', $person->email);
        $this->assertSame('71999999999', $person->phone);
        $this->assertCount(1, $person->tags()->where('system_key', 'user')->get());
        $this->assertGreaterThan($count, AuditRecord::count());
    }

    public function test_access_is_only_created_when_the_user_tag_is_selected(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        // O cadastro comercial nunca cria acesso, mesmo com campos de acesso forjados.
        $this->postJson($this->customers($owner), [
            'name' => 'Sem acesso',
            'email' => 'sem@example.test',
            'grant_access' => true,
            'mode' => 'invite',
            'role' => 'account-admin',
        ])->assertNoContent();

        $this->assertFalse(Person::where('name', 'Sem acesso')->sole()->user()->exists());

        $this->postJson($this->customers($owner), [
            'name' => 'Com etiqueta',
            'tag_ids' => [Tag::userTag($this->accountId($owner))->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('tag_ids');

        // Em Usuários, o acesso exige o e-mail de acesso.
        $this->postJson('/conta/usuarios', [
            'name' => 'Com acesso',
            'mode' => 'invite',
            'role_id' => $this->memberRole($owner),
            'scope' => 'tenant',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertDatabaseMissing('people', ['name' => 'Com acesso']);
        $this->assertTrue($this->membership($owner)->person()->sole()->user()->exists());
    }

    public function test_removing_user_tag_revokes_access_and_preserves_history(): void
    {
        $owner = $this->owner();
        $member = User::factory()->forAccount($this->accountId($owner))->create();
        $membership = AccountMembership::where('user_id', $member->id)->sole();
        $person = $this->membership($member)->person()->sole();

        $this->actingAs($member)->getJson('/conta')->assertOk();

        $this->actingAs($owner)->patchJson('/conta/usuarios/'.$membership->id.'/suspender')->assertOk();

        // Suspender o vínculo retira a etiqueta Usuário e preserva identidade, Pessoa e histórico.
        $this->assertTrue($member->fresh()->access_enabled);
        $this->assertSame('suspended', $membership->fresh()->status);
        $this->assertDatabaseHas('people', ['id' => $person->id]);
        $this->assertFalse($person->tags()->where('system_key', 'user')->exists());

        $event = AuditRecord::where('entity_type', 'account-membership')
            ->where('entity_id', $membership->id)
            ->where('event', 'updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($owner->id, $event->actor_id);
        $this->assertSame($this->accountId($owner), $event->account_id);
        $this->assertSame('suspended', $event->changes['status']['after']);

        $this->actingAs($member->fresh())->getJson('/conta')->assertForbidden();

        $this->actingAs($owner)->patchJson('/conta/usuarios/'.$membership->id.'/reativar')->assertOk();

        $this->assertSame('active', $membership->fresh()->status);
        $this->assertTrue($person->tags()->where('system_key', 'user')->exists());
        $this->assertTrue($member->fresh()->access_enabled);
    }

    public function test_revoking_access_requires_its_own_permission_and_rolls_back(): void
    {
        $owner = $this->owner();
        $role = Role::create(['account_id' => $this->accountId($owner), 'name' => 'Editor de pessoas']);
        $role->permissions()->sync(Permission::where('slug', 'account.people.update')->pluck('id'));
        $editor = User::factory()->forAccount($this->accountId($owner), $role)->create();
        $member = User::factory()->forAccount($this->accountId($owner))->create();
        $membership = AccountMembership::where('user_id', $member->id)->sole();
        $count = AuditRecord::count();

        $this->actingAs($editor)->patchJson('/conta/usuarios/'.$membership->id.'/suspender')->assertForbidden();

        $this->assertSame('active', $membership->fresh()->status);
        $this->assertTrue($member->fresh()->access_enabled);
        $this->assertTrue($this->membership($member)->person()->sole()->tags()->where('system_key', 'user')->exists());
        $this->assertSame($count, AuditRecord::count());
    }

    public function test_tag_modal_payload_preserves_permissions_colors_and_audit(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->getJson('/conta/etiquetas/cadastrar')
            ->assertOk()
            ->assertJsonPath('formOpen', true)
            ->assertJsonPath('canList', true)
            ->assertJsonCount(4, 'tags.data');

        $this->postJson('/conta/etiquetas', [
            'name' => 'Etiqueta colorida',
            'module' => 'customers',
            'color' => '#f4db1b',
        ])->assertOk();

        $tag = Tag::where('name', 'Etiqueta colorida')->sole();

        $this->getJson('/conta/etiquetas/'.$tag->id.'/editar')
            ->assertOk()
            ->assertJsonPath('formOpen', true)
            ->assertJsonPath('tag.color', '#f4db1b');

        $this->patchJson('/conta/etiquetas/'.$tag->id, [
            'name' => 'Etiqueta colorida',
            'module' => 'customers',
            'color' => '#ad125a',
        ])->assertOk();

        $event = AuditRecord::where('entity_type', 'tag')
            ->where('entity_id', $tag->id)
            ->where('event', 'updated')
            ->sole();

        $this->assertSame('#f4db1b', $event->changes['color']['before']);
        $this->assertSame('#ad125a', $event->changes['color']['after']);
        $this->assertSame($owner->id, $event->actor_id);

        $this->postJson($this->customers($owner), ['name' => 'Pessoa colorida', 'tag_ids' => [$tag->id]])
            ->assertNoContent();

        $customer = Customer::sole();
        $code = $this->companies[$this->accountId($owner)]->code;

        $this->getJson($this->unit($owner).'/clientes/'.$code.'/'.$customer->id.'/editar')
            ->assertOk()
            ->assertJsonPath('editing.tag_ids', [$tag->id])
            ->assertJsonFragment(['id' => $tag->id, 'name' => 'Etiqueta colorida', 'color' => '#ad125a']);

        $this->getJson($this->customers($owner).'/'.$customer->id.'/visualizar')
            ->assertOk()
            ->assertJsonPath('tags.1.color', '#ad125a');

        $this->getJson($this->unit($owner).'/clientes')
            ->assertOk()
            ->assertJsonPath('relations.data.0.tags.1.color', '#ad125a');
    }

    public function test_tag_lifecycle_preserves_links_and_audits_deletion(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $payload = ['name' => 'Atendimento', 'module' => 'customers', 'color' => '#ad125a'];

        $this->postJson('/conta/etiquetas', $payload)->assertOk();

        $tag = Tag::where('name', 'Atendimento')->sole();

        $this->postJson($this->customers($owner), ['name' => 'Pessoa vinculada', 'tag_ids' => [$tag->id]])
            ->assertNoContent();

        $customer = Customer::sole();
        $code = $this->companies[$this->accountId($owner)]->code;
        $editForm = $this->unit($owner).'/clientes/'.$code.'/'.$customer->id.'/editar';
        $url = $this->customers($owner).'/'.$customer->id;
        $count = AuditRecord::count();

        $this->deleteJson('/conta/etiquetas/'.$tag->id)->assertUnprocessable()->assertJsonValidationErrors('_form');
        $this->assertSame($count, AuditRecord::count());

        $this->patchJson('/conta/etiquetas/'.$tag->id, [...$payload, 'active' => false])->assertOk();

        $this->assertFalse($tag->fresh()->active);

        // Inativa: some do cadastro novo, continua na relação que já a possuía.
        $this->getJson($this->unit($owner).'/clientes/cadastrar')->assertOk()->assertJsonMissing(['name' => 'Atendimento']);
        $this->getJson($editForm)->assertOk()->assertJsonFragment(['name' => 'Atendimento']);

        $this->postJson($this->customers($owner), ['name' => 'Vínculo indevido', 'tag_ids' => [$tag->id]])
            ->assertUnprocessable();
        $this->assertDatabaseMissing('people', ['name' => 'Vínculo indevido']);

        $this->patchJson($url, ['tag_ids' => [$tag->id]])->assertNoContent();
        $this->assertDatabaseHas('customers_tags', ['relation_id' => $customer->id, 'tag_id' => $tag->id]);

        $this->patchJson('/conta/etiquetas/'.$tag->id, [...$payload, 'active' => true])->assertOk();

        $event = AuditRecord::where('entity_type', 'tag')->where('entity_id', $tag->id)->latest('id')->firstOrFail();

        $this->assertSame($owner->id, $event->actor_id);
        $this->assertSame($this->accountId($owner), $event->account_id);
        $this->assertTrue($event->changes['active']['after']);

        $this->patchJson($url, ['tag_ids' => []])->assertNoContent();
        $this->deleteJson('/conta/etiquetas/'.$tag->id)->assertOk();

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
        $this->assertDatabaseHas('audit_records', [
            'entity_type' => 'tag',
            'entity_id' => $tag->id,
            'event' => 'deleted',
            'actor_id' => $owner->id,
            'account_id' => $this->accountId($owner),
        ]);
    }

    public function test_tag_lifecycle_checks_permissions_account_and_special_protection(): void
    {
        $owner = $this->owner();
        $other = $this->owner();
        $this->actingAs($owner);
        $payload = ['name' => 'Permissões de etiqueta', 'module' => 'customers', 'color' => '#123456'];
        $this->postJson('/conta/etiquetas', $payload)->assertOk();
        $tag = Tag::where('name', $payload['name'])->sole();
        $special = Tag::userTag($this->accountId($owner));
        $this->deleteJson('/conta/etiquetas/'.$special->id)->assertForbidden();
        $this->patchJson('/conta/etiquetas/'.$special->id, [...$payload, 'active' => false])->assertForbidden();
        $this->actingAs($other)->deleteJson('/conta/etiquetas/'.$tag->id)->assertNotFound();
        $role = Role::create(['account_id' => $this->accountId($owner), 'name' => 'Somente editar etiqueta']);
        $role->permissions()->sync(Permission::where('slug', 'account.tags.update')->pluck('id'));
        $editor = User::factory()->forAccount($this->accountId($owner), $role)->create();
        $this->actingAs($editor)->patchJson('/conta/etiquetas/'.$tag->id, $payload)->assertOk();
        $count = AuditRecord::count();
        $this->patchJson('/conta/etiquetas/'.$tag->id, [...$payload, 'active' => false])->assertForbidden();
        $this->deleteJson('/conta/etiquetas/'.$tag->id)->assertForbidden();
        $this->assertTrue($tag->fresh()->active);
        $this->assertSame($count, AuditRecord::count());
        $role->permissions()->attach(Permission::where('slug', 'account.tags.deactivate')->value('id'));
        $this->patchJson('/conta/etiquetas/'.$tag->id, [...$payload, 'active' => false])->assertOk();
        $this->patchJson('/conta/etiquetas/'.$tag->id, [...$payload, 'active' => true])->assertForbidden();
        $this->assertFalse($tag->fresh()->active);
    }

    public function test_people_tags_cannot_be_created_but_existing_links_are_preserved(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $payload = ['name' => 'Cadastro anterior', 'module' => 'people', 'color' => '#123456'];

        $this->postJson('/conta/etiquetas', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('module');
        $this->assertDatabaseMissing('tags', ['name' => $payload['name']]);

        $tag = Tag::create(['account_id' => $this->accountId($owner), ...$payload]);
        $person = $this->membership($owner)->person()->sole();
        $person->tags()->attach($tag->id);

        $this->patchJson('/conta/etiquetas/'.$tag->id, [...$payload, 'active' => false])->assertOk();
        $this->assertDatabaseHas('person_tag', ['person_id' => $person->id, 'tag_id' => $tag->id]);
        $this->assertFalse($tag->fresh()->active);
        $this->assertDatabaseHas('audit_records', [
            'entity_type' => 'tag',
            'entity_id' => $tag->id,
            'event' => 'updated',
            'actor_id' => $owner->id,
            'account_id' => $this->accountId($owner),
        ]);

        $this->postJson('/conta/etiquetas', [...$payload, 'module' => 'customers'])->assertOk();
        $commercial = Tag::where('module', 'customers')->where('name', $payload['name'])->sole();
        $this->patchJson('/conta/etiquetas/'.$commercial->id, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('module');
    }

    public function test_tag_form_does_not_list_records_without_list_permission(): void
    {
        $owner = $this->owner();
        $other = $this->owner();
        $role = Role::create(['account_id' => $this->accountId($owner), 'name' => 'Cadastro de etiquetas']);
        $role
            ->permissions()
            ->sync(Permission::whereIn('slug', ['account.tags.create', 'account.tags.update'])->pluck('id'));
        $user = User::factory()->forAccount($this->accountId($owner), $role)->create();
        $this->actingAs($user)->getJson('/conta/etiquetas')->assertForbidden();
        $this
            ->getJson('/conta/etiquetas/cadastrar')
            ->assertOk()
            ->assertJsonPath('formOpen', true)
            ->assertJsonPath('canList', false)
            ->assertJsonCount(0, 'tags.data');
        $foreign = Tag::userTag($this->accountId($other));
        $this->getJson('/conta/etiquetas/'.$foreign->id.'/editar')->assertNotFound();
        $this
            ->postJson('/conta/etiquetas', ['name' => 'Cadastro permitido', 'module' => 'customers', 'color' => '#123456'])
            ->assertOk()
            ->assertJsonPath('redirect', fn ($url) => str_ends_with($url, $this->accountPath('/conta/etiquetas/cadastrar')));
        $tag = Tag::where('name', 'Cadastro permitido')->sole();
        $this
            ->getJson('/conta/etiquetas/'.$tag->id.'/editar')
            ->assertOk()
            ->assertJsonPath('tag.id', $tag->id)
            ->assertJsonCount(0, 'tags.data');
    }

    public function test_tag_list_filters_by_search_and_status_within_the_account(): void
    {
        $owner = $this->owner();
        Tag::create([
            'account_id' => $this->accountId($owner),
            'module' => 'people',
            'name' => 'Parceiro VIP',
            'color' => '#123456',
            'active' => true,
        ]);
        Tag::create([
            'account_id' => $this->accountId($owner),
            'module' => 'people',
            'name' => 'Parceiro antigo',
            'color' => '#123456',
            'active' => false,
        ]);
        $other = $this->owner();
        Tag::create([
            'account_id' => $this->accountId($other),
            'module' => 'people',
            'name' => 'Parceiro externo',
            'color' => '#123456',
            'active' => true,
        ]);
        $this->actingAs($owner);

        $this
            ->getJson('/conta/etiquetas?search=parceiro')
            ->assertOk()
            ->assertJsonCount(2, 'tags.data')
            ->assertJsonPath('filters.search', 'parceiro');
        $this
            ->getJson('/conta/etiquetas?search=parceiro&status=active')
            ->assertOk()
            ->assertJsonCount(1, 'tags.data')
            ->assertJsonPath('tags.data.0.name', 'Parceiro VIP');
        $this
            ->getJson('/conta/etiquetas?status=inactive')
            ->assertOk()
            ->assertJsonCount(1, 'tags.data')
            ->assertJsonPath('tags.data.0.name', 'Parceiro antigo');
        $this->getJson('/conta/etiquetas?search=%25')->assertOk()->assertJsonCount(0, 'tags.data');
        $this
            ->getJson('/conta/etiquetas?status=outro&module=outro')
            ->assertOk()
            ->assertJsonPath('filters.status', '')
            ->assertJsonPath('filters.module', '')
            ->assertJsonCount(6, 'tags.data');
    }
}
