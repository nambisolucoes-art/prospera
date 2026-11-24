<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_the_landing_page_with_the_login_link()
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Prospera', false)
            ->assertSee('href="/login"', false)
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_authenticated_users_see_the_landing_with_a_link_to_the_dashboard()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Ir para o painel', false)
            ->assertSee('href="/dashboard"', false)
            ->assertDontSee('href="/login"', false);
    }

    public function test_demo_buttons_have_no_destination_until_configured()
    {
        $this->get(route('home'))
            ->assertSee('Agende sua demonstração', false)
            ->assertSee('href="#" data-demo', false);

        config(['landing.demo_url' => 'https://agenda.example.test/demo']);
        $this->get(route('home'))->assertSee('href="https://agenda.example.test/demo" data-demo', false);
    }
}
