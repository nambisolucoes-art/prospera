<?php

// Desempenho integrado por tela, com as métricas do OctaneMetrics (consultas, repetidas, permissão, memória).
use Illuminate\Support\Facades\DB;

require __DIR__.'/http.php';

$label = getenv('E10_LABEL') ?: 'base';
$j = 'Desempenho '.$label;
$samplesPerEndpoint = (int) (getenv('E10_REQUESTS') ?: 100);
$accounts = state()['accounts'];
$A = $accounts['A'];
$B = $accounts['B'];
$a = '/contas/'.$A['code'];
$units = implode(',', [$A['primary_code'], $A['guanambi_code'], $A['brumado_code'], $A['company2_primary_code']]);
$ana = login('ana@gartan.e10.test');
$manager = login('unidade@gartan.e10.test');
$platform = login('platform@e10.test', 'password', 'plataforma');

$endpoints = [
    'Dashboard da conta' => [$ana, $a],
    'Seleção de contas' => [$ana, '/contas'],
    'Resolução da conta (padrão da Área de Trabalho)' => [$ana, $a.'/area-de-trabalho/padrao'],
    'Área de Trabalho consolidada' => [$ana, $a.'/estabelecimento/todos?unidades='.$units],
    'Empresas' => [$ana, $a.'/empresas'],
    'Estabelecimentos' => [$ana, $a.'/estabelecimentos'],
    'Clientes (consolidado)' => [$ana, $a.'/estabelecimento/todos/clientes?unidades='.$units],
    'Fornecedores (consolidado)' => [$ana, $a.'/estabelecimento/todos/fornecedores?unidades='.$units],
    'Usuários' => [$ana, $a.'/usuarios'],
    'Cargos' => [$ana, $a.'/cargos'],
    'Etiquetas' => [$ana, $a.'/etiquetas'],
    'Auditoria (conta inteira)' => [$ana, $a.'/auditoria'],
    'Auditoria (busca textual)' => [$ana, $a.'/auditoria?search=Registro'],
    'Auditoria (unidade)' => [$manager, $a.'/auditoria'],
    'Plataforma: contas' => [$platform, '/saas/contas'],
];

function percentile(array $values, float $rank): float
{
    sort($values);

    return round($values[max(0, (int) ceil(count($values) * $rank) - 1)], 2);
}

$summary = [];
foreach ($endpoints as $name => [$browser, $path]) {
    $browser->call('GET', $path);
    $values = [];
    for ($i = 0; $i < $samplesPerEndpoint; $i++) {
        $response = $browser->call('GET', $path);
        if ($response['status'] !== 200) {
            check($j, $name.' respondeu 200', false, $response['status']);

            break;
        }
        $values[] = $response['metrics'];
    }
    if ($values === []) {
        continue;
    }
    $summary[$name] = [
        'caminho' => $path,
        'amostras' => count($values),
        'mediana_ms' => percentile(array_column($values, 'http_ms'), .5),
        'p95_ms' => percentile(array_column($values, 'http_ms'), .95),
        'db_ms_mediana' => percentile(array_column($values, 'db_ms'), .5),
        'queries' => array_values(array_unique(array_column($values, 'queries'))),
        'queries_permissao' => array_values(array_unique(array_column($values, 'permission_queries'))),
        'queries_atribuicao' => array_values(array_unique(array_column($values, 'assignment_queries'))),
        'duplicadas_max' => max(array_column($values, 'duplicates')),
        'sql_repetido' => array_values(array_unique(array_merge(...array_map(fn ($v) => $v['duplicate_sql'] ?? [], $values)))),
        'pico_incremental_max' => max(array_column($values, 'peak_delta_bytes')),
    ];
}

// N+1: o número de consultas não cresce com as linhas da página.
$pages = [];
foreach (['Clientes (consolidado)', 'Fornecedores (consolidado)', 'Usuários', 'Etiquetas', 'Auditoria (conta inteira)', 'Empresas', 'Estabelecimentos'] as $name) {
    [$browser, $path] = $endpoints[$name];
    $glue = str_contains($path, '?') ? '&' : '?';
    // Mínimo de três medições: a gravação periódica da sessão acrescenta uma consulta em algumas requisições.
    $measure = fn (int $size) => min(array_map(
        fn () => $browser->call('GET', $path.$glue.'per_page='.$size)['metrics']['queries'] ?? PHP_INT_MAX,
        range(1, 3),
    ));
    $pages[$name] = [$measure(5), $measure(50)];
    check($j, 'Sem N+1 em '.$name.' (5 e 50 por página)', $pages[$name][0] === $pages[$name][1], $pages[$name]);
}

check($j, 'Consultas de permissão constantes (no máximo 1 por requisição de conta)',
    collect($summary)->every(fn ($s) => max($s['queries_permissao']) <= 1), collect($summary)->map->queries_permissao);
check($j, 'Nenhuma assinatura SQL repetida nas leituras', collect($summary)->every(fn ($s) => $s['duplicadas_max'] === 0),
    collect($summary)->filter(fn ($s) => $s['duplicadas_max'] > 0)->map->sql_repetido);

file_put_contents('/tmp/e10-results/desempenho-'.$label.'.json', json_encode([
    'rotulo' => $label,
    'eventos_auditoria' => DB::table('audit_records')->count(),
    'eventos_conta_a' => DB::table('audit_records')->where('account_id', $A['id'])->count(),
    'resumo' => $summary,
    'paginas_5_50' => $pages,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

finish('14-desempenho-'.$label);
