<?php

// Dispara uma requisição de cada tela com auto_explain ligado no PostgreSQL do laboratório.
require __DIR__.'/http.php';

$A = state()['accounts']['A'];
$a = '/contas/'.$A['code'];
$units = implode(',', [$A['primary_code'], $A['guanambi_code'], $A['brumado_code'], $A['company2_primary_code']]);
$ana = login('ana@gartan.e10.test');
foreach ([
    '/contas',
    $a.'/area-de-trabalho/padrao',
    $a.'/estabelecimento/todos/clientes?unidades='.$units.'&search=Marta',
    $a.'/estabelecimento/todos/fornecedores?unidades='.$units,
    $a.'/usuarios',
] as $path) {
    echo $path, ' ', $ana->call('GET', $path)['status'], PHP_EOL;
}
