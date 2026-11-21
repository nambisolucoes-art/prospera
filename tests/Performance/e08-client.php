<?php

// E08: medição da consulta de auditoria e prova de autoria e isolamento em worker FrankenPHP real.
// Uso: php e08-client.php measure <volume> | write | isolation | endurance <lote> | race-setup | race <n> | race-check. E08_PHASE=baseline omite os recursos da E08.
use App\Domains\Auditoria\Domain\Models\AuditRecord;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

require_once __DIR__.'/organization-http.php';
require_once '/app/vendor/autoload.php';
$application = require '/app/bootstrap/app.php';
$application->make(Kernel::class)->bootstrap();

$data = json_decode(file_get_contents('/tmp/prospera-e08-scenarios.json'), true, flags: JSON_THROW_ON_ERROR);
$after = getenv('E08_PHASE') !== 'baseline';
$mode = $argv[1] ?? 'measure';

function registrationPath(int $userId): string
{
    // O harness também roda sobre o checkpoint histórico para medir a linha de base da E08.
    if (Route::has('account.directory.people.store')) {
        return '/conta/pessoas';
    }

    $accountId = User::findOrFail($userId)->account_id;
    $company = DB::table('companies')->where('account_id', $accountId)->orderBy('id')->value('code');
    verify(is_string($company));

    return '/conta/empresa/'.$company.'/clientes';
}

function percentile(array $values, float $rank): float
{
    sort($values);

    return (float) $values[(int) max(0, ceil($rank * count($values)) - 1)];
}

function summary(array $samples): array
{
    $summary = ['samples' => count($samples)];

    foreach (['http_ms', 'db_ms', 'php_ms'] as $key) {
        $values = array_column($samples, $key);
        $summary[$key.'_median'] = round(percentile($values, .5), 2);
        $summary[$key.'_p95'] = round(percentile($values, .95), 2);
    }

    $summary['queries'] = array_values(array_unique(array_column($samples, 'queries')));
    $summary['duplicates'] = max(array_column($samples, 'duplicates'));
    $summary['peak_delta_kib_median'] = round(percentile(array_column($samples, 'peak_delta_bytes'), .5) / 1024, 1);

    return $summary;
}

// Condição normativa da seção 11.2, calculada sem a aplicação, para provar contagens sem vazamento.
function expectedTotal(int $accountId, int $userId, array $filters = []): int
{
    $tenant = DB::table('role_assignments as ra')
        ->join('account_memberships as m', 'm.id', '=', 'ra.membership_id')
        ->join('permission_role as pr', 'pr.role_id', '=', 'ra.role_id')
        ->join('permissions as p', 'p.id', '=', 'pr.permission_id')
        ->where('m.user_id', $userId)
        ->where('m.account_id', $accountId)
        ->where('p.slug', 'account.audit.list');

    $isTenant = (clone $tenant)->where('ra.scope', 'tenant')->exists();
    $companies = (clone $tenant)->where('ra.scope', 'company')->pluck('ra.company_id')->all();
    $units = (clone $tenant)->where('ra.scope', 'establishment')->pluck('ra.establishment_id')->all();

    $query = DB::table('audit_records')->where('account_id', $accountId);

    if (! $isTenant) {
        $query->where(fn ($nodes) => $nodes->whereIn('company_id', $companies ?: [0])
            ->orWhereIn('establishment_id', $units ?: [0]));
    }

    foreach ($filters as $column => $value) {
        $query->where($column, $value);
    }

    return $query->count();
}

