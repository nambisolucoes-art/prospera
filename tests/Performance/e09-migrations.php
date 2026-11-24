<?php

// Contratos de evolução em PostgreSQL descartável; não pertence à suíte SQLite.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (getenv('DB_DATABASE') !== 'e09') {
    throw new RuntimeException('Somente o laboratório E09.');
}
function verify(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
$path = '/app/database/migrations/';
$code = require $path.'2026_11_23_000001_add_code_to_clients.php';
$contract = require $path.'2026_11_23_000003_remove_account_from_users.php';
$before = [];
foreach (['account_memberships', 'role_assignments', 'audit_records'] as $table) {
    $before[$table] = hash('sha256', json_encode(DB::table($table)->orderBy('id')->get()));
}
try {
    $contract->down();
    throw new RuntimeException('Rollback ambíguo deveria falhar.');
} catch (RuntimeException $exception) {
    verify(str_contains($exception->getMessage(), 'Reversão interrompida'), $exception->getMessage());
}
verify(! Schema::hasColumn('users', 'account_id'), 'Rollback alterou esquema antes de abortar.');
$code->down();
DB::table('clients')->insert([
    ['name' => 'Árvore & Ação', 'active' => true, 'details' => null],
    ['name' => 'Árvore & Ação', 'active' => true, 'details' => null],
    ['name' => 'todos', 'active' => true, 'details' => null],
]);
$code->up();
verify(DB::table('clients')->whereNull('code')->count() === 0, 'Código ausente.');
verify(DB::table('clients')->count() === DB::table('clients')->distinct()->count('code'), 'Códigos repetidos.');
foreach ($before as $table => $hash) {
    verify($hash === hash('sha256', json_encode(DB::table($table)->orderBy('id')->get())), 'Dados alterados: '.$table);
}
echo json_encode(['evolution' => 'passed', 'legacy_column' => Schema::hasColumn('users', 'account_id'),
    'preserved' => array_keys($before), 'codes' => DB::table('clients')->pluck('code')]), PHP_EOL;
