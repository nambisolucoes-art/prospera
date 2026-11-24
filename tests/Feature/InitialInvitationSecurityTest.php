<?php

namespace Tests\Feature;

use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

final class InitialInvitationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_setup_blocks_access_and_reset_never_reactivates_suspended_membership(): void
    {
        $account = Client::create(['name' => 'Convite inicial', 'active' => true]);
        $user = User::factory()->asAccountAdministrator($account->id)->create();
        $user->forceFill(['password_setup_pending' => true])->saveOrFail();
        $this->actingAs($user)->getJson('/contas/'.$account->code)->assertForbidden();
        $this->getJson('/contas/conta-alheia')->assertNotFound();
        $this->app['auth']->guard()->logout();
        $token = Password::createToken($user);
        $this->membership($user)->forceFill(['status' => 'suspended'])->saveOrFail();
        $this->postJson('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Secure-password-123!',
            'password_confirmation' => 'Secure-password-123!',
        ])->assertSuccessful();
        self::assertFalse($user->fresh()->password_setup_pending);
        self::assertSame('suspended', $this->membership($user)->status);
        $this->actingAs($user->fresh())->getJson('/contas/'.$account->code)->assertUnauthorized();
        $this->flushSession();
        $this->actingAs($user->fresh())->getJson('/contas/'.$account->code)->assertForbidden();
        self::assertFalse(Password::tokenExists($user, $token));
    }

    public function test_expired_or_wrong_recipient_reset_does_not_complete_initial_setup(): void
    {
        $account = Client::create(['name' => 'Validade', 'active' => true]);
        $user = User::factory()->asAccountAdministrator($account->id)->create();
        $other = User::factory()->create();
        $user->forceFill(['password_setup_pending' => true])->saveOrFail();
        $token = Password::createToken($user);
        $this->postJson('/reset-password', [
            'token' => $token,
            'email' => $other->email,
            'password' => 'Secure-password-123!',
            'password_confirmation' => 'Secure-password-123!',
        ]);
        self::assertTrue($user->fresh()->password_setup_pending);
        $this->travel(61)->minutes();
        $this->postJson('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Secure-password-123!',
            'password_confirmation' => 'Secure-password-123!',
        ]);
        self::assertTrue($user->fresh()->password_setup_pending);
        $this->actingAs($user->fresh())->getJson('/contas/'.$account->code)->assertForbidden();
    }

    public function test_pending_or_unverified_identity_does_not_replace_the_last_usable_administrator(): void
    {
        $account = Client::create(['name' => 'Administração utilizável', 'active' => true]);
        $owner = User::factory()->asAccountAdministrator($account->id)->create();
        $pending = User::factory()->asAccountAdministrator($account->id)->create();
        $pending->forceFill(['password_setup_pending' => true])->saveOrFail();
        $membership = $this->membership($owner);
        $base = '/contas/'.$account->code.'/usuarios/';
        $this->actingAs($owner)->patchJson($base.$membership->id.'/suspender')->assertUnprocessable();
        self::assertSame('active', $membership->fresh()->status);
        $pending->forceFill(['password_setup_pending' => false, 'email_verified_at' => null])->saveOrFail();
        $this->patchJson($base.$membership->id.'/suspender')->assertUnprocessable();
        self::assertSame('active', $membership->fresh()->status);
        $this->patchJson($base.$this->membership($pending)->id.'/suspender')->assertOk();
        self::assertSame('suspended', $this->membership($pending)->status);
        self::assertFalse($pending->fresh()->password_setup_pending);
        self::assertNull($pending->fresh()->email_verified_at);
    }
}
