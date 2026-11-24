<?php

// HTTP real contra FrankenPHP no laboratório E09, sem atalhos de autenticação.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

require __DIR__.'/organization-http.php';
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (getenv('DB_DATABASE') !== 'e09') {
    throw new RuntimeException('Somente o laboratório E09.');
}
function token(array $cookies): string
{
    return CookieValuePrefix::remove(
        Crypt::decryptString(
            urldecode(substr($cookies['XSRF-TOKEN'], strlen('XSRF-TOKEN='))),
        ),
    );
}
function login(string $email): array
{
    $cookies = [];
    request('/login', $cookies);
    request('/login', $cookies, 200, ['_token' => token($cookies), 'email' => $email, 'password' => 'password']);

    return $cookies;
}
$data = json_decode(file_get_contents('/tmp/e09-fixtures.json'), true, flags: JSON_THROW_ON_ERROR);
$a = '/contas/'.$data['accounts'][0]['code'];
$b = '/contas/'.$data['accounts'][1]['code'];
$cookies = login('multi@e09.test');
$single = login('single@e09.test');
$suspended = login('suspended@e09.test');
$platform = login('platform@e09.test');
$samples = [];
$workers = [];
$iterations = max(600, (int) (getenv('E09_REQUESTS') ?: 600));
for ($iteration = 0; $iteration < $iterations; $iteration++) {
    $path = match ($iteration % 6) {
        0 => $a.'/etiquetas',
        1 => $b.'/etiquetas?search=E09&page=2',
        2 => '/contas',
        3 => $a.'/area-de-trabalho/padrao',
        4 => $b.'/area-de-trabalho/padrao',
        default => $a.'/usuarios',
    };
    [$body, $metrics] = request($path, $cookies);
    if ($path === '/contas') {
        verify($body['account_context'] === null);
        verify(array_filter(
            $body['auth']['permissions'],
            fn (string $slug): bool => ! str_starts_with($slug, 'self.'),
        ) === []);
    }
    if (isset($body['account_context'])) {
        verify($body['account_context']['base'] === (str_starts_with($path, $b) ? $b : $a));
        verify(in_array('account.tags.create', $body['auth']['permissions'], true) === ! str_starts_with($path, $b));
    }
    $samples[$path][] = $metrics;
    if (isset($metrics['worker'])) {
        $workers[$metrics['worker']] = true;
    }
    if ($iteration % 30 === 0) {
        request($a, $single);
        request($a, $suspended, 403);
        request('/contas/nao-existe', $cookies, 404);
        request($b.'/etiquetas', $cookies, 403, [
            '_token' => token($cookies), 'name' => 'Negado', 'module' => 'customers', 'color' => '#123456',
        ]);
    }
}
// Revogação de permissão vale na requisição seguinte, sem reiniciar o worker.
$roleId = DB::table('roles')
    ->where('account_id', $data['accounts'][1]['id'])
    ->where('name', 'Operador limitado')
    ->value('id');
$permissionId = DB::table('permissions')->where('slug', 'account.tags.list')->value('id');
DB::table('permission_role')->where('role_id', $roleId)->where('permission_id', $permissionId)->delete();
request($b.'/etiquetas', $cookies, 403);
DB::table('permission_role')->insert(['role_id' => $roleId, 'permission_id' => $permissionId]);
request($b.'/etiquetas', $cookies);
// Inspeção tem sessão própria e não abre outra conta alterando a URL.
for ($round = 0; $round < 10; $round++) {
    [, $startMetrics] = request('/saas/contas/'.$data['accounts'][0]['id'].'/inspecao', $platform, 200, [
        '_token' => token($platform), 'user_id' => $data['users']['user'], 'mode' => 'read-only',
    ]);
    $samples['inspection-start'][] = $startMetrics;
    [, $readMetrics] = request($a, $platform);
    $samples['inspection-read'][] = $readMetrics;
    request($b, $platform, 404);
    request($a.'/area-de-trabalho/padrao', $platform, 403, [
        '_token' => token($platform), 'unidades' => ['empresa-1'],
    ], 'PUT');
    request($b.'/etiquetas', $cookies);
    [, $endMetrics] = request('/inspecao/encerrar', $platform, 200, ['_token' => token($platform)]);
    $samples['inspection-end'][] = $endMetrics;
    request($a, $cookies);
}
$normalName = 'E09 autoria normal '.bin2hex(random_bytes(5));
request($a.'/etiquetas', $cookies, 200, [
    '_token' => token($cookies), 'name' => $normalName, 'module' => 'customers', 'color' => '#123456',
]);
$normal = DB::table('audit_records')->where('entity_type', 'tag')->where('entity_name', $normalName)->first();
verify($normal !== null && (int) $normal->actor_id === $data['users']['user']);
verify((int) $normal->account_id === $data['accounts'][0]['id'] && $normal->represented_user_id === null);
request('/saas/contas/'.$data['accounts'][0]['id'].'/inspecao', $platform, 200, [
    '_token' => token($platform), 'user_id' => $data['users']['user'], 'mode' => 'edit',
]);
$inspectedName = 'E09 autoria inspecao '.bin2hex(random_bytes(5));
request($a.'/etiquetas', $platform, 200, [
    '_token' => token($platform), 'name' => $inspectedName, 'module' => 'customers', 'color' => '#123456',
]);
$inspected = DB::table('audit_records')->where('entity_type', 'tag')->where('entity_name', $inspectedName)->first();
verify($inspected !== null && (int) $inspected->actor_id === $data['users']['platform']);
verify((int) $inspected->represented_user_id === $data['users']['user']);
verify((int) $inspected->account_id === $data['accounts'][0]['id']);
verify($inspected->inspection_id !== null && $inspected->membership_id !== null);
request('/inspecao/encerrar', $platform, 200, ['_token' => token($platform)]);
request($a, $cookies);
function percentile(array $values, float $rank): float
{
    sort($values);

    return $values[max(0, (int) ceil(count($values) * $rank) - 1)];
}
$summary = [];
foreach ($samples as $path => $values) {
    $summary[$path] = [
        'samples' => count($values),
        'median_ms' => percentile(array_column($values, 'http_ms'), .5),
        'p95_ms' => percentile(array_column($values, 'http_ms'), .95),
        'queries' => array_values(array_unique(array_column($values, 'queries'))),
        'duplicates_max' => max(array_column($values, 'duplicates')),
        'memory_start_first' => $values[0]['start_bytes'],
        'memory_start_last' => $values[count($values) - 1]['start_bytes'],
        'peak_delta_max' => max(array_column($values, 'peak_delta_bytes')),
    ];
}
echo json_encode([
    'e09' => 'passed',
    'sampled_requests' => $iterations,
    'workers_observed' => count($workers),
    'summary' => $summary,
]), PHP_EOL;
