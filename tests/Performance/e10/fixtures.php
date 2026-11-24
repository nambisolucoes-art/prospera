<?php

// E10: só as identidades de plataforma nascem aqui. Contas, empresas, usuários e relações são criados pelas
// jornadas HTTP, com as mesmas validações, autorizações e auditoria da interface.
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (getenv('DB_DATABASE') !== 'e10') {
    throw new RuntimeException('Somente o laboratório E10.');
}

User::factory()->create([
    'name' => 'Plataforma E10',
    'email' => 'platform@e10.test',
    'is_saas_admin' => true,
]);

// Segundo inspetor, usado para a perda de autoridade durante a inspeção.
User::factory()->create([
    'name' => 'Inspetor revogável E10',
    'email' => 'platform-revoked@e10.test',
    'is_saas_admin' => true,
]);

echo 'fixtures-e10=ok', PHP_EOL;
