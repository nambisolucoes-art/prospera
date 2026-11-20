<?php

require_once __DIR__.'/organization-http.php';

if (isset($argv[1])) {
    $race = json_decode(file_get_contents('/tmp/prospera-e05-race.json'), true, flags: JSON_THROW_ON_ERROR);
    $case = $argv[1];
    $params = $case === 'grant' ? $race['grant'] : $race['suspensions'][(int) $argv[2]];
    $cookies = [];
    [$session] = request('/_e03/session/'.$params['actor'], $cookies);
    $path = '/conta/usuarios/'.$params['membership'];
    $payload = ['_token' => $session['token']];

    if ($case === 'grant') {
        $path .= '/atribuicoes';
        $payload['grants'] = [[
            'role_id' => $params['role'],
            'scope' => 'establishment',
            'company_id' => $params['company'],
            'establishment_id' => $params['unit'],
        ]];
    } else {
        $path .= '/suspender';
    }

    [, $metrics, $status] = request($path, $cookies, [200, 403, 422], $payload, 'PATCH');
    echo json_encode(['e05_race' => $case, 'status' => $status, ...$metrics], JSON_THROW_ON_ERROR).PHP_EOL;
    exit($status === 200 ? 0 : $status % 100);
}

$scenarios = json_decode(file_get_contents('/tmp/prospera-e05-scenarios.json'), true, flags: JSON_THROW_ON_ERROR);
$sessions = [];
foreach ($scenarios as $scenario => $parameters) {
    $session = [];
    request('/_e03/session/'.$parameters['user'], $session);
    $sessions[$scenario] = $session;

    for ($sample = 0; $sample < 11; $sample++) {
        [$authorization, $metrics] = request('/_e05/authorize', $session);
        [$page, $listing] = request('/conta/estabelecimentos?per_page=50', $session);
        verify($authorization['covered']);
        verify($metrics['permission_queries'] === 1 && $metrics['assignment_queries'] === 1);
        verify($listing['permission_queries'] === 1 && $listing['assignment_queries'] === 1);
        verify($page['establishments']['total'] === $parameters['rows']);

        if ($sample !== 0) {
            echo json_encode([
                'e05' => $scenario,
                'assignments' => $parameters['assignment_rows'],
                'roles' => $parameters['roles'],
                'permission_rows' => $parameters['permission_rows'],
                'authorization' => $authorization,
                'list' => $listing,
            ], JSON_THROW_ON_ERROR).PHP_EOL;
        }
    }
}

if (getenv('E05_ISOLATION') === '1') {
    $worker = null;

    for ($round = 0; $round < 60; $round++) {
        foreach ([1, 5, 30, 'integral'] as $scenario) {
            [$page, $metrics] = request('/conta/estabelecimentos?per_page=50', $sessions[$scenario]);
            $worker ??= $metrics['worker'];
            verify($worker === $metrics['worker'] && $page['establishments']['total'] === $scenarios[$scenario]['rows']);
        }

        request('/conta/estabelecimentos?company=9999999', $sessions[1], 404);
    }

    echo json_encode(['e05_isolation' => 300, 'worker' => $worker], JSON_THROW_ON_ERROR).PHP_EOL;
}
