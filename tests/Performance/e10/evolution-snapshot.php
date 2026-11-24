<?php

// Fotografia por SHA-256 das tabelas preservadas na evolução; usa só SQL, para rodar no código antigo e no atual.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! str_starts_with((string) getenv('DB_DATABASE'), 'e10evo') && getenv('DB_DATABASE') !== 'e10_restore') {
    throw new RuntimeException('Somente o laboratório de evolução da E10.');
}

// Colunas que a própria evolução acrescenta ou remove ficam fora do hash e são conferidas à parte.
$ignored = ['users' => ['account_id', 'password_setup_pending'], 'clients' => ['code']];
$tables = ['users', 'clients', 'companies', 'establishments', 'people', 'customers', 'suppliers', 'tags', 'person_tag',
    'roles', 'permission_role', 'role_availability', 'account_memberships', 'role_assignments', 'audit_records'];
$snapshot = [];

foreach ($tables as $table) {
    if (! Schema::hasTable($table)) {
        $snapshot[$table] = null;

        continue;
    }

    $removed = implode(' ', array_map(fn ($c) => "- '".$c."'", $ignored[$table] ?? []));
    $order = Schema::hasColumn($table, 'id') ? 'id' : '1';
    $rows = DB::select("select to_jsonb(t) {$removed} as row from {$table} t order by {$order}");
    $snapshot[$table] = ['rows' => count($rows), 'sha256' => hash('sha256', json_encode(array_column($rows, 'row')))];
}

// Linhas pré-existentes: o código atual acrescenta permissões e eventos de sincronização, sem alterar os antigos.
$limits = is_file('/tmp/e10-evolution-before.json')
    ? json_decode((string) file_get_contents('/tmp/e10-evolution-before.json'), true)['limites']
    : ['audit_records' => (int) DB::table('audit_records')->max('id'), 'permissions' => (int) DB::table('permissions')->max('id')];
$snapshot['limites'] = $limits;
$preexisting = fn (string $sql) => hash('sha256', json_encode(array_column(DB::select($sql), 'row')));
$snapshot['audit_records_preexistentes'] = $preexisting(
    'select to_jsonb(t) as row from audit_records t where id <= '.(int) $limits['audit_records'].' order by id',
);
$snapshot['permission_role_preexistentes'] = $preexisting(
    'select to_jsonb(t) as row from permission_role t where permission_id <= '.(int) $limits['permissions'].' order by role_id, permission_id',
);
$snapshot['acrescimos'] = [
    'audit_records' => DB::table('audit_records')->where('id', '>', $limits['audit_records'])->pluck('event', 'entity_type'),
    'permissions' => DB::table('permissions')->where('id', '>', $limits['permissions'])->pluck('slug'),
];

$snapshot['users.account_id'] = Schema::hasColumn('users', 'account_id');
$snapshot['clients.code'] = Schema::hasColumn('clients', 'code') ? DB::table('clients')->orderBy('id')->pluck('code', 'id') : null;
$snapshot['workspace_preferences'] = DB::table('account_memberships')->whereNotNull('workspace_establishments')->count();
$snapshot['memberships_by_user'] = DB::table('account_memberships')->orderBy('user_id')->get(['user_id', 'account_id', 'status']);
if ($snapshot['users.account_id']) {
    $snapshot['users_account'] = DB::table('users')->whereNotNull('account_id')->orderBy('id')->pluck('account_id', 'id');
}

echo json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
