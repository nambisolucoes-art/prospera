<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class TwoFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
    }

    public function test_two_factor_challenge_redirects_to_login_when_not_authenticated(): void
    {
        $response = $this->get(route('two-factor.login'));

        $response->assertRedirect(route('login'));
    }

    public function test_two_factor_challenge_can_be_rendered(): void
    {
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->get(route('two-factor.login'))
            ->assertOk()
            ->assertViewIs('app');
    }

    public function test_login_api_requires_the_two_factor_challenge_before_authenticating(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->postJson(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->assertJsonPath('redirect', route('two-factor.login'));
        $this->assertGuest();
        $this->getJson(route('two-factor.login'))->assertOk()->assertJsonPath('auth.user', null);
    }
}
