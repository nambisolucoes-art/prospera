<?php

namespace Tests\Feature;

use App\Domains\Plataforma\Domain\Models\Client;
use App\Domains\Plataforma\Infrastructure\Session\AccountInspectionSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountInspectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_platform_can_select_eligible_users_from_the_account(): void
    {
        $account = Client::query()->create(['name' => 'Conta ativa', 'active' => true]);
        $inactive = Client::query()->create(['name' => 'Conta inativa', 'active' => false]);
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $member = User::factory()->forAccount($account->id)->create(['profile_photo' => (string) Str::uuid()]);
        User::factory()->unverified()->forAccount($account->id)->create();
        $outsider = User::factory()->forAccount($inactive->id)->create();

        $options = route('saas.accounts.inspection.options', $account);
        $start = route('saas.accounts.inspection.start', $account);
        $photo = fn (Client $client) => route('saas.accounts.inspection.photo', [
            'client' => $client, 'userId' => $member->id, 'version' => $member->profile_photo,
        ], absolute: false);
        Storage::fake('local')->put(User::profilePhotoPath($member->id, $member->profile_photo), 'photo');

        $this->actingAs($member)->getJson($options)->assertForbidden();
        $this->get($photo($account))->assertForbidden();
        $this->post($start, ['user_id' => $member->id, 'mode' => 'edit'])->assertForbidden();
        $this->actingAs($platform)->getJson($options)->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.id', $member->id)->assertJsonPath('0.photo_url', $photo($account));
        $this->get($photo($account))->assertOk()->assertHeader('Content-Type', 'image/webp');
        $otherActive = Client::query()->create(['name' => 'Outra conta', 'active' => true]);
        $this->get($photo($otherActive))->assertNotFound();
        $this->getJson(route('saas.accounts.inspection.options', $inactive))->assertNotFound();
        $this->postJson($start, ['user_id' => $outsider->id, 'mode' => 'edit'])
            ->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->postJson($start, ['user_id' => $member->id, 'mode' => 'unsupported'])
            ->assertUnprocessable()->assertJsonValidationErrors('mode');
        $this->assertAuthenticatedAs($platform);
    }

    public function test_read_only_inspection_shows_the_account_and_restores_the_administrator(): void
    {
        $account = Client::query()->create(['name' => 'Conta ativa', 'active' => true]);
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $owner = User::factory()->asAccountAdministrator($account->id)->create();

        $this->actingAs($platform)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('saas.accounts.inspection.start', $account), ['user_id' => $owner->id, 'mode' => 'read-only'])
            ->assertRedirect(route('account.dashboard'))->assertSessionMissing('auth.password_confirmed_at');
        $this->assertAuthenticatedAs($owner);
        $this
            ->getJson(route('account.dashboard'))
            ->assertJsonPath('auth.inspection_mode', 'read-only')
            ->assertJsonPath('auth.user.id', $owner->id)
            ->assertJsonPath('account.name', 'Conta ativa');
        $this->getJson(route('two-factor.secret-key'))->assertForbidden();
        $this->patch(route('account.update'), ['name' => 'Conta renomeada'])->assertForbidden();
        $this->assertDatabaseMissing('clients', ['name' => 'Conta renomeada']);
        $this->get(route('saas.users.index'))->assertForbidden();

        $this
            ->post(route('account.inspection.end'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionMissing('account_inspection');
        $this->assertAuthenticatedAs($platform);
        $this->post(route('account.inspection.end'))->assertForbidden();
    }

    public function test_edit_inspection_uses_target_permissions_and_protects_personal_settings(): void
    {
        $account = Client::query()->create(['name' => 'Conta ativa', 'active' => true]);
        $platform = User::factory()->create(['is_saas_admin' => true]);
        $owner = User::factory()->asAccountAdministrator($account->id)->create();
        $member = User::factory()->forAccount($account->id)->create();
        $start = route('saas.accounts.inspection.start', $account);
        $end = route('account.inspection.end');

        $this->actingAs($platform)->post($start, ['user_id' => $member->id, 'mode' => 'edit'])->assertRedirect();
        $this->patch(route('account.update'), ['name' => 'Renomeada pelo membro'])->assertForbidden();
        $this->post($end)->assertRedirect(route('dashboard'));

        $this->post($start, ['user_id' => $owner->id, 'mode' => 'edit'])->assertRedirect();
        $this->patch(route('account.update'), ['name' => 'Renomeada pelo administrador'])
            ->assertRedirect(route('account.dashboard'));
        $this->assertSame('Renomeada pelo administrador', $account->refresh()->name);
        $this->patch(route('profile.update'), ['name' => 'Alterado', 'email' => $owner->email])->assertForbidden();

        $platform->forceFill(['is_saas_admin' => false])->saveOrFail();
        $this->post($end)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_active_inspection_ends_on_the_next_request_when_saas_authority_is_revoked(): void
    {
        $account = Client::create(['name' => 'Conta ativa', 'active' => true]);
        $owner = User::factory()->asAccountAdministrator($account->id)->create();
        $start = route('saas.accounts.inspection.start', $account);

        foreach (['is_saas_admin' => false, 'access_enabled' => false, 'email_verified_at' => null] as $field => $value) {
            $platform = User::factory()->create(['is_saas_admin' => true]);

            $this->actingAs($platform)->post($start, ['user_id' => $owner->id, 'mode' => 'edit'])->assertRedirect();
            $this->getJson(route('account.dashboard'))->assertOk()->assertJsonPath('auth.user.id', $owner->id);

            $session = $this->app['session.store'];
            $guardKey = Auth::guard('web')->getName();
            $inspectionSessionId = $session->getId();
            $inspectionToken = $session->token();
            $this->assertSame($owner->id, $session->get($guardKey));
            $this->assertSame($platform->id, AccountInspectionSession::current($session)?->administratorId);

            $platform->forceFill([$field => $value])->saveOrFail();

            $this->getJson(route('account.dashboard'))->assertUnauthorized()
                ->assertSessionMissing($guardKey)
                ->assertSessionMissing('account_inspection');
            $this->assertGuest();
            $this->assertNull(AccountInspectionSession::current($session));
            $this->assertNotSame($inspectionSessionId, $session->getId());
            $this->assertNotSame($inspectionToken, $session->token());

            $this->getJson(route('account.dashboard'))->assertUnauthorized();
            $this->getJson(route('account.establishments.index'))->assertUnauthorized();
            $this->patchJson(route('account.update'), ['name' => 'Alteração pela sessão encerrada'])->assertUnauthorized();
            $this->postJson(route('account.inspection.end'))->assertUnauthorized();
            $this->assertGuest();
        }

        $this->assertTrue($owner->fresh()->access_enabled);
        $this->assertSame('Conta ativa', $account->fresh()->name);
    }
}
