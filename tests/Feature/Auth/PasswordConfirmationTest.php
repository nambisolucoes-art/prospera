<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_password_screen_can_be_rendered()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('password.confirm'));

        $response->assertOk();

        $response->assertViewIs('app');
    }

    public function test_password_confirmation_requires_authentication()
    {
        $response = $this->get(route('password.confirm'));

        $response->assertRedirect(route('login'));
    }

    public function test_security_api_resumes_the_intended_destination_after_password_confirmation(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('security.edit'))
            ->assertOk()->assertJsonPath('redirect', route('password.confirm'));
        $this->getJson(route('password.confirm'))->assertOk()->assertJsonPath('forSecurity', true);
        $this->postJson(route('password.confirm.store'), ['password' => 'password'])
            ->assertOk()->assertJsonPath('redirect', route('security.edit'));
        $this->getJson(route('security.edit'))->assertOk()->assertJsonPath('passkeys', []);
    }
}
