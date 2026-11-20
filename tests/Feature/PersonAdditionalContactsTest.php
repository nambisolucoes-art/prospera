<?php

namespace Tests\Feature;

use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonAdditionalContactsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $account = Client::create(['name' => 'Conta de contatos', 'active' => true]);

        return User::factory()->asAccountAdministrator($account->id)->create();
    }

    public function test_additional_contacts_are_normalized_exposed_and_audited(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->postJson('/conta/pessoas', [
            'name' => 'Contato com listas',
            'phone' => '7133334444',
            'email' => 'principal@example.test',
            'additional_phones' => ['(71) 99999-1234', '', '(11) 3333-4444'],
            'additional_emails' => [' Alternativo@Example.Test ', ''],
        ])->assertOk();
        $person = Person::where('name', 'Contato com listas')->sole();
        $this->assertSame(['71999991234', '1133334444'], $person->additional_phones);
        $this->assertSame(['alternativo@example.test'], $person->additional_emails);
        $this
            ->getJson('/conta/pessoas/'.$person->id.'/visualizar')
            ->assertOk()
            ->assertJsonPath('additional_phones', ['71999991234', '1133334444'])
            ->assertJsonPath('additional_emails', ['alternativo@example.test']);
        $event = AuditRecord::where('entity_type', 'person')->where('entity_id', $person->id)->sole();
        $this->assertSame(['71999991234', '1133334444'], $event->changes['additional_phones']['after']);
        $this->assertSame(['alternativo@example.test'], $event->changes['additional_emails']['after']);
        $this->assertSame($owner->id, $event->actor_id);
        $this->assertSame($owner->account_id, $event->account_id);
        $this->getJson('/conta/auditoria/'.$event->id.'/visualizar')->assertOk()->assertJsonFragment([
            'label' => 'Telefones adicionais',
            'before' => null,
            'after' => ['(71) 99999-1234', '(11) 3333-4444'],
        ]);
    }

    public function test_contacts_can_be_preserved_or_cleared_without_changing_login_or_primary_fields(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $path = '/conta/pessoas/'.$this->membership($owner)->person_id;
        $tags = $this->membership($owner)->person()->sole()->tags->pluck('id')->all();
        $base = ['name' => $owner->name, 'email' => $owner->email, 'phone' => '7133334444', 'tag_ids' => $tags];
        $payload = [
            ...$base,
            'additional_phones' => ['71999991234'],
            'additional_emails' => ['adicional@example.test'],
        ];
        $this->patchJson($path, $payload)->assertOk();
        $count = AuditRecord::count();
        $this->patchJson($path, $payload)->assertOk();
        $this->assertSame($count, AuditRecord::count());
        $this->patchJson($path, $base)->assertOk();
        $this->assertSame(['adicional@example.test'], $this->membership($owner)->person()->firstOrFail()->additional_emails);
        $this->patchJson($path, [...$base, 'additional_phones' => [''], 'additional_emails' => ['']])->assertOk();
        $person = $this->membership($owner)->person()->firstOrFail();
        $this->assertSame([], $person->additional_phones);
        $this->assertSame([], $person->additional_emails);
        $this->assertSame('7133334444', $person->phone);
        $this->assertSame($owner->email, $person->email);
        $this->assertSame($owner->email, $owner->fresh()->email);
    }

    public function test_invalid_lists_do_not_change_person_or_audit_and_other_accounts_are_isolated(): void
    {
        $owner = $this->owner();
        $other = $this->owner();
        $this->actingAs($owner);
        $count = AuditRecord::count();
        $path = '/conta/pessoas/'.$this->membership($owner)->person_id;
        $base = [
            'name' => 'Nome não deve mudar',
            'email' => $owner->email,
            'tag_ids' => $this->membership($owner)->person()->sole()->tags->pluck('id')->all(),
        ];
        $this
            ->patchJson($path, [...$base, 'additional_emails' => ['inválido']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('additional_emails.0');
        $this
            ->patchJson($path, [...$base, 'additional_phones' => 'não é lista'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('additional_phones');
        $this
            ->patchJson($path, [...$base, 'additional_emails' => array_fill(0, 21, 'teste@example.test')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('additional_emails');
        $this
            ->patchJson('/conta/pessoas/'.$this->membership($other)->person_id, [...$base, 'additional_phones' => ['71999991234']])
            ->assertNotFound();
        $this->assertSame($count, AuditRecord::count());
        $this->assertSame($owner->name, $owner->fresh()->name);
        $this->assertSame([], $this->membership($owner)->person()->firstOrFail()->additional_emails);
    }

    public function test_inspection_keeps_real_actor_for_additional_contacts_and_read_only_blocks_changes(): void
    {
        $owner = $this->owner();
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $this
            ->actingAs($platform)
            ->postJson('/saas/contas/'.$owner->account_id.'/inspecao', ['user_id' => $owner->id, 'mode' => 'edit'])
            ->assertOk();
        $base = [
            'name' => $owner->name,
            'email' => $owner->email,
            'tag_ids' => $this->membership($owner)->person()->sole()->tags->pluck('id')->all(),
        ];
        $this
            ->patchJson(
                '/conta/pessoas/'.$this->membership($owner)->person_id,
                [...$base, 'additional_emails' => ['inspecao@example.test']],
            )
            ->assertOk();
        $event = AuditRecord::where('entity_type', 'person')
            ->where('entity_id', $this->membership($owner)->person_id)
            ->where('event', 'updated')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame($platform->id, $event->actor_id);
        $this->assertSame($owner->id, $event->represented_user_id);
        $this->assertSame($owner->account_id, $event->account_id);
        $this->postJson('/conta/inspecao/encerrar')->assertOk();
        $this
            ->actingAs($platform)
            ->postJson('/saas/contas/'.$owner->account_id.'/inspecao', ['user_id' => $owner->id, 'mode' => 'read-only'])
            ->assertOk();
        $count = AuditRecord::count();
        $this->patchJson('/conta/pessoas/'.$this->membership($owner)->person_id, [...$base, 'additional_emails' => []])->assertForbidden();
        $this->assertSame(['inspecao@example.test'], $this->membership($owner)->person()->firstOrFail()->additional_emails);
        $this->assertSame($count, AuditRecord::count());
    }

    public function test_empty_contact_lists_do_not_add_empty_changes_to_creation_history(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->postJson('/conta/pessoas', [
            'name' => 'Sem contatos adicionais',
            'additional_phones' => [''],
            'additional_emails' => [''],
        ])->assertOk();
        $person = Person::where('name', 'Sem contatos adicionais')->sole();
        $event = AuditRecord::where('entity_type', 'person')->where('entity_id', $person->id)->sole();
        $this->assertArrayNotHasKey('additional_phones', $event->changes);
        $this->assertArrayNotHasKey('additional_emails', $event->changes);
    }

    public function test_people_editor_can_change_additional_contacts_without_user_management_permission(): void
    {
        $owner = $this->owner();
        $role = Role::create(['account_id' => $owner->account_id, 'name' => 'Editor de contatos']);
        $role->permissions()->sync(Permission::where('slug', 'account.people.update')->pluck('id'));
        $editor = User::factory()->forAccount($owner->account_id, $role)->create();
        $this->actingAs($editor)->patchJson('/conta/pessoas/'.$this->membership($owner)->person_id, [
            'name' => $owner->name,
            'email' => $owner->email,
            'tag_ids' => $this->membership($owner)->person()->sole()->tags->pluck('id')->all(),
            'additional_emails' => ['contato@example.test'],
        ])->assertOk();
        $this->assertSame($owner->email, $owner->fresh()->email);
        $this->assertSame(['contato@example.test'], $this->membership($owner)->person()->firstOrFail()->additional_emails);
    }
}
