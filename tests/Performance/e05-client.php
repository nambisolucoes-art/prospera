<?php

use App\Domains\Plataforma\Domain\Models\Client;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

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

    $case = json_decode(file_get_contents('/tmp/prospera-e05-inspection.json'), true, flags: JSON_THROW_ON_ERROR);
    $operator = [];
    [$session] = request('/_e03/session/'.$case['operator'], $operator);
    request('/conta/usuarios/'.$case['membership'].'/atribuicoes', $operator, 403, [
        '_token' => $session['token'],
        'grants' => [],
        'revocations' => [],
    ], 'PATCH');

    $member = [];
    [$guest] = request('/_e05/session-state', $member);
    request('/login', $member, 200, [
        '_token' => $guest['token'],
        'email' => $case['owner_email'],
        'password' => 'password',
        'remember' => true,
    ]);
    [$memberSession] = request('/_e05/session-state', $member);
    verify($memberSession['guard_user'] === $case['owner'] && ! $memberSession['inspecting']);

    $inspector = [];
    [$guest] = request('/_e05/session-state', $inspector);
    request('/login', $inspector, 200, [
        '_token' => $guest['token'],
        'email' => $case['platform_email'],
        'password' => 'password',
    ]);
    [$administratorSession] = request('/_e05/session-state', $inspector);
    verify($administratorSession['guard_user'] === $case['platform']);
    request('/saas/contas/'.$case['account'].'/inspecao', $inspector, 200, [
        '_token' => $administratorSession['token'],
        'user_id' => $case['owner'],
        'mode' => 'edit',
    ]);

    [$inspection, $metrics] = request('/_e05/session-state', $inspector);
    verify($metrics['worker'] === $worker);
    verify($inspection['guard_user'] === $case['owner'] && $inspection['session_user'] === $case['owner']);
    verify($inspection['inspecting'] && $inspection['id'] !== $memberSession['id']);
    [$page] = request('/conta', $inspector);
    verify($page['auth']['user']['id'] === $case['owner'] && $page['auth']['inspection_mode'] === 'edit');
    $previousCookies = $inspector;

    require_once '/app/vendor/autoload.php';
    $application = require '/app/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();

    $administrator = User::findOrFail($case['platform']);
    $owner = User::findOrFail($case['owner']);
    $account = Client::findOrFail($case['account']);
    $rememberToken = $owner->getRememberToken();
    $accountName = $account->name;
    verify(DB::table('sessions')->where('id', $inspection['id'])->where('user_id', $owner->id)->exists());

    $administrator->forceFill(['is_saas_admin' => false])->saveOrFail();

    [, $metrics] = request('/conta', $inspector, 401);
    verify($metrics['worker'] === $worker);
    [$ended, $metrics] = request('/_e05/session-state', $inspector);
    verify($metrics['worker'] === $worker);
    verify($ended['guard_user'] === null && $ended['session_user'] === null && ! $ended['inspecting']);
    verify($ended['id'] !== $inspection['id'] && $ended['token'] !== $inspection['token']);
    verify(! DB::table('sessions')->where('id', $inspection['id'])->exists());

    request('/conta', $inspector, 401);
    request('/conta/estabelecimentos', $inspector, 401);
    request('/conta', $inspector, 401, [
        '_token' => $ended['token'],
        'name' => 'Alteração pela sessão encerrada',
    ], 'PATCH');
    request('/conta/inspecao/encerrar', $inspector, 401, ['_token' => $ended['token']]);
    verify($account->refresh()->name === $accountName);

    // O cookie guardado antes do encerramento também perde acesso após a reativação do inspetor.
    $administrator->forceFill(['is_saas_admin' => true])->saveOrFail();
    request('/conta', $inspector, 401);
    $staleCookies = $previousCookies;
    request('/conta', $staleCookies, 401);
    $staleCookies = $previousCookies;
    [$replayed, $metrics] = request('/_e05/session-state', $staleCookies);
    verify($metrics['worker'] === $worker);
    verify($replayed['guard_user'] === null && $replayed['session_user'] === null && ! $replayed['inspecting']);
    request('/conta/estabelecimentos', $staleCookies, 401);

    [$memberAfter, $metrics] = request('/_e05/session-state', $member);
    verify($metrics['worker'] === $worker);
    verify($memberAfter === $memberSession);
    [$page] = request('/conta', $member);
    verify($page['auth']['user']['id'] === $owner->id && $page['auth']['inspection_mode'] === null);
    request('/conta/estabelecimentos', $member);
    request('/conta', $member, 200, ['_token' => $memberAfter['token'], 'name' => 'Alteração do titular'], 'PATCH');
    verify($account->refresh()->name === 'Alteração do titular');
    verify($owner->refresh()->getRememberToken() === $rememberToken);

    echo json_encode([
        'e05_inspection_revoked' => true,
        'old_session_destroyed' => true,
        'old_cookie_rejected_after_privilege_restoration' => true,
        'independent_session_preserved' => true,
        'worker' => $worker,
    ], JSON_THROW_ON_ERROR).PHP_EOL;
}
