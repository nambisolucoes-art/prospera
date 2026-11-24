<?php

namespace Tests\Feature;

use Tests\TestCase;

final class AccessLogRedactionTest extends TestCase
{
    public function test_every_response_forbids_the_referer_header(): void
    {
        $token = str_repeat('A', 64);

        $this->get('/login')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->get('/convites/'.$token)->assertHeader('Referrer-Policy', 'no-referrer');
        $this->getJson('/convites/'.$token)->assertHeader('Referrer-Policy', 'no-referrer');
        $this->get('/reset-password/'.$token.'?email=a@b.test')->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_the_page_shell_also_declares_the_policy(): void
    {
        $this->get('/login')->assertSee('<meta name="referrer" content="no-referrer">', false);
    }

    public function test_official_commands_start_octane_with_the_redacting_caddyfile(): void
    {
        $dockerfile = (string) file_get_contents(base_path('Dockerfile'));
        $development = (string) file_get_contents(base_path('docker/dev.sh'));

        $this->assertStringContainsString('"--caddyfile=/app/docker/Caddyfile"', $dockerfile);
        $this->assertStringContainsString('--caddyfile=docker/Caddyfile', $development);
        $this->assertFileExists(base_path('docker/Caddyfile'));
    }

    public function test_caddyfile_redacts_sensitive_paths_and_headers(): void
    {
        $caddyfile = (string) file_get_contents(base_path('docker/Caddyfile'));

        $this->assertStringContainsString('request>uri regexp', $caddyfile);
        $this->assertStringContainsString('request>headers>Referer regexp', $caddyfile);
        $this->assertStringContainsString('request>headers>X-Xsrf-Token delete', $caddyfile);

        foreach (['convites', 'reset-password', 'email/verify', 'settings/profile/email/confirm'] as $prefix) {
            $this->assertStringContainsString($prefix, $caddyfile);
        }

        // Toda rota cujo caminho ou query carrega credencial temporária precisa estar coberta pelo filtro.
        $routes = (string) file_get_contents(base_path('routes/account.php')).file_get_contents(base_path('routes/settings.php'));
        $this->assertMatchesRegularExpression('#/convites/\{token\}#', $routes);
        $this->assertMatchesRegularExpression("#'signed'#", $routes);
    }
}
