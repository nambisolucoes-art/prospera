<?php

namespace Tests\Feature;

use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PeopleDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $account = Client::create(['name' => 'Conta teste', 'active' => true]);

        return User::factory()->create(['account_id' => $account->id, 'is_account_admin' => true]);
    }

    public function test_person_can_gain_access_without_creating_another_person(): void
    {
        Notification::fake();
        $owner = $this->owner();
        $this->actingAs($owner);
        $this->post('/conta/etiquetas', ['name' => 'Contato comercial', 'module' => 'people', 'color' => '#123456'])->assertRedirect();
        $tag = Tag::whereNull('system_key')->sole();
        $this->post('/conta/pessoas', ['name' => 'Maria', 'email' => 'maria@example.test', 'grant_access' => '0', 'tag_ids' => [$tag->id]])->assertRedirect();
        $person = Person::where('name', 'Maria')->sole();
        $this->patch('/conta/pessoas/'.$person->id, ['name' => 'Maria', 'email' => 'maria@example.test', 'grant_access' => '1', 'mode' => 'invite', 'role_id' => Role::where('account_id', auth()->user()->account_id)->where('system_key', 'member')->value('id'), 'tag_ids' => [$tag->id, Tag::userTag($owner->account_id)->id]])->assertRedirect();
        $this->assertDatabaseCount('people', 2);
        $user = $person->user()->sole();
        $this->assertSame($owner->account_id, $user->account_id);
        $this->assertCount(2, $person->tags()->get());
        $this->getJson('/conta/pessoas')->assertJsonCount(2, 'people.data');
        $this->assertDatabaseHas('directory_audit_events', ['actor_id' => $owner->id, 'account_id' => $owner->account_id, 'event' => 'user.created']);
    }

    public function test_directory_rejects_other_accounts_and_manual_special_tags(): void
    {
        $a = $this->owner();
        $b = $this->owner();
        $this->actingAs($a);
        $this->getJson('/conta/pessoas/'.$b->person_id.'/editar')->assertNotFound();
        $special = Tag::userTag($a->account_id);
        $this->patch('/conta/etiquetas/'.$special->id, ['name' => 'Outro', 'module' => 'people', 'color' => '#123456'])->assertForbidden();
        $this->postJson('/conta/pessoas', ['name' => 'Teste', 'tag_ids' => [$special->id]])->assertUnprocessable();
        $this->actingAs($b)->post('/conta/etiquetas', ['name' => 'Externo', 'module' => 'people', 'color' => '#123456']);
        $external = Tag::where('name', 'Externo')->sole();
        $this->actingAs($a)->postJson('/conta/pessoas', ['name' => 'Teste', 'tag_ids' => [$external->id]])->assertUnprocessable();
        $member = User::factory()->create(['account_id' => $a->account_id]);
        $this->actingAs($member->fresh())->getJson('/conta/pessoas')->assertForbidden();
    }

    public function test_duplicate_access_rolls_back_person_and_audit(): void
    {
        $a = $this->owner();
        $this->actingAs($a);
        $this->postJson('/conta/pessoas', ['name' => 'Duplicado', 'email' => $a->email, 'tag_ids' => [Tag::userTag($a->account_id)->id], 'mode' => 'invite', 'role_id' => Role::where('account_id', auth()->user()->account_id)->where('system_key', 'member')->value('id')])->assertUnprocessable();
        $this->assertDatabaseMissing('people', ['name' => 'Duplicado']);
        $this->assertDatabaseCount('directory_audit_events', 0);
    }

    public function test_identity_updates_keep_the_same_user_and_noop_saves_are_not_audited(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $path = '/conta/pessoas/'.$owner->person_id;
        $this->patch($path, ['name' => $owner->name, 'email' => $owner->email, 'tag_ids' => [Tag::userTag($owner->account_id)->id]])->assertRedirect();
        $this->assertDatabaseCount('directory_audit_events', 0);
        $this->patch($path, ['name' => 'Novo nome', 'email' => 'alterado@example.test', 'phone' => '71999999999', 'tag_ids' => [Tag::userTag($owner->account_id)->id]])->assertRedirect();
        $this->assertSame('Novo nome', $owner->fresh()->name);
        $this->assertNull($owner->fresh()->email_verified_at);
        $this->assertSame('71999999999', $owner->person()->first()->phone);
        $this->assertCount(1, $owner->person()->first()->tags()->where('system_key', 'user')->get());
        $this->assertDatabaseCount('directory_audit_events', 1);
    }

    public function test_access_is_only_created_when_the_user_tag_is_selected(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $this->getJson('/conta/pessoas/cadastrar')->assertJsonFragment(['system_key' => 'user']);
        $this->post('/conta/pessoas', ['name' => 'Sem acesso', 'grant_access' => true, 'email' => 'sem@example.test', 'mode' => 'invite', 'role' => 'account-admin'])->assertRedirect();
        $this->assertFalse(Person::where('name', 'Sem acesso')->sole()->user()->exists());
        $this->postJson('/conta/pessoas', ['name' => 'Com acesso', 'tag_ids' => [Tag::userTag($owner->account_id)->id]])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->patchJson('/conta/pessoas/'.$owner->person_id, ['name' => $owner->name, 'email' => $owner->email, 'tag_ids' => []])->assertUnprocessable()->assertJsonValidationErrors('tag_ids');
        $this->assertTrue($owner->person()->sole()->user()->exists());
    }

    public function test_removing_user_tag_revokes_access_and_preserves_history(): void
    {
        $owner = $this->owner();
        $member = User::factory()->create(['account_id' => $owner->account_id]);
        $this->actingAs($owner)->patchJson('/conta/pessoas/'.$member->person_id, ['name' => $member->name, 'email' => $member->email, 'tag_ids' => []])->assertOk();
        $this->assertFalse($member->fresh()->access_enabled);
        $this->assertDatabaseHas('people', ['id' => $member->person_id]);
        $event = AuditRecord::where('entity_type', 'user')->where('entity_id', $member->id)->where('event', 'updated')->latest('id')->firstOrFail();
        $this->assertSame($owner->id, $event->actor_id);
        $this->assertSame($owner->account_id, $event->account_id);
        $this->assertFalse($event->changes['access_enabled']['after']);
        $this->actingAs($member->fresh())->getJson('/conta/pessoas')->assertUnauthorized();
        $this->post('/login', ['email' => $member->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->actingAs($owner)->patchJson('/conta/pessoas/'.$member->person_id, ['name' => $member->name, 'email' => $member->email, 'tag_ids' => [Tag::userTag($owner->account_id)->id]])->assertOk();
        $this->assertTrue($member->fresh()->access_enabled);
    }

    public function test_revoking_access_requires_its_own_permission_and_rolls_back(): void
    {
        $owner = $this->owner();
        $role = Role::create(['account_id' => $owner->account_id, 'name' => 'Editor de pessoas']);
        $role->permissions()->sync(Permission::where('slug', 'account.people.update')->pluck('id'));
        $editor = User::factory()->create(['account_id' => $owner->account_id, 'role_id' => $role->id]);
        $member = User::factory()->create(['account_id' => $owner->account_id]);
        $count = AuditRecord::count();
        $this->actingAs($editor)->patchJson('/conta/pessoas/'.$member->person_id, ['name' => $member->name, 'email' => $member->email, 'tag_ids' => []])->assertForbidden();
        $this->assertTrue($member->fresh()->access_enabled);
        $this->assertTrue($member->person->tags()->where('system_key', 'user')->exists());
        $this->assertSame($count, AuditRecord::count());
    }

    public function test_tag_modal_payload_preserves_permissions_colors_and_audit(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->getJson('/conta/etiquetas/cadastrar')->assertOk()->assertJsonPath('formOpen', true)->assertJsonPath('canList', true)->assertJsonCount(3, 'tags.data');
        $this->postJson('/conta/etiquetas', ['name' => 'Etiqueta colorida', 'module' => 'people', 'color' => '#f4db1b'])->assertOk();
        $tag = Tag::where('name', 'Etiqueta colorida')->sole();
        $this->getJson('/conta/etiquetas/'.$tag->id.'/editar')->assertOk()->assertJsonPath('formOpen', true)->assertJsonPath('tag.color', '#f4db1b');
        $this->patchJson('/conta/etiquetas/'.$tag->id, ['name' => 'Etiqueta colorida', 'module' => 'people', 'color' => '#ad125a'])->assertOk();
        $event = AuditRecord::where('entity_type', 'tag')->where('entity_id', $tag->id)->where('event', 'updated')->sole();
        $this->assertSame('#f4db1b', $event->changes['color']['before']);
        $this->assertSame('#ad125a', $event->changes['color']['after']);
        $this->assertSame($owner->id, $event->actor_id);
        $this->postJson('/conta/pessoas', ['name' => 'Pessoa colorida', 'tag_ids' => [$tag->id]])->assertOk();
        $person = Person::where('name', 'Pessoa colorida')->sole();
        $this->getJson('/conta/pessoas/'.$person->id.'/editar')->assertOk()->assertJsonPath('person.tags.0.color', '#ad125a');
        $this->getJson('/conta/pessoas/'.$person->id.'/visualizar')->assertOk()->assertJsonPath('tags.0.color', '#ad125a');
    }

    public function test_tag_lifecycle_preserves_links_and_audits_deletion(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $payload = ['name' => 'Atendimento', 'module' => 'people', 'color' => '#ad125a'];
        $this->postJson('/conta/etiquetas', $payload)->assertOk();
        $tag = Tag::where('name', 'Atendimento')->sole();
        $this->postJson('/conta/pessoas', ['name' => 'Pessoa vinculada', 'tag_ids' => [$tag->id]])->assertOk();
        $person = Person::where('name', 'Pessoa vinculada')->sole();
        $count = AuditRecord::count();
        $this->deleteJson('/conta/etiquetas/'.$tag->id)->assertUnprocessable()->assertJsonValidationErrors('_form');
        $this->assertSame($count, AuditRecord::count());
        $this->patchJson('/conta/etiquetas/'.$tag->id, [...$payload, 'active' => false])->assertOk();
        $this->assertFalse($tag->fresh()->active);
        $this->getJson('/conta/pessoas/cadastrar')->assertJsonMissing(['name' => 'Atendimento']);
        $this->getJson('/conta/pessoas/'.$person->id.'/editar')->assertJsonFragment(['name' => 'Atendimento', 'active' => false]);
        $this->postJson('/conta/pessoas', ['name' => 'Vínculo indevido', 'tag_ids' => [$tag->id]])->assertUnprocessable();
        $this->assertDatabaseMissing('people', ['name' => 'Vínculo indevido']);
        $this->patchJson('/conta/pessoas/'.$person->id, ['name' => $person->name, 'tag_ids' => [$tag->id]])->assertOk();
        $this->assertTrue($person->tags()->whereKey($tag->id)->exists());
        $this->patchJson('/conta/etiquetas/'.$tag->id, [...$payload, 'active' => true])->assertOk();
        $event = AuditRecord::where('entity_type', 'tag')->where('entity_id', $tag->id)->latest('id')->firstOrFail();
        $this->assertSame($owner->id, $event->actor_id);
        $this->assertSame($owner->account_id, $event->account_id);
        $this->assertTrue($event->changes['active']['after']);
        $this->patchJson('/conta/pessoas/'.$person->id, ['name' => $person->name, 'tag_ids' => []])->assertOk();
        $this->deleteJson('/conta/etiquetas/'.$tag->id)->assertOk();
        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
        $this->assertDatabaseHas('audit_records', ['entity_type' => 'tag', 'entity_id' => $tag->id, 'event' => 'deleted', 'actor_id' => $owner->id, 'account_id' => $owner->account_id]);
    }

    public function test_tag_lifecycle_checks_permissions_account_and_special_protection(): void
    {
        $owner = $this->owner();
        $other = $this->owner();
        $this->actingAs($owner);
        $payload = ['name' => 'Permissões de etiqueta', 'module' => 'people', 'color' => '#123456'];
        $this->postJson('/conta/etiquetas', $payload)->assertOk();
        $tag = Tag::where('name', $payload['name'])->sole();
        $special = Tag::userTag($owner->account_id);
        $this->deleteJson('/conta/etiquetas/'.$special->id)->assertForbidden();
        $this->patchJson('/conta/etiquetas/'.$special->id, [...$payload, 'active' => false])->assertForbidden();
        $this->actingAs($other)->deleteJson('/conta/etiquetas/'.$tag->id)->assertNotFound();
        $role = Role::create(['account_id' => $owner->account_id, 'name' => 'Somente editar etiqueta']);
        $role->permissions()->sync(Permission::where('slug', 'account.tags.update')->pluck('id'));
        $editor = User::factory()->create(['account_id' => $owner->account_id, 'role_id' => $role->id]);
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

    public function test_tag_form_does_not_list_records_without_list_permission(): void
    {
        $owner = $this->owner();
        $other = $this->owner();
        $role = Role::create(['account_id' => $owner->account_id, 'name' => 'Cadastro de etiquetas']);
        $role->permissions()->sync(Permission::whereIn('slug', ['account.tags.create', 'account.tags.update'])->pluck('id'));
        $user = User::factory()->create(['account_id' => $owner->account_id, 'role_id' => $role->id]);
        $this->actingAs($user)->getJson('/conta/etiquetas')->assertForbidden();
        $this->getJson('/conta/etiquetas/cadastrar')->assertOk()->assertJsonPath('formOpen', true)->assertJsonPath('canList', false)->assertJsonCount(0, 'tags.data');
        $foreign = Tag::userTag($other->account_id);
        $this->getJson('/conta/etiquetas/'.$foreign->id.'/editar')->assertNotFound();
        $this->postJson('/conta/etiquetas', ['name' => 'Cadastro permitido', 'module' => 'people', 'color' => '#123456'])->assertOk()->assertJsonPath('redirect', fn ($url) => str_ends_with($url, '/conta/etiquetas/cadastrar'));
        $tag = Tag::where('name', 'Cadastro permitido')->sole();
        $this->getJson('/conta/etiquetas/'.$tag->id.'/editar')->assertOk()->assertJsonPath('tag.id', $tag->id)->assertJsonCount(0, 'tags.data');
    }

    public function test_tag_list_filters_by_search_and_status_within_the_account(): void
    {
        $owner = $this->owner();
        Tag::create(['account_id' => $owner->account_id, 'module' => 'people', 'name' => 'Parceiro VIP', 'color' => '#123456', 'active' => true]);
        Tag::create(['account_id' => $owner->account_id, 'module' => 'people', 'name' => 'Parceiro antigo', 'color' => '#123456', 'active' => false]);
        $other = $this->owner();
        Tag::create(['account_id' => $other->account_id, 'module' => 'people', 'name' => 'Parceiro externo', 'color' => '#123456', 'active' => true]);
        $this->actingAs($owner);

        $this->getJson('/conta/etiquetas?search=parceiro')->assertOk()->assertJsonCount(2, 'tags.data')->assertJsonPath('filters.search', 'parceiro');
        $this->getJson('/conta/etiquetas?search=parceiro&status=active')->assertOk()->assertJsonCount(1, 'tags.data')->assertJsonPath('tags.data.0.name', 'Parceiro VIP');
        $this->getJson('/conta/etiquetas?status=inactive')->assertOk()->assertJsonCount(1, 'tags.data')->assertJsonPath('tags.data.0.name', 'Parceiro antigo');
        $this->getJson('/conta/etiquetas?search=%25')->assertOk()->assertJsonCount(0, 'tags.data');
        $this->getJson('/conta/etiquetas?status=outro&module=outro')->assertOk()->assertJsonPath('filters.status', '')->assertJsonPath('filters.module', '')->assertJsonCount(5, 'tags.data');
    }
}
