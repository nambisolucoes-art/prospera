<?php

function request(string $path, array &$cookies, int $expected = 200, ?array $input = null): array
{
    $header = ['Accept: application/json'];
    $header[] = 'Cookie: '.implode('; ', $cookies);
    $options = ['ignore_errors' => true, 'header' => $header];

    if ($input !== null) {
        $options['method'] = 'POST';
        $options['header'][] = 'Content-Type: application/json';
        $options['content'] = json_encode($input, JSON_THROW_ON_ERROR);
    }

    $options['follow_location'] = 0;
    $start = hrtime(true);
    $body = file_get_contents('http://127.0.0.1:8000'.$path, false, stream_context_create(['http' => $options]));
    $elapsed = (hrtime(true) - $start) / 1e6;
    $headers = http_get_last_response_headers();

    if ((int) explode(' ', $headers[0] ?? '')[1] !== $expected) {
        throw new RuntimeException("$path: ".($headers[0] ?? '').' '.substr((string) $body, 0, 500));
    }

    $metrics = [];
    foreach ($headers as $line) {
        if (preg_match('/^set-cookie: (([^=]+)=[^;]+)/i', $line, $matches)) {
            $cookies[$matches[2]] = $matches[1];
        }

        if (str_starts_with(strtolower($line), 'x-e03-metrics:')) {
            $metrics = json_decode(trim(explode(':', $line, 2)[1]), true, flags: JSON_THROW_ON_ERROR);
        }
    }

    return [json_decode((string) $body, true), [...$metrics, 'http_ms' => $elapsed]];
}

function verify(bool $condition): void
{
    if (! $condition) {
        throw new RuntimeException('Falha na verificação HTTP da linha '.debug_backtrace()[0]['line'].'.');
    }
}

// Empresa e unidade principal da Conta A, criadas pelas fixtures (E04).
const COMPANY = '/conta/empresa/empresa-a';
const UNIT = '/conta/estabelecimento/empresa-a';

$owner = [];
$member = [];
$medium = [];
$platform = [];
request('/_e03/session/1', $owner);
request('/_e03/session/41', $member);
request('/_e03/session/42', $medium);
request('/_e03/session/43', $platform);
$workers = [];
foreach ([
    // Pessoas não tem tela própria desde 2ba26f7: a listagem e o cadastro de Clientes são as entradas vigentes.
    '/conta', UNIT.'/clientes', UNIT.'/clientes/cadastrar', '/conta/etiquetas', '/conta/cargos',
    '/conta/cargos/cadastrar', '/conta/auditoria', '/conta/usuarios', '/ajuda',
] as $path) {
    for ($sample = 0; $sample < 10; $sample++) {
        [$payload, $metrics] = request($path, $owner);

        // Confere que a medição exercita a tela prevista, e não uma página de erro ou de orientação.
        if ($path === UNIT.'/clientes') {
            verify(isset($payload['relations']['data']) && $payload['role'] === 'customer');
        }

        if ($path === UNIT.'/clientes/cadastrar') {
            verify(($payload['companies'][0]['code'] ?? null) === 'empresa-a');
        }

        $workers[$metrics['worker']] = true;
        echo json_encode(['path' => $path, 'profile' => 'owner', ...$metrics], JSON_THROW_ON_ERROR).PHP_EOL;
    }
}
foreach (['member' => 10, 'medium' => 40] as $profile => $size) {
    for ($sample = 0; $sample < 10; $sample++) {
        [$payload, $metrics] = request('/ajuda', $$profile);
        verify(count($payload['auth']['permissions']) === $size);
        $workers[$metrics['worker']] = true;
        echo json_encode(['path' => '/ajuda', 'profile' => $profile, ...$metrics], JSON_THROW_ON_ERROR).PHP_EOL;
    }
}

if ($argv[1] !== '1') {
    exit;
}

[$session] = request('/_e03/session', $platform);
request('/saas/contas/1/inspecao', $platform, 200, [
    '_token' => $session['token'], 'user_id' => 1, 'mode' => 'read-only',
]);
[$inspected, $during] = request('/conta', $platform);
[$other, $after] = request('/conta', $member);
verify(count($workers) === 1 && isset($workers[$during['worker']], $workers[$after['worker']]));
verify($inspected['auth']['inspection_mode'] === 'read-only' && $inspected['account']['name'] === 'Conta A');
verify($other['auth']['inspection_mode'] === null && $other['account']['name'] === 'Conta B');
verify(! in_array('account.customers.create', $other['auth']['permissions'], true));
[$session] = request('/_e03/session', $platform);
$inspectionToken = $session['token'];
[$session] = request('/_e03/session', $owner);
$ownerToken = $session['token'];
$workerMemory = [];
// Mantém a execução completa abaixo da reciclagem padrão de 500 requisições por worker.
$rounds = 60;

for ($round = 0; $round < $rounds; $round++) {
    request(COMPANY.'/clientes', $platform, 403, ['_token' => $inspectionToken, 'name' => 'Bloqueado']);
    request(COMPANY.'/clientes/999999/visualizar', $owner, 404);
    request(COMPANY.'/clientes', $owner, 422, ['_token' => $ownerToken, 'name' => '']);
    [$other, $memberMetrics] = request('/conta', $member);
    [$inspected, $inspectionMetrics] = request('/conta', $platform);

    verify($memberMetrics['worker'] === $after['worker'] && $inspectionMetrics['worker'] === $after['worker']);
    verify($other['account']['name'] === 'Conta B' && $other['auth']['inspection_mode'] === null);
    verify(! in_array('account.customers.create', $other['auth']['permissions'], true));
    verify($inspected['account']['name'] === 'Conta A' && $inspected['auth']['inspection_mode'] === 'read-only');
    verify(in_array('account.customers.create', $inspected['auth']['permissions'], true));
    $workerMemory[] = $memberMetrics['start_bytes'];
}

echo json_encode([
    'isolation_requests' => 5 * $rounds,
    'worker_php_start_bytes' => array_intersect_key($workerMemory, array_flip([0, 14, 29, 44, 59])),
], JSON_THROW_ON_ERROR).PHP_EOL;

request('/conta/inspecao/encerrar', $platform, 200, ['_token' => $inspectionToken]);
[$session] = request('/_e03/session', $platform);
request('/saas/contas/1/inspecao', $platform, 200, [
    '_token' => $session['token'], 'user_id' => 1, 'mode' => 'edit',
]);
[$session] = request('/_e03/session', $platform);
request(COMPANY.'/clientes', $platform, 204, [
    '_token' => $session['token'], 'name' => 'Cliente sintético',
]);
request(COMPANY.'/clientes', $owner, 204, [
    '_token' => $ownerToken, 'name' => 'Cliente do administrador',
]);
[$listing] = request(UNIT.'/clientes', $owner);
verify($listing['relations']['total'] === 2);
request('/ajuda', $member);
exec('php /performance/octane-fixtures.php revoke', result_code: $status);
verify($status === 0);
[, $denied] = request('/ajuda', $member, 403);
verify($denied['worker'] === $after['worker']);
echo "Revogação, isolamento e inspeção verificados.\n";
