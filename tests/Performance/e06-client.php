<?php

use App\Domains\Acessos\Domain\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require_once __DIR__.'/organization-http.php';

$data = json_decode(file_get_contents('/tmp/prospera-e06-scenarios.json'), true, flags: JSON_THROW_ON_ERROR);
$gartan = $data['gartan'];
$sessions = [];

foreach ($data['scenarios'] as $name => $scenario) {
    $session = [];
    request('/_e03/session/'.$scenario['user'], $session);
    $sessions[$name] = $session;

    for ($sample = 0; $sample < 11; $sample++) {
        [$home, $homeMetrics] = request('/conta/estabelecimento/'.$scenario['code'], $session);
        [$cost] = request('/_e06/context/'.$scenario['code'], $session);
        [, $listMetrics] = request('/conta/estabelecimentos?per_page=50', $session);
        verify($home['establishment']['code'] === $scenario['code']);
        verify(count($home['organization_context']['establishments']) === ($scenario['units'] > 1 ? $scenario['units'] : 0));
        verify($cost['units'] === $scenario['units']);

        foreach ([$homeMetrics, $listMetrics] as $metrics) {
            verify($metrics['permission_queries'] === 1 && $metrics['assignment_queries'] === 1);
            verify($metrics['duplicates'] === 0);
        }

        if ($sample !== 0) {
            echo json_encode([
                'e06' => $name,
                'units' => $scenario['units'],
                'home' => $homeMetrics,
                'context' => $cost,
                'list' => $listMetrics,
            ], JSON_THROW_ON_ERROR).PHP_EOL;
        }
    }
}

// Troca de unidade: navegação alternada entre duas unidades autorizadas na mesma sessão.
foreach (['unidades', 'cargos'] as $name) {
    $session = $sessions[$name];
    [$first] = request('/conta/estabelecimento/'.$data['scenarios'][$name]['code'], $session);
    $codes = array_column($first['organization_context']['establishments'], 'code');

    for ($sample = 0; $sample < 11; $sample++) {
        $code = $codes[$sample % 2 === 0 ? 0 : count($codes) - 1];
        [$page, $metrics] = request('/conta/estabelecimento/'.$code, $session);
        verify($page['organization_context']['establishment']['code'] === $code);

        if ($sample !== 0) {
            echo json_encode(['e06_switch' => $name, 'code' => $code, 'home' => $metrics], JSON_THROW_ON_ERROR).PHP_EOL;
        }
    }
}

// Duas abas da mesma sessão em unidades diferentes, com requisições simultâneas.
$tabs = [];
request('/_e03/session/'.$gartan['ana'], $tabs);
$parallel = 0;

for ($round = 0; $round < 20; $round++) {
    $multi = curl_multi_init();
    $handles = [];

    foreach ([$gartan['ana_units'][0], $gartan['ana_units'][2]] as $code) {
        $handle = curl_init('http://127.0.0.1:8000/conta/estabelecimento/'.$code);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Cookie: '.implode('; ', $tabs)],
        ]);
        curl_multi_add_handle($multi, $handle);
        $handles[$code] = $handle;
    }

    do {
        curl_multi_exec($multi, $running);
        curl_multi_select($multi);
    } while ($running > 0);

    foreach ($handles as $code => $handle) {
        $page = json_decode((string) curl_multi_getcontent($handle), true, flags: JSON_THROW_ON_ERROR);
        verify(curl_getinfo($handle, CURLINFO_RESPONSE_CODE) === 200);
        verify($page['establishment']['code'] === $code);
        verify($page['organization_context']['establishment']['code'] === $code);
        curl_multi_remove_handle($multi, $handle);
        $parallel++;
    }

    curl_multi_close($multi);
}

echo json_encode(['e06_parallel_tabs' => $parallel], JSON_THROW_ON_ERROR).PHP_EOL;

if (getenv('E06_ISOLATION') !== '1') {
    return;
}

$users = ['ana' => $gartan['ana'], 'local' => $gartan['local'], 'single' => $gartan['single_owner']];
$cookies = [];

foreach ($users as $name => $id) {
    $cookies[$name] = [];
    request('/_e03/session/'.$id, $cookies[$name]);
}

// Com Accept JSON, o redirecionamento sem código chega como {redirect}.
[$single] = request('/conta/estabelecimento', $cookies['single']);
verify($single['redirect'] === 'http://127.0.0.1:8000/conta/estabelecimento/'.$data['scenarios']['integral 1x1']['code']);
[$localPage] = request('/conta/estabelecimento', $cookies['local']);
$localCode = basename($localPage['redirect']);
$expected = $gartan['ana_units'];
sort($expected);
$worker = null;
$checked = 0;

