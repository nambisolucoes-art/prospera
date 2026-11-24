<?php

namespace Tests\Feature;

use App\Domains\Acessos\Application\Actions\DefaultRolesAction;
use App\Domains\Acessos\Application\Actions\RegisterAccountMembershipAction;
use App\Domains\Acessos\Application\Context\EffectivePermissions;
use App\Domains\Acessos\Domain\DTOs\AccessNode;
use App\Domains\Acessos\Domain\Models\AccountInvitation;
use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Organizacao\Application\Actions\CreateCompanyAction;
use App\Domains\Organizacao\Application\Data\SaveCompanyData;
use App\Domains\Plataforma\Application\Queries\AccountMembershipsQuery;
use App\Domains\Plataforma\Domain\DTOs\AccountCode;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Usuarios\Application\Actions\CreateUserAction;
use App\Domains\Usuarios\Application\Data\CreateUserData;
use App\Domains\Usuarios\Domain\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class MultipleAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_memberships_remain_selectable_with_release_disabled(): void
    {
        $first = Client::create(['name' => 'Primeira', 'active' => true]);
        $second = Client::create(['name' => 'Segunda', 'active' => true]);
        $other = Client::create(['name' => 'Outra', 'active' => true]);
        $user = User::factory()->asAccountAdministrator($first->id)->create();
        [$role] = app(DefaultRolesAction::class)->execute($second->id);
        app(RegisterAccountMembershipAction::class)->execute($user, $role, AccessNode::tenant($second->id));
        config(['prospera.multiple_accounts' => false]);

        $this->actingAs($user)->getJson('/contas')->assertOk()->assertJsonCount(2, 'accounts');
        $this->getJson('/contas/'.$first->code)->assertOk();
        $this->getJson('/contas/'.$second->code)->assertOk();
        $this->getJson('/contas/'.$other->code)->assertNotFound();
        $this->patchJson('/conta', ['name' => 'Alteração indevida'])->assertStatus(405);
        $this->assertDatabaseHas('clients', ['id' => $first->id, 'name' => 'Primeira']);
        $migration = require database_path('migrations/2026_11_23_000003_remove_account_from_users.php');

        try {
            $migration->down();
            self::fail('O rollback não pode escolher uma conta arbitrária.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('1 identidade(s)', $exception->getMessage());
        }

        self::assertFalse(Schema::hasColumn('users', 'account_id'));
    }

    public function test_pending_invitation_requires_recipient_and_is_single_use(): void
    {
        $account = Client::create(['name' => 'Convidante', 'active' => true]);
        [$role] = app(DefaultRolesAction::class)->execute($account->id);
        $recipient = User::factory()->create();
        $stranger = User::factory()->create();
        $token = str_repeat('a', 64);
        $invitation = AccountInvitation::issue(
            accountId: $account->id,
            recipient: $recipient,
            role: $role,
            node: AccessNode::tenant($account->id),
            personId: null,
            invitedBy: null,
            token: $token,
            expiresAt: now()->addDay(),
        );
        $invitation->saveOrFail();

        $this->actingAs($recipient)->getJson('/contas/'.$account->code)->assertNotFound();
        $this->actingAs($stranger)->postJson('/convites/'.$token)->assertStatus(410);
        $this->assertDatabaseMissing('account_memberships', ['user_id' => $recipient->id]);
        $recipient->forceFill(['email_verified_at' => null])->saveOrFail();
        $this->actingAs($recipient)->postJson('/convites/'.$token)->assertForbidden();
        $this->assertDatabaseMissing('account_memberships', ['user_id' => $recipient->id]);
        $recipient->forceFill(['email_verified_at' => now()])->saveOrFail();
        $this->actingAs($recipient);

        for ($view = 0; $view < 20; $view++) {
            $this->getJson('/convites/'.$token)->assertOk();
        }

        $this->getJson('/convites/'.$token)->assertStatus(429);
        $this->postJson('/convites/'.$token)->assertOk();
        $this->assertDatabaseHas('account_memberships', [
            'user_id' => $recipient->id,
            'account_id' => $account->id,
            'status' => 'active',
        ]);
        $this->postJson('/convites/'.$token)->assertStatus(410);
        $this->assertDatabaseCount('account_memberships', 1);
    }

    public function test_expired_revoked_and_disabled_invitations_never_grant_access(): void
    {
        $account = Client::create(['name' => 'Convidante', 'active' => true]);
        [$role] = app(DefaultRolesAction::class)->execute($account->id);
        $recipient = User::factory()->create();
        $this->actingAs($recipient);

        foreach (['expired', 'revoked', 'disabled'] as $index => $state) {
            $token = str_repeat((string) ($index + 1), 64);
            $invitation = AccountInvitation::issue(
                accountId: $account->id,
                recipient: $recipient,
                role: $role,
                node: AccessNode::tenant($account->id),
                personId: null,
                invitedBy: null,
                token: $token,
                expiresAt: $state === 'expired' ? now()->subMinute() : now()->addDay(),
            );

            if ($state === 'revoked') {
                $invitation->markRevoked();
            }

            $invitation->saveOrFail();
            config(['prospera.multiple_accounts' => $state !== 'disabled']);
            $this->postJson('/convites/'.$token)->assertStatus($state === 'disabled' ? 403 : 410);
            $this->assertDatabaseMissing('account_memberships', ['user_id' => $recipient->id]);
            $invitation->markRevoked();
            $invitation->saveOrFail();
        }
    }

    public function test_selection_has_one_query_and_never_depends_on_release_flag(): void
    {
        foreach ([1, 2, 5] as $count) {
            $user = User::factory()->create();
            $accounts = [];

            for ($number = 0; $number < $count; $number++) {
                $account = Client::create(['name' => 'Matriz '.$count.' '.$number, 'active' => true]);
                [$role] = app(DefaultRolesAction::class)->execute($account->id);
                app(RegisterAccountMembershipAction::class)->execute($user, $role, AccessNode::tenant($account->id));
                $accounts[] = $account;
            }

            DB::enableQueryLog();
            $rows = app(AccountMembershipsQuery::class)
                ->forIdentity($user->id);
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            DB::flushQueryLog();
            self::assertCount(1, $queries);
            self::assertCount($count, $rows);

            foreach ([true, false] as $enabled) {
                config(['prospera.multiple_accounts' => $enabled]);
                $this->actingAs($user);
                $response = $this->getJson('/contas')->assertOk();

                if ($count === 1) {
                    $response->assertJsonPath('redirect', url('/contas/'.$accounts[0]->code));
                } else {
                    $response->assertJsonCount($count, 'accounts');
                }

                foreach ($accounts as $account) {
                    $this->getJson('/contas/'.$account->code)->assertOk()
                        ->assertJsonPath('account_context.code', $account->code);
                }
            }
        }
    }

    public function test_account_code_keeps_format_with_many_duplicate_long_names(): void
    {
        $base = str_repeat('a', 36);
        $taken = [$base];

        for ($suffix = 2; $suffix < 1000; $suffix++) {
            $taken[] = $base.'-'.$suffix;
        }

        $code = AccountCode::firstAvailable($base, $taken);
        self::assertSame(40, strlen($code));
        self::assertStringEndsWith('-1000', $code);
        self::assertTrue(AccountCode::isValid($code));
        self::assertNotContains($code, $taken);
        self::assertSame('arvore-acao', AccountCode::suggestFrom('Árvore & Ação'));
        self::assertFalse(AccountCode::isValid('todos'));
        self::assertFalse(AccountCode::isValid('Acesso'));
    }

    public function test_invitation_cannot_grant_an_assignment_to_a_company_inactivated_after_issue(): void
    {
        $account = Client::create(['name' => 'Conta convite local', 'active' => true]);
        $company = app(CreateCompanyAction::class)->execute(
            $account->id,
            SaveCompanyData::fromInput([
                'name' => 'Empresa convite',
                'person_type' => 'company',
                'document' => '11000001',
                'primary' => ['classification' => 'unregistered'],
            ], $account->id, null),
        );
        $role = Role::create([
            'account_id' => $account->id, 'name' => 'Cargo local',
        ]);
        $recipient = User::factory()->create();
        $token = str_repeat('z', 64);
        $invitation = AccountInvitation::issue(
            $account->id, $recipient, $role, AccessNode::company($account->id, $company->id),
            null, null, $token, now()->addDay(),
        );
        $invitation->saveOrFail();
        $company->forceFill(['active' => false])->saveOrFail();
        $this->actingAs($recipient)->postJson('/convites/'.$token)->assertStatus(410);
        $this->assertDatabaseMissing('account_memberships', ['user_id' => $recipient->id]);
        self::assertSame('pending', $invitation->fresh()->status);
    }

    public function test_legacy_routes_redirect_only_safe_requests_without_ambiguous_tenant(): void
    {
        $first = Client::create(['name' => 'Compatibilidade segura', 'active' => true]);
        $user = User::factory()->asAccountAdministrator($first->id)->create();
        $this->actingAs($user)->getJson(url('/conta/etiquetas'))->assertOk()
            ->assertJsonPath('redirect', url('/contas/'.$first->code.'/etiquetas'));
        $this->head(url('/conta/etiquetas'))->assertRedirect('/contas/'.$first->code.'/etiquetas');
        $this->postJson(url('/conta/etiquetas'), [])->assertStatus(405);
        $second = Client::create(['name' => 'Segunda compatibilidade', 'active' => true]);
        [$role] = app(DefaultRolesAction::class)->execute($second->id);
        app(RegisterAccountMembershipAction::class)->execute($user, $role, AccessNode::tenant($second->id));
        $this->getJson(url('/conta/etiquetas'))->assertOk()
            ->assertJsonPath('redirect', route('accounts.select', ['destino' => 'etiquetas']));
        $this->postJson(url('/conta/etiquetas'), [])->assertStatus(405);
    }

    public function test_new_identity_grant_reauthorizes_after_a_cached_permission_is_revoked(): void
    {
        $account = Client::create(['name' => 'Revalidação da concessão', 'active' => true]);
        $role = Role::create([
            'account_id' => $account->id, 'name' => 'Operador de acesso',
        ]);
        $role->permissions()->sync(Permission::whereIn('slug', [
            'account.users.create', 'account.users.assign-role', 'self.help.list',
        ])->pluck('id'));
        $target = Role::create([
            'account_id' => $account->id, 'name' => 'Destino da concessão',
        ]);
        $target->permissions()->sync(Permission::where('slug', 'self.help.list')->pluck('id'));
        $operator = User::factory()->forAccount($account->id, $role)->create();
        $this->actingAs($operator);
        self::assertTrue($this->enterAccount($operator));
        $permissions = app(EffectivePermissions::class);
        self::assertTrue($permissions->allows($operator, 'account.users.create', AccessNode::tenant($account->id)));
        $role->permissions()->detach(Permission::where('slug', 'account.users.create')->value('id'));
        self::assertTrue($permissions->allows($operator, 'account.users.create', AccessNode::tenant($account->id)));

        try {
            app(CreateUserAction::class)->execute(
                CreateUserData::fromInput([
                    'name' => 'Concessão impedida', 'email' => 'denied-grant@e09.test', 'mode' => 'invite',
                ]),
                UserRole::AccountMember,
                $account,
                roleId: $target->id,
                node: AccessNode::tenant($account->id),
            );
            self::fail('A concessão precisa observar a permissão revogada antes da escrita.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('users', ['email' => 'denied-grant@e09.test']);
        }
    }

    public function test_reassigned_email_does_not_transfer_the_invitation_to_another_identity(): void
    {
        $account = Client::create(['name' => 'Destinatário imutável', 'active' => true]);
        [$role] = app(DefaultRolesAction::class)->execute($account->id);
        $recipient = User::factory()->create(['email' => 'original@e09.test']);
        $token = str_repeat('d', 64);
        $invitation = AccountInvitation::issue(
            $account->id, $recipient, $role, AccessNode::tenant($account->id), null, null, $token, now()->addDay(),
        );
        $invitation->saveOrFail();
        $recipient->forceFill(['email' => 'changed@e09.test', 'email_verified_at' => now()])->saveOrFail();
        $other = User::factory()->create(['email' => 'original@e09.test']);
        $this->actingAs($other)->getJson('/convites/'.$token)->assertOk()->assertJsonPath('invitation', null);
        $this->postJson('/convites/'.$token)->assertStatus(410);
        $this->assertDatabaseMissing('account_memberships', ['user_id' => $other->id]);
        self::assertSame($recipient->id, $invitation->fresh()->recipient_id);
        self::assertSame('pending', $invitation->fresh()->status);
    }
}
