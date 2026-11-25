<?php

namespace Tests\Feature;

use App\Domains\Acessos\Application\Actions\RegisterAccountMembershipAction;
use App\Domains\Acessos\Domain\Models\AccountInvitation;
use App\Domains\Acessos\Domain\Models\AccountMembership;
use App\Domains\Acessos\Domain\Models\Permission;
use App\Domains\Acessos\Domain\Models\Role;
use App\Domains\Acessos\Domain\ValueObjects\AccessNode;
use App\Domains\Etiquetas\Domain\Models\Tag;
use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * E09: uma identidade global em várias contas, sem herdar permissões, dados ou auditoria de outra conta.
 * As URLs são completas (/contas/{conta}); o helper /conta só vale para identidades de uma conta.
 */
final class AccountIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_identity_alternates_between_accounts_without_sharing_permissions_data_or_authorship(): void
    {
        $first = Client::create(['name' => 'Conta administrada', 'active' => true]);
        $second = Client::create(['name' => 'Conta operada', 'active' => true]);
        $user = User::factory()->asAccountAdministrator($first->id)->create();
        $operator = $this->limitedRole($second, [
            'account.tags.list', 'account.tags.view', 'account.tags.create', 'self.help.list',
        ]);
        app(RegisterAccountMembershipAction::class)->execute($user, $operator, AccessNode::tenant($second->id));
        $a = '/contas/'.$first->code;
        $b = '/contas/'.$second->code;
        $this->actingAs($user);

        // Mesma sessão, contas alternadas: a única diferença entre as "abas" é a URL.
        for ($round = 1; $round <= 2; $round++) {
            $adminPermissions = $this->getJson($a)->assertOk()
                ->assertJsonPath('account_context.code', $first->code)
                ->json('auth.permissions');
            // Sem o Início, a conta B leva à primeira tela permitida, sempre dentro da própria conta.
            $this->getJson($b)->assertOk()->assertJsonPath('redirect', url($b.'/etiquetas'));
            $operatorPermissions = $this->getJson($b.'/etiquetas')->assertOk()
                ->assertJsonPath('account_context.code', $second->code)
                ->json('auth.permissions');

            self::assertContains('account.users.list', $adminPermissions);
            self::assertNotContains('account.users.list', $operatorPermissions);
            self::assertNotContains('account.account-data.update', $operatorPermissions);

            $this->getJson($a.'/usuarios')->assertOk();
            $this->getJson($b.'/usuarios')->assertForbidden();

            $this->postJson($a.'/etiquetas', ['name' => 'Etiqueta A'.$round, 'module' => 'customers', 'color' => '#2b7de9'])
                ->assertOk();
            $this->postJson($b.'/etiquetas', ['name' => 'Etiqueta B'.$round, 'module' => 'customers', 'color' => '#ad125a'])
                ->assertOk();

            $this->patchJson($b, ['name' => 'Alteração sem permissão'])->assertForbidden();
            $this->patchJson($a, ['name' => 'Conta administrada '.$round])->assertOk()
                ->assertJsonPath('redirect', url($a));
        }

        self::assertSame('Conta operada', $second->fresh()->name);
        self::assertSame('Conta administrada 2', $first->fresh()->name);

        $tagOfA = Tag::where('account_id', $first->id)->where('name', 'Etiqueta A1')->sole();
        $tagOfB = Tag::where('account_id', $second->id)->where('name', 'Etiqueta B1')->sole();

        // Um ID de outra conta responde 404, mesmo com a permissão de visualizar na conta da URL.
        $this->getJson($b.'/etiquetas/'.$tagOfA->id.'/visualizar')->assertNotFound();
        $this->getJson($b.'/etiquetas/'.$tagOfB->id.'/visualizar')->assertOk();
        $this->deleteJson($b.'/etiquetas/'.$tagOfB->id)->assertForbidden();
        $this->deleteJson($a.'/etiquetas/'.$tagOfB->id)->assertNotFound();

        $listed = $this->getJson($b.'/etiquetas?search=Etiqueta')->assertOk()->getContent();
        self::assertStringNotContainsString('Etiqueta A', (string) $listed);

        // Cada evento fica na conta da URL, com a identidade real como autora.
        $events = DB::table('audit_records')
            ->where('entity_type', Tag::auditType())
            ->where('actor_id', $user->id)
            ->get(['entity_id', 'account_id']);

        self::assertCount(4, $events);

        foreach ($events as $event) {
            $owner = Tag::whereKey($event->entity_id)->value('account_id');
            self::assertSame($owner, (int) $event->account_id);
        }

        $this->assertDatabaseMissing('audit_records', [
            'entity_type' => 'account',
            'entity_id' => $second->id,
            'actor_id' => $user->id,
        ]);
    }

    public function test_membership_and_account_states_open_only_usable_accounts_and_hide_others(): void
    {
        $active = Client::create(['name' => 'Ativa principal', 'active' => true]);
        $alsoActive = Client::create(['name' => 'Ativa secundária', 'active' => true]);
        $suspended = Client::create(['name' => 'Vínculo suspenso', 'active' => true]);
        $inactive = Client::create(['name' => 'Conta inativa', 'active' => true]);
        $foreign = Client::create(['name' => 'Conta alheia sigilosa', 'active' => true]);
        $user = User::factory()->asAccountAdministrator($active->id)->create();

        foreach ([$alsoActive, $suspended, $inactive] as $account) {
            $role = $this->limitedRole($account, ['self.help.list']);
            app(RegisterAccountMembershipAction::class)->execute($user, $role, AccessNode::tenant($account->id));
        }

        $this->membership($user, $suspended->id)->forceFill(['status' => 'suspended'])->saveOrFail();
        $inactive->forceFill(['active' => false])->saveOrFail();
        $this->actingAs($user);

        $accounts = collect($this->getJson('/contas')->assertOk()->json('accounts'))->keyBy('code');

        self::assertSame('active', $accounts[$active->code]['status']);
        self::assertSame('active', $accounts[$alsoActive->code]['status']);
        self::assertSame('suspended', $accounts[$suspended->code]['status']);
        self::assertSame('inactive', $accounts[$inactive->code]['status']);
        self::assertNull($accounts[$suspended->code]['url']);
        self::assertNull($accounts[$inactive->code]['url']);
        self::assertSame([], $accounts[$suspended->code]['roles']);
        self::assertFalse($accounts->has($foreign->code));

        $this->getJson('/contas/'.$suspended->code)->assertForbidden();
        $this->getJson('/contas/'.$inactive->code)->assertForbidden();
        $this->postJson('/contas/'.$suspended->code.'/etiquetas', ['name' => 'Indevida'])->assertForbidden();

        $unknown = $this->getJson('/contas/conta-inexistente')->assertNotFound();
        $other = $this->getJson('/contas/'.$foreign->code)->assertNotFound();
        self::assertSame($unknown->json('message'), $other->json('message'));
        self::assertStringNotContainsString('sigilosa', (string) $other->getContent());

        // Sem conta utilizável, a seleção não escolhe nada e não abre o ambiente de nenhuma conta.
        $this->membership($user, $active->id)->forceFill(['status' => 'suspended'])->saveOrFail();
        $this->membership($user, $alsoActive->id)->forceFill(['status' => 'suspended'])->saveOrFail();
        $response = $this->getJson('/contas')->assertOk();

        self::assertNull($response->json('redirect'));
        self::assertSame([], collect($response->json('accounts'))->whereNotNull('url')->all());
        $this->getJson('/conta/etiquetas')->assertOk()->assertJsonPath('redirect', route('accounts.select', [
            'destino' => 'etiquetas',
        ]));
    }

    public function test_inspection_is_bound_to_the_inspected_account_even_when_the_user_has_another(): void
    {
        $inspected = Client::create(['name' => 'Conta inspecionada', 'active' => true]);
        $other = Client::create(['name' => 'Outra conta do usuário', 'active' => true]);
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $owner = User::factory()->asAccountAdministrator($inspected->id)->create();
        $administrator = $this->limitedRole($other, ['account.account-data.update', 'self.help.list']);
        app(RegisterAccountMembershipAction::class)->execute($owner, $administrator, AccessNode::tenant($other->id));

        $this->actingAs($platform)
            ->post(route('saas.accounts.inspection.start', $inspected), ['user_id' => $owner->id, 'mode' => 'edit'])
            ->assertRedirect();

        $this->getJson('/contas/'.$inspected->code)->assertOk()->assertJsonPath('auth.user.id', $owner->id);
        $this->getJson('/contas')->assertJsonPath('redirect', url('/contas/'.$inspected->code));
        $this->getJson('/contas/'.$other->code)->assertNotFound();
        $this->patchJson('/contas/'.$other->code, ['name' => 'Alterada pela inspeção'])->assertNotFound();
        $this->getJson('/conta/etiquetas')->assertJsonPath('redirect', url('/contas/'.$inspected->code.'/etiquetas'));
        self::assertSame('Outra conta do usuário', $other->fresh()->name);

        $this->post(route('inspection.end'))->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($platform);
        $this->getJson('/contas/'.$inspected->code)->assertNotFound();

        // Fora da inspeção, a própria identidade continua com as duas contas independentes.
        $this->actingAs($owner);
        $this->getJson('/contas/'.$other->code)->assertOk()->assertJsonPath('redirect', url('/contas/'.$other->code.'/ajuda'));
        $this->patchJson('/contas/'.$other->code, ['name' => 'Alterada pelo vínculo'])->assertOk();
        self::assertSame('Alterada pelo vínculo', $other->fresh()->name);
        $this->assertDatabaseHas('audit_records', [
            'entity_type' => 'account',
            'entity_id' => $other->id,
            'account_id' => $other->id,
            'actor_id' => $owner->id,
        ]);
    }

    public function test_invitation_issue_is_neutral_stores_only_the_hash_and_replaces_the_pending_one(): void
    {
        config(['mail.default' => 'array']);
        $account = Client::create(['name' => 'Conta convidante', 'active' => true]);
        $elsewhere = Client::create(['name' => 'Conta de origem', 'active' => true]);
        $owner = User::factory()->asAccountAdministrator($account->id)->create();
        $existing = User::factory()->asAccountAdministrator($elsewhere->id)->create(['email' => 'Existente@E09.test']);
        $member = User::factory()->forAccount($account->id)->create();
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $role = $this->limitedRole($account, ['self.help.list']);
        $data = ['name' => 'Acesso', 'mode' => 'invite', 'role_id' => $role->id];
        $this->actingAs($owner);

        $toExisting = $this->postJson('/conta/usuarios', [...$data, 'email' => 'existente@e09.test'])->assertOk()->json();
        $toMember = $this->postJson('/conta/usuarios', [...$data, 'email' => $member->email])->assertOk()->json();
        $toPlatform = $this->postJson('/conta/usuarios', [...$data, 'email' => $platform->email])->assertOk()->json();

        self::assertSame($toExisting, $toMember);
        self::assertSame($toExisting, $toPlatform);
        self::assertSame(1, AccountInvitation::count());
        $this->assertDatabaseMissing('account_memberships', ['user_id' => $existing->id, 'account_id' => $account->id]);

        $first = AccountInvitation::sole();
        $firstToken = $this->tokenFromLastMail('existente@e09.test');

        self::assertSame($existing->id, $first->recipient_id);
        self::assertSame('existente@e09.test', $first->email);
        self::assertSame(hash('sha256', $firstToken), $first->token_hash);
        self::assertCount(1, $this->sentMessages());
        $this->assertTokenNeverPersisted($firstToken);
        $this->assertDatabaseHas('audit_records', [
            'entity_type' => 'account-invitation',
            'entity_id' => $first->id,
            'account_id' => $account->id,
            'event' => 'invitation-sent',
        ]);

        // O pendente não concede acesso; um novo envio revoga o anterior na mesma transação.
        $this->actingAs($existing)->getJson('/contas/'.$account->code)->assertNotFound();
        $this->actingAs($owner)->postJson('/conta/usuarios', [...$data, 'email' => 'existente@e09.test'])->assertOk();
        $secondToken = $this->tokenFromLastMail('existente@e09.test');

        self::assertSame('revoked', $first->fresh()->status);
        self::assertSame(1, AccountInvitation::where('status', 'pending')->count());
        $this->assertTokenNeverPersisted($secondToken);

        $this->actingAs($existing)->postJson('/convites/'.$firstToken)->assertStatus(410);
        $this->assertDatabaseMissing('account_memberships', ['user_id' => $existing->id, 'account_id' => $account->id]);
        $this->postJson('/convites/'.$secondToken)->assertOk()->assertJsonPath('redirect', url('/contas/'.$account->code));
        // Cargo só com a Ajuda: a entrada leva à Central de Ajuda da conta, não a um 403 vazio.
        $this->getJson('/contas/'.$account->code)->assertOk()->assertJsonPath('redirect', url('/contas/'.$account->code.'/ajuda'));
        $this->getJson('/contas/'.$account->code.'/ajuda')->assertOk();
        $this->getJson('/contas/'.$elsewhere->code)->assertOk();

        // Com a liberação desligada, a resposta é a mesma e nada é emitido nem enviado.
        config(['prospera.multiple_accounts' => false]);
        $other = User::factory()->asAccountAdministrator($elsewhere->id)->create();
        $sent = count($this->sentMessages());
        $disabled = $this->actingAs($owner)->postJson('/conta/usuarios', [...$data, 'email' => $other->email])
            ->assertOk()
            ->json();

        self::assertSame($toExisting, $disabled);
        self::assertSame(0, AccountInvitation::where('recipient_id', $other->id)->count());
        self::assertCount($sent, $this->sentMessages());
    }

    public function test_invitation_revocation_is_scoped_to_the_url_account_and_blocks_acceptance(): void
    {
        $account = Client::create(['name' => 'Conta do convite', 'active' => true]);
        $intruder = Client::create(['name' => 'Conta intrusa', 'active' => true]);
        $owner = User::factory()->asAccountAdministrator($account->id)->create();
        $intruderOwner = User::factory()->asAccountAdministrator($intruder->id)->create();
        $recipient = User::factory()->create();
        $role = $this->limitedRole($account, ['self.help.list']);
        $token = str_repeat('r', 64);
        $invitation = AccountInvitation::issue(
            $account->id, $recipient, $role, AccessNode::tenant($account->id), null, $owner->id, $token, now()->addDay(),
        );
        $invitation->saveOrFail();
        $path = '/usuarios/convites/'.$invitation->id.'/revogar';

        $this->actingAs($intruderOwner)->patchJson('/contas/'.$intruder->code.$path)->assertNotFound();
        $this->patchJson('/contas/'.$account->code.$path)->assertNotFound();
        self::assertSame('pending', $invitation->fresh()->status);

        $this->actingAs($owner)->patchJson('/contas/'.$account->code.$path)->assertOk();
        self::assertSame('revoked', $invitation->fresh()->status);
        $this->patchJson('/contas/'.$account->code.$path)->assertUnprocessable();
        $this->assertDatabaseHas('audit_records', [
            'entity_type' => 'account-invitation',
            'entity_id' => $invitation->id,
            'account_id' => $account->id,
            'actor_id' => $owner->id,
        ]);

        $this->actingAs($recipient)->getJson('/convites/'.$token)->assertOk()->assertJsonPath('invitation', null);
        $this->postJson('/convites/'.$token)->assertStatus(410);
        self::assertFalse(AccountMembership::where('user_id', $recipient->id)->exists());
    }

    public function test_invitation_rate_limit_answers_in_portuguese_for_json_and_direct_opening(): void
    {
        $recipient = User::factory()->create();
        $path = '/convites/'.str_repeat('t', 64);
        $this->actingAs($recipient);

        for ($view = 0; $view < 20; $view++) {
            $this->getJson($path)->assertOk()->assertJsonPath('invitation', null);
        }

        $this->getJson($path)
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertExactJson(['message' => 'Muitas tentativas em pouco tempo. Aguarde um minuto e tente novamente.']);

        $html = $this->get($path)->assertTooManyRequests()->assertHeader('Retry-After')->assertViewIs('app');
        self::assertStringNotContainsString('Too Many', (string) $html->getContent());
    }

    /** @param list<string> $slugs */
    private function limitedRole(Client $account, array $slugs): Role
    {
        $role = Role::create(['account_id' => $account->id, 'name' => 'Cargo '.$account->id.' '.count($slugs)]);
        $role->permissions()->sync(Permission::whereIn('slug', $slugs)->pluck('id'));

        return $role;
    }

    /** @return list<Email> */
    private function sentMessages(): array
    {
        $messages = [];

        foreach (app('mailer')->getSymfonyTransport()->messages() as $sent) {
            $messages[] = $sent->getOriginalMessage();
        }

        return $messages;
    }

    private function tokenFromLastMail(string $email): string
    {
        $messages = $this->sentMessages();
        $last = end($messages);

        self::assertInstanceOf(Email::class, $last);
        self::assertSame($email, $last->getTo()[0]->getAddress());
        self::assertSame(1, preg_match('#/convites/([A-Za-z0-9]{64})#', (string) $last->getTextBody(), $match));

        return $match[1];
    }

    private function assertTokenNeverPersisted(string $token): void
    {
        foreach (['account_invitations', 'audit_records'] as $table) {
            foreach (DB::table($table)->get() as $row) {
                self::assertStringNotContainsString($token, (string) json_encode($row));
            }
        }
    }
}