for ($round = 0; $round < 60; $round++) {
    $code = $gartan['ana_units'][$round % count($gartan['ana_units'])];
    if ($round % 2 === 0) {
        $selected = [$code, $gartan['ana_units'][($round + 1) % count($gartan['ana_units'])]];
        $uri = '/conta/estabelecimento/todos?unidades='.implode(',', $selected);
        [$page, $metrics] = request($uri, $cookies['ana']);
        verify($page['organization_context']['selected_codes'] === $selected);
        verify(count($page['workspace']) === 2);

        if ($round < 20) {
            echo json_encode(['e06_workspace' => 'subset', 'units' => 2, 'home' => $metrics], JSON_THROW_ON_ERROR).PHP_EOL;
        }
    } else {
        [$page, $metrics] = request('/conta/estabelecimento/'.$code, $cookies['ana']);
        verify($page['establishment']['code'] === $code);
    }

    $worker ??= $metrics['worker'];
    $codes = array_column($page['organization_context']['establishments'], 'code');
    sort($codes);
    verify($metrics['worker'] === $worker && $codes === $expected);

    [$page, $metrics] = request('/conta/estabelecimento', $cookies['local']);
    verify($metrics['worker'] === $worker && str_ends_with($page['redirect'], '/conta/estabelecimento/'.$localCode));

    [$page, $metrics] = request('/conta/estabelecimento/'.$data['scenarios']['integral 1x1']['code'], $cookies['single']);
    verify($metrics['worker'] === $worker && $page['organization_context']['establishments'] === []);
    verify(! str_contains(json_encode($page, JSON_THROW_ON_ERROR), 'Gartan Roupas'));

    // Código de outra empresa sem atribuição e código de outro tenant.
    request('/conta/estabelecimento/'.$gartan['hidden'], $cookies['ana'], 404);
    request('/conta/estabelecimento/'.$code, $cookies['single'], 404);
    $checked += 5;
}

echo json_encode(['e06_isolation' => $checked, 'worker' => $worker, 'single_redirect' => $single['redirect']], JSON_THROW_ON_ERROR).PHP_EOL;

// Revogação entre requisições no mesmo worker, sem cache de contexto.
require_once '/app/vendor/autoload.php';
$application = require '/app/bootstrap/app.php';
$application->make(Kernel::class)->bootstrap();

$janauba = $gartan['ana_units'][2];
request('/conta/estabelecimento/'.$janauba, $cookies['ana']);
$assignment = RoleAssignment::where('establishment_id', $gartan['express_janauba'])->sole();
$assignment->delete();
[, $metrics] = request('/conta/estabelecimento/'.$janauba, $cookies['ana'], 404);
verify($metrics['worker'] === $worker);
[$page] = request('/conta/estabelecimento/'.$gartan['ana_units'][0], $cookies['ana']);
verify(! in_array($janauba, array_column($page['organization_context']['establishments'], 'code'), true));
$assignment->replicate()->saveOrFail();
request('/conta/estabelecimento/'.$janauba, $cookies['ana']);

// Inspeção: o inspetor vê os contextos de Ana e perde tudo na requisição seguinte à revogação.
$inspector = [];
[$guest] = request('/_e05/session-state', $inspector);
request('/login', $inspector, 200, ['_token' => $guest['token'], 'email' => 'saas@e06.test', 'password' => 'password']);
[$state] = request('/_e05/session-state', $inspector);
request('/saas/contas/'.$gartan['account'].'/inspecao', $inspector, 200, [
    '_token' => $state['token'],
    'user_id' => $gartan['ana'],
    'mode' => 'read-only',
]);
[$page, $metrics] = request('/conta/estabelecimento/'.$janauba, $inspector);
verify($metrics['worker'] === $worker && $page['auth']['inspection_mode'] === 'read-only');
$codes = array_column($page['organization_context']['establishments'], 'code');
sort($codes);
verify($codes === $expected);
request('/conta/estabelecimento/'.$gartan['hidden'], $inspector, 404);

$platform = User::findOrFail($gartan['platform']);
$platform->forceFill(['is_saas_admin' => false])->saveOrFail();
request('/conta/estabelecimento/'.$janauba, $inspector, 401);
[$ended] = request('/_e05/session-state', $inspector);
verify($ended['guard_user'] === null && ! $ended['inspecting']);
request('/conta/estabelecimento/'.$janauba, $inspector, 401);
[$page, $metrics] = request('/conta/estabelecimento/'.$janauba, $cookies['ana']);
verify($metrics['worker'] === $worker && $page['auth']['inspection_mode'] === null);
$platform->forceFill(['is_saas_admin' => true])->saveOrFail();

echo json_encode([
    'e06_revocation_next_request' => true,
    'e06_inspection_revoked' => true,
    'represented_session_preserved' => true,
    'worker' => $worker,
], JSON_THROW_ON_ERROR).PHP_EOL;
