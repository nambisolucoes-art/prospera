<?php

namespace Tests\Feature;

use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Domains\Clientes\Domain\Models\Customer;
use App\Domains\Organizacao\Domain\Models\Company;
use App\Domains\Pessoas\Domain\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsCommercialScenario;
use Tests\TestCase;

/**
 * Contatos adicionais da Pessoa, editados pela ficha comercial de Clientes (E07).
 */
class PersonAdditionalContactsTest extends TestCase
{
    use BuildsCommercialScenario;
    use RefreshDatabase;

    /** @var array<int, Company> */
    private array $companies = [];

    private function owner(string $company = 'Empresa de contatos', string $root = '11222333'): User
    {
        $account = $this->account('Conta de contatos');
        $this->companies[$account->id] = $this->company($account, $company, $root);

        return $this->administrator($account);
    }

    private function customers(User $owner): string
    {
        return '/conta/empresa/'.$this->companies[$owner->account_id]->code.'/clientes';
    }

    /**
     * A Pessoa do próprio administrador vira cliente: os contatos centrais nunca alteram o login.
     */
    private function ownerAsCustomer(User $owner): string
    {
        $this->actingAs($owner)
            ->postJson($this->customers($owner), ['person_id' => $this->membership($owner)->person_id])
            ->assertNoContent();

        $relation = Customer::where('person_id', $this->membership($owner)->person_id)->sole();

        return $this->customers($owner).'/'.$relation->id;
    }

    public function test_additional_contacts_are_normalized_exposed_and_audited(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->postJson($this->customers($owner), [
            'name' => 'Contato com listas',
            'phone' => '7133334444',
            'email' => 'principal@example.test',
            'additional_phones' => ['(71) 99999-1234', '', '(11) 3333-4444'],
            'additional_emails' => [' Alternativo@Example.Test ', ''],
        ])->assertNoContent();

        $person = Person::where('name', 'Contato com listas')->sole();

        $this->assertSame(['71999991234', '1133334444'], $person->additional_phones);
        $this->assertSame(['alternativo@example.test'], $person->additional_emails);

        $this->getJson($this->customers($owner).'/'.$person->customers()->sole()->id.'/visualizar')
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
        $url = $this->ownerAsCustomer($owner);
        $base = ['phone' => '7133334444'];
        $payload = [
            ...$base,
            'additional_phones' => ['71999991234'],
            'additional_emails' => ['adicional@example.test'],
        ];

        $this->patchJson($url, $payload)->assertNoContent();

        $count = AuditRecord::count();

        $this->patchJson($url, $payload)->assertNoContent();

        $this->assertSame($count, AuditRecord::count());

        // Listas ausentes no envio mantêm os valores atuais.
        $this->patchJson($url, $base)->assertNoContent();

        $this->assertSame(['adicional@example.test'], $this->membership($owner)->person()->firstOrFail()->additional_emails);

        $this->patchJson($url, [...$base, 'additional_phones' => [''], 'additional_emails' => ['']])->assertNoContent();

        $person = $this->membership($owner)->person()->firstOrFail();

        $this->assertSame([], $person->additional_phones);
        $this->assertSame([], $person->additional_emails);
        $this->assertSame('7133334444', $person->phone);
        $this->assertSame($owner->email, $owner->fresh()->email);
    }

    public function test_invalid_lists_do_not_change_person_or_audit_and_other_accounts_are_isolated(): void
    {
        $owner = $this->owner();
        $other = $this->owner('Empresa da outra conta', '99888777');
        $otherUrl = $this->ownerAsCustomer($other);
        $url = $this->ownerAsCustomer($owner);
        $count = AuditRecord::count();
        $base = ['name' => 'Nome não deve mudar'];

        $this->patchJson($url, [...$base, 'additional_emails' => ['inválido']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('additional_emails.0');
        $this->patchJson($url, [...$base, 'additional_phones' => 'não é lista'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('additional_phones');
        $this->patchJson($url, [...$base, 'additional_emails' => array_fill(0, 21, 'teste@example.test')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('additional_emails');

        // A relação de outra conta não é encontrada pela empresa desta conta nem pela dela.
        $relation = Customer::where('account_id', $other->account_id)->sole();

        $this->patchJson($this->customers($owner).'/'.$relation->id, ['additional_phones' => ['71999991234']])
            ->assertNotFound();
        $this->patchJson($otherUrl, ['additional_phones' => ['71999991234']])->assertNotFound();

        $this->assertSame($count, AuditRecord::count());
        $this->assertSame($owner->name, $this->membership($owner)->person()->firstOrFail()->name);
        $this->assertSame([], $this->membership($owner)->person()->firstOrFail()->additional_emails);
        $this->assertSame([], $this->membership($other)->person()->firstOrFail()->additional_phones);
    }

    public function test_inspection_keeps_real_actor_for_additional_contacts_and_read_only_blocks_changes(): void
    {
        $owner = $this->owner();
        $url = $this->ownerAsCustomer($owner);
        $platform = User::factory()->create(['is_saas_admin' => true]);

        $this->actingAs($platform)
            ->postJson('/saas/contas/'.$owner->account_id.'/inspecao', ['user_id' => $owner->id, 'mode' => 'edit'])
            ->assertOk();

        $this->patchJson($url, ['additional_emails' => ['inspecao@example.test']])->assertNoContent();

        $event = AuditRecord::where('entity_type', 'person')
            ->where('entity_id', $this->membership($owner)->person_id)
            ->where('event', 'updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($platform->id, $event->actor_id);
        $this->assertSame($owner->id, $event->represented_user_id);
        $this->assertSame($owner->account_id, $event->account_id);

        $this->postJson('/conta/inspecao/encerrar')->assertOk();

        $this->actingAs($platform)
            ->postJson('/saas/contas/'.$owner->account_id.'/inspecao', ['user_id' => $owner->id, 'mode' => 'read-only'])
            ->assertOk();

        $count = AuditRecord::count();

        $this->patchJson($url, ['additional_emails' => []])->assertForbidden();

        $this->assertSame(['inspecao@example.test'], $this->membership($owner)->person()->firstOrFail()->additional_emails);
        $this->assertSame($count, AuditRecord::count());
    }

    public function test_empty_contact_lists_do_not_add_empty_changes_to_creation_history(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->postJson($this->customers($owner), [
            'name' => 'Sem contatos adicionais',
            'additional_phones' => [''],
            'additional_emails' => [''],
        ])->assertNoContent();

        $person = Person::where('name', 'Sem contatos adicionais')->sole();
        $event = AuditRecord::where('entity_type', 'person')->where('entity_id', $person->id)->sole();

        $this->assertArrayNotHasKey('additional_phones', $event->changes);
        $this->assertArrayNotHasKey('additional_emails', $event->changes);
    }

    public function test_people_editor_can_change_additional_contacts_without_user_management_permission(): void
    {
        $owner = $this->owner();
        $url = $this->ownerAsCustomer($owner);
        $role = Role::create(['account_id' => $owner->account_id, 'name' => 'Editor de contatos']);
        $role->permissions()->sync(Permission::whereIn('slug', [
            'account.customers.update',
            'account.people.view',
            'account.people.update',
        ])->pluck('id'));
        $editor = User::factory()->forAccount($owner->account_id, $role)->create();

        $this->actingAs($editor)->patchJson($url, ['additional_emails' => ['contato@example.test']])->assertNoContent();

        $this->assertSame($owner->email, $owner->fresh()->email);
        $this->assertSame(['contato@example.test'], $this->membership($owner)->person()->firstOrFail()->additional_emails);
    }
}
