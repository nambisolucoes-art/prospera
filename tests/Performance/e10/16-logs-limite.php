<?php

// Limite de leitura de convites com sessão real: o token inexistente é sintético e só o formato é válido.
require __DIR__.'/http.php';

$j = 'J16 logs, limite de convites';
$ana = login('ana@gartan.e10.test');
$token = str_pad('SINTETICOLIMITE', 64, 'x');
$statuses = [];

for ($i = 0; $i < 25; $i++) {
    $statuses[] = $ana->call('GET', '/convites/'.$token)['status'];
}

check($j, 'Abertura de convite limitada com 429', in_array(429, $statuses, true), array_count_values($statuses));
check($j, 'Antes do limite responde 200', $statuses[0] === 200, $statuses[0]);

finish('16-logs-limite');
