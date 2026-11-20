<?php

namespace Tests\Feature;

use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SaasPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_accounts_have_no_access_to_the_platform(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard')->assertForbidden();
        $this->get('/crm-dashboard.html')->assertForbidden();
        $this->post('/saas/contas', ['name' => 'Cliente'])->assertForbidden();
        $this->assertDatabaseCount('clients', 0);
    }

    public function test_the_platform_provisions_accounts_and_the_owner_manages_the_establishment(): void
    {
        $this->actingAs(User::factory()->create(['is_saas_admin' => true]));

        $this->post('/saas/contas', [
            'name' => 'Cliente',
            'person_type' => 'company',
            'document' => '11.222.333/0001-81',
            'email' => 'cliente@example.test',
            'plan' => 'Essencial',
            'administrator' => [
                'name' => 'Admin da conta',
                'email' => 'owner@example.test',
                'mode' => 'manual',
                'password' => 'SenhaForte123!',
                'password_confirmation' => 'SenhaForte123!',
            ],
            'details' => [
                'trade_name' => 'Nome fantasia',
                'address' => ['postal_code' => '01001-000', 'city' => 'São Paulo', 'state' => 'SP'],
                'phone' => '(11) 99999-0000',
                'whatsapp' => '(11) 99999-1111',
                'additional_phones' => ['(11) 98888-1111', '(11) 98888-2222'],
                'additional_emails' => [' extra@example.test '],
            ],
        ])->assertRedirect();
        $client = Client::query()->sole();
        $path = "/saas/contas/{$client->id}";

        $this->patch($path, ['name' => 'Cliente editado'])->assertRedirect(route('dashboard'));
        $this->post("$path/estabelecimentos", ['name' => 'Loja'])->assertNotFound();
        $this->get($path)->assertRedirect("$path/editar");

        $this->getJson("$path/editar")->assertJsonPath('client.name', 'Cliente editado')
            ->assertJsonPath('client.document', '11222333000181')
            ->assertJsonPath('client.email', 'cliente@example.test')
            ->assertJsonPath('client.details.address.postal_code', '01001000')
            ->assertJsonPath('client.details.phone', '11999990000')
            ->assertJsonPath('client.details.whatsapp', '11999991111');

        $otherClient = Client::query()->create(['name' => 'Outro cliente', 'active' => true]);
        $otherPoint = $otherClient->points()->create(['name' => 'Outro ponto', 'active' => true]);
        $this->getJson("$path/visualizar")->assertOk()
            ->assertJsonPath('administrator.email', 'owner@example.test')
            ->assertJsonPath('details.additional_phones', ['11988881111', '11988882222'])
            ->assertJsonPath('details.additional_emails', ['extra@example.test']);
        $contacts = [
            'name' => 'Cliente editado',
            'person_type' => 'company',
            'document' => '11222333000181',
            'details' => [
                'phone' => '11999990000',
                'additional_phones' => ['11988882222'],
                'additional_emails' => [''],
            ],
        ];
        $this->patch($path, array_replace_recursive($contacts, ['details' => ['additional_emails' => ['inválido']]]))
            ->assertSessionHasErrors('details.additional_emails.0');
        $this->patch($path, $contacts)->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
        $this->getJson("$path/visualizar")->assertOk()
            ->assertJsonPath('details.additional_phones', ['11988882222'])
            ->assertJsonPath('details.additional_emails', [])
            ->assertJsonPath('details.address.postal_code', '01001000');
        $owner = $client->users()->sole();
        $this->assertTrue($owner->is_account_admin);
        $this->assertFalse($owner->is_saas_admin);
        $this->assertSame($owner->name, $owner->person()->sole()->name);
        $this->assertTrue(Hash::check('SenhaForte123!', $owner->password));
        $member = User::factory()->create(['account_id' => $client->id]);
        $this->actingAs($member)->patch('/conta', ['name' => 'Inválido'])->assertForbidden();
        $this->post('/conta/pontos', ['name' => 'Inválido', 'active' => true])->assertForbidden();
        $this->actingAs($owner)->get('/dashboard')->assertRedirect(route('account.dashboard'));
        $this->getJson('/conta/editar')->assertJsonPath('tenant', true)->assertJsonPath('client.id', $client->id);
        $this->patch('/conta', [
            'name' => 'Estabelecimento atualizado',
            'details' => ['trade_name' => 'Nova marca', 'address' => ['city' => 'Campinas']],
            'active' => false,
            'plan' => 'Alteração de plano indevida',
            'document' => '52998224725',
        ])->assertSessionHasNoErrors()->assertRedirect(route('account.dashboard'));
        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'active' => true,
            'plan' => 'Essencial',
            'document' => '11222333000181',
        ]);
        $this->assertSame('Nova marca', $client->refresh()->details['trade_name']);
        $this->assertSame('Campinas', $client->details['address']['city']);
        $this->assertSame('01001000', $client->details['address']['postal_code']);
        $this->assertSame('Outro cliente', $otherClient->refresh()->name);
        $this
            ->post('/conta/pontos', ['name' => 'Recepção', 'active' => '1'])
            ->assertRedirect(route('account.dashboard'));
        $point = $client->points()->sole();
        $this
            ->patch("/conta/pontos/{$point->id}", ['name' => 'Caixa', 'active' => '0'])
            ->assertRedirect(route('account.dashboard'));
        $this->patch("/conta/pontos/{$otherPoint->id}", ['name' => 'Inválido', 'active' => '1'])->assertNotFound();
        $this->getJson('/conta')->assertJsonPath('account.name', 'Estabelecimento atualizado')
            ->assertJsonPath('points.0.name', 'Caixa')
            ->assertJsonPath('points.0.active', false);
        $this->getJson("/saas/contas/{$otherClient->id}/visualizar")->assertForbidden();
    }

    public function test_an_administrator_can_be_invited_without_a_manual_password(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->create(['is_saas_admin' => true]));

        $this->post('/saas/contas', [
            'name' => 'Conta convidada',
            'person_type' => 'individual',
            'document' => '529.982.247-25',
            'details' => ['phone' => '11999990000'],
            'administrator' => ['name' => 'Convidado', 'email' => 'invite@example.test', 'mode' => 'invite'],
        ])->assertRedirect(route('dashboard'))->assertSessionHas('toast', [
            'type' => 'success',
            'message' => 'Conta criada. O link para definir a senha foi enviado ao administrador.',
        ]);

        $owner = Client::query()->sole()->users()->sole();
        Notification::assertSentTo($owner, ResetPassword::class);
        $this->assertSame('invite@example.test', $owner->person()->sole()->email);
    }

    public function test_missing_administrator_passwords_have_friendly_validation_messages(): void
    {
        $this->actingAs(User::factory()->create(['is_saas_admin' => true]));

        $this->post('/saas/contas', [
            'name' => 'Conta sem senha',
            'person_type' => 'company',
            'document' => '11.222.333/0001-81',
            'details' => ['phone' => '11999990000'],
            'administrator' => [
                'name' => 'Admin da conta',
                'email' => 'admin@example.test',
                'mode' => 'manual',
            ],
        ])->assertSessionHasErrors([
            'administrator.password' => 'Informe a senha inicial.',
            'administrator.password_confirmation' => 'Confirme a senha inicial.',
        ]);

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_user_creation_resolves_the_role_and_account_from_the_server_context(): void
    {
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $account = Client::query()->create(['name' => 'Conta A', 'active' => true]);
        $other = Client::query()->create(['name' => 'Conta B', 'active' => true]);
        $owner = User::factory()->create(['account_id' => $account->id, 'is_account_admin' => true]);
        $this->actingAs($platform)->post('/saas/usuarios', [
            'name' => 'Operador SaaS', 'email' => 'platform@example.test', 'mode' => 'manual',
            'password' => 'SenhaForte123!', 'password_confirmation' => 'SenhaForte123!',
            'role' => 'account-member', 'account_id' => $other->id,
        ])->assertRedirect(route('saas.users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'platform@example.test',
            'is_saas_admin' => true,
            'account_id' => null,
        ]);

        $this->actingAs($owner)->post('/saas/usuarios', [])->assertForbidden();
        $this->post('/conta/usuarios', [
            'name' => 'Colaborador', 'email' => 'member@example.test', 'mode' => 'manual',
            'password' => 'SenhaForte123!', 'password_confirmation' => 'SenhaForte123!',
            'role_id' => Role::where('account_id', $account->id)
                ->where('system_key', 'member')
                ->value('id'), 'account_id' => $other
                ->id, 'is_saas_admin' => true,
        ])->assertRedirect(route('account.users.index'));
        $member = User::query()->where('email', 'member@example.test')->sole();
        $this->assertSame($account->id, $member->account_id);
        $this->assertFalse($member->is_saas_admin);
        $this->assertFalse($member->is_account_admin);
        $this->assertSame('Colaborador', $member->person()->sole()->name);
        $this->getJson('/conta/usuarios')->assertJsonCount(2, 'users.data');
        $this->actingAs($member)->get('/conta/usuarios')->assertForbidden();
    }

    public function test_the_command_creates_a_verified_administrator_with_a_hashed_password(): void
    {
        $this->artisan('auth:create-user', [
            '--role' => 'platform-admin',
            '--name' => 'Administrador',
            '--email' => 'ADMIN@example.test',
        ])
            ->expectsQuestion('Senha (mínimo de 12 caracteres)', 'SenhaForte123!')
            ->expectsQuestion('Confirme a senha', 'SenhaForte123!')
            ->assertSuccessful();

        $user = User::query()->sole();
        $this->assertSame('admin@example.test', $user->email);
        $this->assertTrue($user->is_saas_admin);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check('SenhaForte123!', $user->password));
    }
}