if ($mode === 'measure') {
    $volume = (int) ($argv[2] ?? 0);
    $sessions = [];

    foreach ($data['scenarios'] as $name => $scenario) {
        $cookies = [];
        request('/_e03/session/'.$scenario['user'], $cookies);
        [$first] = request('/conta/auditoria?per_page=20', $cookies);
        $total = $first['records']['total'];
        verify($total === expectedTotal($data['account'], $scenario['user']));

        $lastPage = max(1, (int) ceil($total / 20));
        $paths = [
            'lista' => '/conta/auditoria?per_page=20',
            'busca' => '/conta/auditoria?per_page=20&search='.rawurlencode('Registro sintético 12'),
            'periodo' => '/conta/auditoria?per_page=20&from=2026-10-01&until=2026-10-31',
            'autor' => '/conta/auditoria?per_page=20&actor=1000007',
            'pagina-profunda' => '/conta/auditoria?per_page=20&page='.min(500, $lastPage),
        ];

        if ($after) {
            $paths['empresa'] = '/conta/auditoria?per_page=20&company='.$data['establishment_company'];
            $paths['estabelecimento'] = '/conta/auditoria?per_page=20&establishment='.$data['establishment'];
        }

        if ($scenario['view'] && $first['records']['data'] !== []) {
            $paths['detalhe'] = '/conta/auditoria/'.$first['records']['data'][0]['id'].'/visualizar';
        }

        foreach ($paths as $label => $path) {
            $samples = [];

            for ($sample = 0; $sample < 11; $sample++) {
                [$body, $metrics] = request($path, $cookies);

                if ($label === 'autor') {
                    verify($body['records']['total'] === expectedTotal(
                        $data['account'],
                        $scenario['user'],
                        ['actor_id' => 1000007],
                    ));
                }

                if ($label === 'empresa') {
                    verify($body['records']['total'] === expectedTotal(
                        $data['account'],
                        $scenario['user'],
                        ['company_id' => $data['establishment_company']],
                    ));
                }

                if ($sample !== 0) {
                    $samples[] = $metrics;
                }
            }

            echo json_encode([
                'e08' => 'consulta',
                'volume' => $volume,
                'perfil' => $name,
                'caso' => $label,
                'total' => $total,
                ...summary($samples),
            ], JSON_THROW_ON_ERROR).PHP_EOL;
        }
    }
}

if ($mode === 'write') {
    $owner = $data['scenarios']['integral']['user'];
    $cookies = [];
    [$session] = request('/_e03/session/'.$owner, $cookies);
    $samples = [];
    $path = registrationPath($owner);

    for ($sample = 0; $sample < 31; $sample++) {
        $before = AuditRecord::count();
        [, $metrics] = request($path, $cookies, [200, 204], [
            '_token' => $session['token'],
            'name' => 'Pessoa de escrita '.$sample.' '.bin2hex(random_bytes(3)),
        ]);
        verify(AuditRecord::count() > $before);

        if ($sample !== 0) {
            $samples[] = $metrics;
        }
    }

    echo json_encode([
        'e08' => 'escrita',
        'caso' => 'cadastro de pessoa',
        'audit_records' => AuditRecord::count(),
        ...summary($samples),
    ], JSON_THROW_ON_ERROR).PHP_EOL;
}

