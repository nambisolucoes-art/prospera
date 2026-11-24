<?php

// Workers persistentes: fluxos integrados alternados entre identidades, contas, unidades, inspeção, escritas
// comerciais e auditoria. Cada resposta é conferida contra o próprio contexto; as métricas vêm do OctaneMetrics.
use Illuminate\Support\Facades\DB;

require __DIR__.'/http.php';

$j = 'Octane '.(getenv('E10_LABEL') ?: 'workers');
$accounts = state()['accounts'];
$commercial = state()['commercial'];
$A = $accounts['A'];
$B = $accounts['B'];
$a = '/contas/'.$A['code'];
$b = '/contas/'.$B['code'];
$iterations = (int) (getenv('E10_REQUESTS') ?: 300);
$ana = login('ana@gartan.e10.test', label: 'ana');
$olga = login('olga@outra.e10.test');
$carlos = login('carlos@gartan.e10.test');
$manager = login('unidade@gartan.e10.test');
$bruno = login('bruno@outra.e10.test');
$consult = login('consulta@gartan.e10.test');
$platform = login('platform@e10.test', 'password', 'plataforma');
$ids = fn (string $email) => DB::table('users')->where('email', $email)->value('id');
$anaId = $ids('ana@gartan.e10.test');
$carlosId = $ids('carlos@gartan.e10.test');
$platformId = $ids('platform@e10.test');
$a1Units = implode(',', [$A['primary_code'], $A['guanambi_code'], $A['brumado_code']]);

$samples = [];
$workers = [];
$unexpected = [];
$sample = function (string $label, array $response, int|array $expected, ?callable $assert = null) use (&$samples, &$workers, &$unexpected): void {
    $ok = in_array($response['status'], (array) $expected, true);
    if ($ok && $assert !== null) {
        $ok = (bool) $assert($response['body'] ?? [], $response['raw']);
    }
    if (! $ok) {
        $unexpected[] = ['label' => $label, 'status' => $response['status'], 'body' => mb_substr($response['raw'], 0, 200)];
    }
    if (isset($response['metrics']['worker'])) {
        $workers[$response['metrics']['worker']][] = $response['metrics']['start_bytes'];
    }
    $samples[$label][] = $response['metrics'];
};
$base = fn (string $expected) => fn ($body) => ($body['account_context']['base'] ?? null) === $expected;
$hasCreate = fn (bool $expected) => fn ($body) => in_array('account.tags.create', $body['auth']['permissions'] ?? [], true) === $expected;
$noForeign = fn (array $words) => fn ($body, $raw) => collect($words)->every(fn ($w) => ! str_contains($raw, $w));
$expectedLocal = DB::table('audit_records')->where('account_id', $A['id'])->where('establishment_id', $A['brumado'])->count();

for ($i = 0; $i < $iterations; $i++) {
    switch ($i % 12) {
        case 0:
            $sample('A etiquetas (administradora)', $ana->call('GET', $a.'/etiquetas'), 200,
                fn ($body) => $base($a)($body) && $hasCreate(true)($body));
            break;
        case 1:
            $sample('B etiquetas (mesma identidade, operadora)', $ana->call('GET', $b.'/etiquetas?search=E&page=1'), 200,
                fn ($body, $raw) => $base($b)($body) && $hasCreate(false)($body) && $noForeign(['Cliente VIP', 'Gartan Comércio'])($body, $raw));
            break;
        case 2:
            $sample('B etiquetas (uma unidade)', $olga->call('GET', $b.'/etiquetas'), 200,
                fn ($body) => $base($b)($body) && $hasCreate(false)($body));
            break;
        case 3:
            $sample('A clientes consolidado (empresa A1)', $carlos->call('GET', $a.'/estabelecimento/todos/clientes?unidades='.$a1Units), 200,
                fn ($body, $raw) => collect($body['relations']['data'] ?? [])->every(fn ($r) => $r['company']['code'] === $A['company_code'])
                    && ! str_contains($raw, 'Marta na B'));
            break;
        case 4:
            $sample('A auditoria local (unidade)', $manager->call('GET', $a.'/auditoria?per_page=25'), 200,
                fn ($body) => ($body['records']['total'] ?? -1) === $expectedLocal);
            break;
        case 5:
            $sample('B usuarios (administrador)', $bruno->call('GET', $b.'/usuarios'), 200, $noForeign(['Grupo Gartan', 'Vendas A']));
            break;
        case 6:
            $sample('A empresas (consulta)', $consult->call('GET', $a.'/empresas'), 200, $base($a));
            $sample('A auditoria negada (consulta)', $consult->call('GET', $a.'/auditoria'), 403);
            break;
        case 7:
            $sample('Selecao de contas', $ana->call('GET', '/contas'), 200, fn ($body) => count($body['accounts'] ?? []) >= 3 && $body['account_context'] === null);
            $sample('Conta inexistente', $ana->call('GET', '/contas/nao-existe'), 404);
            break;
        case 8:
            $sample('A area de trabalho padrao', $ana->call('GET', $a.'/area-de-trabalho/padrao'), 200, fn ($body) => is_array($body['codes'] ?? null));
            $sample('B area de trabalho padrao', $ana->call('GET', $b.'/area-de-trabalho/padrao'), 200);
            break;
        case 9:
            $phone = '7736'.str_pad((string) $i, 6, '0', STR_PAD_LEFT);
            $sample('A escrita comercial (cliente)', $ana->call('PATCH', $a.'/empresa/'.$A['company_code'].'/clientes/'.$commercial['customerA1'], ['phone' => $phone]), [200, 204]);
            $event = DB::table('audit_records')->where('entity_type', 'person')->where('entity_id', $commercial['person'])->orderByDesc('id')->first();
            if ((int) $event->actor_id !== $anaId || $event->represented_user_id !== null || $event->inspection_id !== null) {
                $unexpected[] = ['label' => 'autoria herdada', 'event' => $event->id];
            }
            $sample('A etiqueta invalida (422)', $ana->call('POST', $a.'/etiquetas', ['name' => '', 'module' => 'customers', 'color' => 'x']), 422);
            break;
        case 10:
            if ($i % 60 === 10) {
                $sample('Inspecao inicio', $platform->call('POST', '/saas/contas/'.$A['id'].'/inspecao', ['user_id' => $carlosId, 'mode' => 'edit']), 200);
                $sample('Inspecao leitura', $platform->call('GET', $a.'/empresas'), 200, $base($a));
                $sample('Inspecao troca de conta', $platform->call('GET', $b.'/etiquetas'), 404);
                $whatsapp = '7798'.str_pad((string) $i, 6, '0', STR_PAD_LEFT);
                $sample('Inspecao escrita', $platform->call('PATCH', $a.'/empresa/'.$A['company_code'].'/clientes/'.$commercial['customerA1'], ['whatsapp' => $whatsapp]), [200, 204]);
                $event = DB::table('audit_records')->where('entity_type', 'person')->where('entity_id', $commercial['person'])->orderByDesc('id')->first();
                if ((int) $event->actor_id !== $platformId || (int) $event->represented_user_id !== $carlosId) {
                    $unexpected[] = ['label' => 'autoria da inspeção', 'event' => $event->id];
                }
                $sample('Inspecao encerramento', $platform->call('POST', '/inspecao/encerrar'), 200);
            } else {
                $sample('Plataforma contas', $platform->call('GET', '/saas/contas'), 200, fn ($body) => ($body['account_context'] ?? null) === null);
            }
            break;
        default:
            $sample('B clientes (administrador)', $bruno->call('GET', $b.'/estabelecimento/'.$B['primary_code'].'/clientes'), 200,
                $noForeign(['Marta Queiroz', 'Gartan']));
    }
}

