<?php

require_once __DIR__.'/organization-http.php';

if (isset($argv[1])) {
    $operation = $argv[1];
    $unit = (int) ($argv[2] ?? 0);
    $cookies = [];
    [$session] = request('/_e03/session/2', $cookies);
    $token = ['_token' => $session['token']];
    [, $metrics, $status] = match ($operation) {
        'promote' => request("/conta/estabelecimentos/$unit/principal", $cookies, [200, 422], $token),
        'deactivate' => request("/conta/estabelecimentos/$unit", $cookies, [200, 422], [
            ...$token,
            'active' => false,
        ], 'PATCH'),
        'create' => request('/conta/empresas', $cookies, [200, 422], [
            ...$token,
            'person_type' => 'company',
            'name' => 'Empresa concorrente',
            'document' => '12.ABC.345/01DE-35',
            'primary' => ['classification' => 'headquarters'],
        ]),
    };
    echo json_encode(['operation' => $operation, 'unit' => $unit, 'status' => $status, ...$metrics],
        JSON_THROW_ON_ERROR).PHP_EOL;

    exit($status === 200 ? 0 : 42);
}

foreach (['1x1', '1x3', '3xN', '10', '50'] as $index => $scenario) {
    $cookies = [];
    request('/_e03/session/'.($index + 1), $cookies);
    [$units] = request('/conta/estabelecimentos', $cookies);
    $company = $units['companies'][0]['id'];
    foreach (['/', '/empresas?status=active', '/estabelecimentos?per_page=50', "/estabelecimentos?company=$company&status=active", '/ajuda'] as $suffix) {
        $path = $suffix === '/ajuda' ? $suffix : '/conta'.rtrim($suffix, '/');
        for ($sample = 0; $sample < 11; $sample++) {
            [$payload, $metrics] = request($path, $cookies);
            $rows = $payload['companies']['data'] ?? $payload['establishments']['data'] ?? [];
            verify($metrics['permission_queries'] === 1);
            if ($sample > 0) {
                echo json_encode(['scenario' => $scenario, 'path' => $path, 'rows' => count($rows), ...$metrics]).PHP_EOL;
            }
        }
    }
}
if (getenv('E04_ISOLATION') === '1') {
    $first = $second = [];
    [$session] = request('/_e03/session/1', $first);
    request('/_e03/session/2', $second);
    $worker = null;
    $peak = [];
    for ($round = 0; $round < 60; $round++) {
        request('/saas/contas', $first, 403);
        request('/conta/estabelecimentos/2/visualizar', $first, 404);
        request('/conta/empresas', $first, 422, ['_token' => $session['token']]);
        foreach ([1, 3] as $expected) {
            [$body, $metrics] = $expected === 1
                ? request('/conta/estabelecimentos', $first)
                : request('/conta/estabelecimentos', $second);
            $worker ??= $metrics['worker'];
            verify($worker === $metrics['worker'] && count($body['establishments']['data']) === $expected);
            verify($metrics['permission_queries'] === 1);
            $peak[] = $metrics['start_bytes'];
        }
    }
    echo json_encode(['isolation' => 300, 'worker' => $worker, 'last_start_bytes' => array_slice($peak, -4)]).PHP_EOL;
}