if ($mode === 'isolation') {
    // Alterna escritas de contas, autores e inspeção no mesmo worker e confere cada evento produzido.
    $platform = User::findOrFail($data['platform']);
    $authors = [$data['scenarios']['integral']['user'], ...$data['other_owners']];
    $sessions = [];
    $worker = null;
    $memory = [];
    $checked = 0;

    foreach ($authors as $author) {
        $cookies = [];
        [$session] = request('/_e03/session/'.$author, $cookies);
        $sessions[$author] = [$cookies, $session['token'], registrationPath($author)];
    }

    $inspector = [];
    [$guest] = request('/_e05/session-state', $inspector);
    request('/login', $inspector, 200, [
        '_token' => $guest['token'],
        'email' => $data['platform_email'],
        'password' => 'password',
    ]);

    $represented = User::findOrFail($authors[1]);
    $inspectedAccount = $represented->account_id;
    [$state] = request('/_e05/session-state', $inspector);
    request('/saas/contas/'.$inspectedAccount.'/inspecao', $inspector, 200, [
        '_token' => $state['token'],
        'user_id' => $represented->id,
        'mode' => 'edit',
    ]);
    [$state] = request('/_e05/session-state', $inspector);
    $inspectionToken = $state['token'];
    $inspectionPath = registrationPath($represented->id);

    for ($round = 0; $round < 300; $round++) {
        $inspecting = $round % 5 === 4;
        $author = $authors[$round % count($authors)];
        $name = 'Isolamento E08 '.$round.' '.bin2hex(random_bytes(3));

        if ($inspecting) {
            [, $metrics] = request($inspectionPath, $inspector, [200, 204], ['_token' => $inspectionToken, 'name' => $name]);
        } else {
            [, $metrics] = request($sessions[$author][2], $sessions[$author][0], [200, 204], [
                '_token' => $sessions[$author][1],
                'name' => $name,
            ]);
        }

        $worker ??= $metrics['worker'];
        verify($metrics['worker'] === $worker);
        $memory[] = $metrics['start_bytes'];

        $event = AuditRecord::where('entity_type', 'person')->where('entity_name', $name)->sole();

        if ($inspecting) {
            verify((int) $event->actor_id === $platform->id && $event->actor_name === $platform->name);
            verify((int) $event->represented_user_id === $represented->id);
            verify((int) $event->account_id === (int) $inspectedAccount && $event->context === 'platform-account');

            if ($after) {
                verify($event->represented_user_name === $represented->name && $event->inspection_id !== null);
            }
        } else {
            $user = User::findOrFail($author);
            verify((int) $event->actor_id === $user->id && $event->actor_name === $user->name);
            verify($event->represented_user_id === null && (int) $event->account_id === (int) $user->account_id);
            verify($event->context === 'account');

            if ($after) {
                verify($event->represented_user_name === null && $event->inspection_id === null);
            }
        }

        verify($event->company_id === null && $event->establishment_id === null);

        // Leituras negadas e de outra conta entre as escritas não podem deixar contexto residual.
        request('/conta/auditoria/999999999/visualizar', $sessions[$author][0], 404);
        $checked++;
    }

    // Revogação durante a inspeção: a requisição seguinte encerra a sessão e não grava como o representado.
    $platform->forceFill(['is_saas_admin' => false])->saveOrFail();
    request($inspectionPath, $inspector, 401, ['_token' => $inspectionToken, 'name' => 'Após revogação']);
    verify(! AuditRecord::where('entity_name', 'Após revogação')->exists());

    if ($after) {
        $ended = AuditRecord::where('event', 'inspection-ended')
            ->where('actor_id', $platform->id)
            ->latest('id')
            ->firstOrFail();
        verify((int) $ended->represented_user_id === $represented->id && isset($ended->changes['ending']));
    }

    $platform->forceFill(['is_saas_admin' => true])->saveOrFail();
    $owner = $authors[0];
    request($sessions[$owner][2], $sessions[$owner][0], [200, 204], ['_token' => $sessions[$owner][1], 'name' => 'Depois E08']);
    $last = AuditRecord::where('entity_name', 'Depois E08')->sole();
    verify((int) $last->actor_id === (int) $owner && $last->represented_user_id === null && $last->context === 'account');

    echo json_encode([
        'e08' => 'isolamento',
        'escritas_verificadas' => $checked,
        'worker' => $worker,
        'memoria_inicio_primeira' => $memory[0],
        'memoria_inicio_ultima' => end($memory),
        'memoria_inicio_maxima' => max($memory),
    ], JSON_THROW_ON_ERROR).PHP_EOL;
}