// Revogação sem reinício do worker: retira e devolve a listagem de etiquetas do cargo da Olga.
$roleId = state()['roles']['B']['Operador B'];
$permission = DB::table('permissions')->where('slug', 'account.tags.list')->value('id');
DB::table('permission_role')->where('role_id', $roleId)->where('permission_id', $permission)->delete();
$sample('Revogacao imediata', $olga->call('GET', $b.'/etiquetas'), 403);
DB::table('permission_role')->insert(['role_id' => $roleId, 'permission_id' => $permission]);
$sample('Concessao imediata', $olga->call('GET', $b.'/etiquetas'), 200);

function percentile(array $values, float $rank): float
{
    sort($values);

    return round($values[max(0, (int) ceil(count($values) * $rank) - 1)], 2);
}

$summary = [];
foreach ($samples as $label => $values) {
    $summary[$label] = [
        'amostras' => count($values),
        'mediana_ms' => percentile(array_column($values, 'http_ms'), .5),
        'p95_ms' => percentile(array_column($values, 'http_ms'), .95),
        'queries' => array_values(array_unique(array_column($values, 'queries'))),
        'queries_permissao' => array_values(array_unique(array_column($values, 'permission_queries'))),
        'duplicadas_max' => max(array_column($values, 'duplicates') ?: [0]),
        'db_ms_mediana' => percentile(array_column($values, 'db_ms') ?: [0], .5),
        'pico_incremental_max' => max(array_column($values, 'peak_delta_bytes') ?: [0]),
        'sql_repetido' => array_values(array_unique(array_merge(...array_map(fn ($v) => $v['duplicate_sql'] ?? [], $values)))),
    ];
}
$memory = [];
foreach ($workers as $worker => $starts) {
    $memory[$worker] = [
        'requisicoes' => count($starts),
        'inicio' => $starts[0],
        'fim' => end($starts),
        'max' => max($starts),
        'min' => min($starts),
        // Média da memória inicial a cada bloco de 500 requisições do worker: mostra a tendência.
        'media_por_bloco_500' => array_map(fn ($chunk) => (int) (array_sum($chunk) / count($chunk)), array_chunk($starts, 500)),
    ];
}

check($j, 'Nenhuma resposta inesperada em '.array_sum(array_map('count', $samples)).' requisições', $unexpected === [], array_slice($unexpected, 0, 10));
check($j, 'Workers observados', $workers !== [], count($workers));
file_put_contents('/tmp/e10-results/octane-'.(getenv('E10_LABEL') ?: 'workers').'.json', json_encode([
    'requisicoes' => array_sum(array_map('count', $samples)),
    'iteracoes' => $iterations,
    'workers' => count($workers),
    'memoria_inicio_por_worker' => $memory,
    'resumo' => $summary,
    'inesperadas' => $unexpected,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

finish('13-octane-'.(getenv('E10_LABEL') ?: 'workers'));
