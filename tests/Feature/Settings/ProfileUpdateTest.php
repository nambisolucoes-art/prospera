<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $email = $user->email;
        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame($email, $user->email);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_email_change_requires_confirmation_and_revokes_other_sessions(): void
    {
        $user = User::factory()->create();
        $oldEmail = $user->email;

        DB::table('sessions')->insert([
            'id' => 'other-session',
            'user_id' => $user->id,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($user)->postJson(route('profile.email.request'), [
            'email' => 'Test@Example.com',
            'current_password' => 'password',
        ])->assertOk();
        $this->assertSame($oldEmail, $user->fresh()->email);
        $this->assertDatabaseHas('sessions', ['id' => 'other-session']);

        $transport = Mail::mailer('array')->getSymfonyTransport();
        $messages = $transport->messages();
        $this->assertCount(1, $messages);
        $message = $messages->first()->getOriginalMessage();
        $this->assertSame('test@example.com', $message->getTo()[0]->getAddress());
        $link = str($message->getTextBody())->after('neste link: ')->trim()->toString();

        $other = User::factory()->create();
        $this->actingAs($other)->get($link)->assertForbidden();
        $this->assertSame($oldEmail, $user->fresh()->email);

        $this->actingAs($user)->get($link)->assertRedirect(route('profile.edit'));
        $this->assertSame('test@example.com', $user->fresh()->email);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);
        $this->assertSame($oldEmail, $transport->messages()->last()->getOriginalMessage()->getTo()[0]->getAddress());
    }

    public function test_existing_email_has_same_request_response_and_is_not_contacted(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        $requested = $this->actingAs($user)->postJson(route('profile.email.request'), [
            'email' => 'Taken@Example.com',
            'current_password' => 'password',
        ])->assertOk();
        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());

        $available = $this->postJson(route('profile.email.request'), [
            'email' => 'available@example.com',
            'current_password' => 'password',
        ])->assertOk();

        $this->assertSame($requested->json(), $available->json());
        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_access_email_requests_are_rate_limited(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $payload = ['email' => $user->email, 'current_password' => 'password'];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson(route('profile.email.request'), $payload)->assertOk();
        }

        $this->postJson(route('profile.email.request'), $payload)->assertTooManyRequests();
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}
