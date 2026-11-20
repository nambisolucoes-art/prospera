<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_page_is_displayed()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);
        Features::passkeys([
            'confirmPassword' => true,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->getJson(route('security.edit'))
            ->assertJsonPath('canManagePasskeys', true)
            ->assertJsonPath('passkeys', [])
            ->assertJsonPath('canManageTwoFactor', true)
            ->assertJsonPath('twoFactorEnabled', false);
    }

    public function test_security_page_requires_password_confirmation_when_enabled()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        $user = User::factory()->create();

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $response = $this->actingAs($user)
            ->get(route('security.edit'));

        $response->assertRedirect(route('password.confirm'));
    }

    public function test_security_page_renders_without_two_factor_when_feature_is_disabled()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        config(['fortify.features' => []]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->getJson(route('security.edit'))
            ->assertOk()
            ->assertJsonPath('canManagePasskeys', false)
            ->assertJsonPath('passkeys', [])
            ->assertJsonPath('canManageTwoFactor', false)
            ->assertJsonMissingPath('twoFactorEnabled')
            ->assertJsonMissingPath('requiresConfirmation');
    }

    public function test_two_factor_setup_validates_code_and_keeps_pending_configuration()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->from(route('security.edit'))
            ->post(route('two-factor.enable'))
            ->assertRedirect(route('security.edit'));

        $this->post(route('two-factor.confirm'), ['code' => ''])
            ->assertRedirect(route('security.edit'))
            ->assertSessionHasErrorsIn(
                'confirmTwoFactorAuthentication',
                ['code' => 'Informe o código do autenticador.'],
            );

        $this->post(route('two-factor.confirm'), ['code' => '123'])
            ->assertRedirect(route('security.edit'))
            ->assertSessionHasErrorsIn('confirmTwoFactorAuthentication', ['code' => 'Informe um código de 6 dígitos.']);

        $this->getJson(route('security.edit'))
            ->assertJsonPath('twoFactorPending', true)
            ->assertJsonPath('twoFactorEnabled', false);
        $this->getJson(route('two-factor.qr-code'))->assertOk()->assertJsonStructure(['svg']);

        $secret = $this->getJson(route('two-factor.secret-key'))->json('secretKey');
        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->post(route('two-factor.confirm'), ['code' => $code])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('security.edit'));

        $this->getJson(route('security.edit'))
            ->assertJsonPath('twoFactorPending', false)
            ->assertJsonPath('twoFactorEnabled', true);
    }

    public function test_password_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('security.edit'));

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect(route('security.edit'));
    }
}