if ($mode === 'endurance') {
    // Carga sequencial prolongada: três perfis de duas contas, filtros, detalhe, páginas e acessos negados.
    $profiles = [
        $data['scenarios']['integral']['user'],
        $data['scenarios']['empresa']['user'],
        $data['other_owners'][0],
    ];
    $sessions = [];

    foreach ($profiles as $profile) {
        $cookies = [];
        [$session] = request('/_e03/session/'.$profile, $cookies);
        [$page] = request('/conta/auditoria?per_page=20', $cookies);
        $sessions[] = [$cookies, $session['token'], $page['records']['data'][0]['id']];
    }

    $foreign = $sessions[0][2];
    $statuses = [];
    $samples = [];
    $start = [];

    for ($index = 0; $index < 450; $index++) {
        [$cookies, $token, $own] = $sessions[$index % 3];
        $case = $index % 9;
        $expected = 200;
        $path = match ($case) {
            0 => '/conta/auditoria?per_page=20',
            1 => '/conta/auditoria?per_page=20&page=50',
            2 => '/conta/auditoria?per_page=20&from=2026-10-01&until=2026-10-31',
            3 => '/conta/auditoria?per_page=20&search='.rawurlencode('Registro sintético 3'),
            4 => '/conta/auditoria/'.$own.'/visualizar',
            5 => '/conta/auditoria?per_page=20&actor=1000003',
            6 => '/conta/auditoria?per_page=20&event=updated',
            7 => '/conta/auditoria/999999999/visualizar',
            default => '/conta/auditoria/'.$foreign.'/visualizar',
        };

        if ($case === 7 || ($case === 8 && $index % 3 === 2)) {
            $expected = 404;
        }

        // O evento da conta só aparece para a empresa quando pertence a ela.
        if ($case === 8 && $index % 3 === 1) {
            $expected = [200, 404];
        }

        [, $metrics, $status] = request($path, $cookies, $expected);
        $statuses[$status] = ($statuses[$status] ?? 0) + 1;
        $samples[] = $metrics;
        $start[] = $metrics['start_bytes'];
    }

    echo json_encode([
        'e08' => 'carga-prolongada',
        'lote' => (int) ($argv[2] ?? 0),
        'status' => $statuses,
        'workers' => array_values(array_unique(array_column($samples, 'worker'))),
        'memoria_inicio_mediana' => percentile($start, .5),
        'queries' => array_values(array_unique(array_column($samples, 'queries'))),
        ...summary($samples),
    ], JSON_THROW_ON_ERROR).PHP_EOL;
}

if ($mode === 'race-setup') {
    $cookies = [];
    [$session] = request('/_e03/session/'.$data['scenarios']['integral']['user'], $cookies);
    $path = registrationPath($data['scenarios']['integral']['user']);
    request($path, $cookies, [200, 204], ['_token' => $session['token'], 'name' => 'Concorrência E08']);
    $person = DB::table('people')->where('name', 'Concorrência E08')->value('id');
    $id = Route::has('account.directory.people.store')
        ? $person
        : DB::table('customers')->where('person_id', $person)->sole()->id;
    file_put_contents('/tmp/prospera-e08-race.json', json_encode([
        'person' => $person,
        'url' => $path.'/'.$id,
        'cookies' => $cookies,
        'token' => $session['token'],
    ], JSON_THROW_ON_ERROR));
}

if ($mode === 'race') {
    // Cada processo altera a mesma Pessoa com um nome próprio, ao mesmo tempo que os demais.
    $race = json_decode(file_get_contents('/tmp/prospera-e08-race.json'), true, flags: JSON_THROW_ON_ERROR);
    $cookies = $race['cookies'];
    [, , $status] = request($race['url'], $cookies, [200, 204, 409, 422], [
        '_token' => $race['token'],
        'name' => 'Concorrência E08 '.$argv[2],
    ], 'PATCH');
    echo json_encode(['e08' => 'corrida', 'processo' => (int) $argv[2], 'status' => $status], JSON_THROW_ON_ERROR).PHP_EOL;
}

if ($mode === 'race-check') {
    // Cada alteração efetivada tem exatamente um evento, e o antes de cada evento é o depois do anterior.
    $race = json_decode(file_get_contents('/tmp/prospera-e08-race.json'), true, flags: JSON_THROW_ON_ERROR);
    $events = AuditRecord::where('entity_type', 'person')
        ->where('entity_id', $race['person'])
        ->where('event', 'updated')
        ->orderBy('id')
        ->get();
    $previous = 'Concorrência E08';

    foreach ($events as $event) {
        verify($event->changes['name']['before'] === $previous);
        $previous = $event->changes['name']['after'];
    }

    $final = DB::table('people')->where('id', $race['person'])->value('name');
    verify($previous === $final);
    verify($events->pluck('operation_id')->unique()->count() === $events->count());

    echo json_encode([
        'e08' => 'corrida-verificada',
        'eventos' => $events->count(),
        'nome_final' => $final,
        'cadeia_coerente' => true,
    ], JSON_THROW_ON_ERROR).PHP_EOL;
}
