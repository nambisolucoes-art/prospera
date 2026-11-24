<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class ErrorScreensTest extends TestCase
{
    public function test_unknown_page_opened_directly_receives_the_application_with_status_404(): void
    {
        $this->get('/pagina-que-nao-existe')
            ->assertNotFound()
            ->assertSee('<div id="root">', false)
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_the_application_repeats_the_request_in_json_and_gets_the_real_status(): void
    {
        $this->getJson('/pagina-que-nao-existe')->assertNotFound()->assertJsonStructure(['message']);
    }

    public function test_maintenance_serves_the_application_shell_with_retry_after(): void
    {
        Artisan::call('down', ['--retry' => 60]);

        try {
            $this->get('/login')
                ->assertStatus(503)
                ->assertSee('<div id="root">', false)
                ->assertHeader('Retry-After', '60');
            $this->getJson('/login')->assertStatus(503);
        } finally {
            Artisan::call('up');
        }
    }

    public function test_non_navigation_requests_keep_their_own_error_response(): void
    {
        $this->post('/pagina-que-nao-existe')->assertStatus(404)->assertDontSee('<div id="root">', false);
    }
}
