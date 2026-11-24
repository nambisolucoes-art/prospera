<?php

// Jornada 3: a mesma identidade global em várias contas, com autoridade independente em cada uma.
use Illuminate\Support\Facades\DB;

require __DIR__.'/http.php';

$j = 'J3 multicontas';
$accounts = state()['accounts'];
$A = $accounts['A'];
$B = $accounts['B'];
$C = $accounts['C'];
$D = $accounts['D'];
$a = '/contas/'.$A['code'];
$b = '/contas/'.$B['code'];
$c = '/contas/'.$C['code'];
$ana = login('ana@gartan.e10.test');
$anaId = DB::table('users')->where('email', 'ana@gartan.e10.test')->value('id');
$platform = login('platform@e10.test', 'password', 'plataforma');
$bruno = login('bruno@outra.e10.test');

// Seleção: três vínculos ativos, sem escolha automática.
$selection = $ana->call('GET', '/contas');
$codes = collect($selection['body']['accounts'] ?? [])->pluck('code')->sort()->values()->all();
$expected = collect([$A['code'], $B['code'], $C['code']])->sort()->values()->all();
check($j, 'Seleção lista exatamente as três contas da identidade', $codes === $expected, $codes);
check($j, 'Seleção com vários vínculos não escolhe conta', ! isset($selection['body']['redirect']) && $selection['status'] === 200);
check($j, 'Seleção informa o cargo de cada conta sem misturar contas', collect($selection['body']['accounts'])->every(
    fn ($x) => is_array($x['roles']) && $x['url'] === '/contas/'.$x['code'],
), $selection['body']['accounts']);

// Entrada automática com um único vínculo.
$carlos = login('carlos@gartan.e10.test');
$single = $carlos->call('GET', '/contas');
check($j, 'Vínculo único entra direto na conta', str_contains((string) ($single['body']['redirect'] ?? $single['location']), $A['code']),
    $single['body']['redirect'] ?? $single['location']);

// Autoridade por conta pela URL.
$permissions = fn (string $base): array => $ana->call('GET', $base.'/etiquetas')['body']['auth']['permissions'] ?? [];
$inA = $permissions($a);
$inB = $permissions($b);
$inC = $permissions($c);
check($j, 'Administradora na A', in_array('account.companies.create', $inA, true) && in_array('account.users.revoke', $inA, true));
check($j, 'Operadora na B, sem administração', ! in_array('account.companies.create', $inB, true)
    && ! in_array('account.users.list', $inB, true) && in_array('account.tags.list', $inB, true), $inB);
check($j, 'Administradora na C', in_array('account.companies.create', $inC, true));
$contextBase = fn (string $base) => $ana->call('GET', $base.'/etiquetas')['body']['account_context']['base'] ?? null;
check($j, 'Contexto da resposta segue a URL de cada requisição', $contextBase($a) === $a && $contextBase($b) === $b && $contextBase($a) === $a);

// A autoridade da A não vale na B: tentativas por URL e por ID.
$before = [DB::table('companies')->count(), DB::table('tags')->count(), DB::table('audit_records')->count()];
status($j, 'Criar empresa na B negado', $ana->call('POST', $b.'/empresas', ['name' => 'X', 'person_type' => 'company', 'document' => '55443322', 'active' => true]), 403);
status($j, 'Criar etiqueta na B negado', $ana->call('POST', $b.'/etiquetas', ['name' => 'X', 'module' => 'customers', 'color' => '#111111']), 403);
status($j, 'Usuários da B negado', $ana->call('GET', $b.'/usuarios'), 403);
status($j, 'Editar empresa da B negado', $ana->call('PATCH', $b.'/empresas/'.$B['company'], ['name' => 'X']), [403, 404]);
status($j, 'Empresa da B pela URL da A responde 404', $ana->call('GET', $a.'/empresas/'.$B['company'].'/visualizar'), 404);
status($j, 'Unidade da B pela URL da A responde 404', $ana->call('GET', $a.'/estabelecimento/'.$B['primary_code'].'/clientes'), 404);
status($j, 'Cargo da B pela URL da A responde 404', $ana->call('GET', $a.'/cargos/'.state()['roles']['B']['Operador B'].'/visualizar'), 404);
check($j, 'Negações entre contas sem efeito colateral', $before === [DB::table('companies')->count(), DB::table('tags')->count(), DB::table('audit_records')->count()]);

// Conta inexistente e conta sem vínculo não se distinguem.
$missing = $ana->call('GET', '/contas/conta-que-nao-existe');
$foreign = $ana->call('GET', '/contas/'.$D['code']);
status($j, 'Conta inexistente responde 404', $missing, 404);
status($j, 'Conta sem vínculo responde 404', $foreign, 404);
check($j, 'Inexistente e sem vínculo têm a mesma resposta', $missing['raw'] === $foreign['raw']);

// Endereço anterior: leitura segura sem eleger conta; escrita recusada.
$legacy = $ana->call('GET', '/conta/etiquetas');
check($j, 'Endereço /conta com vários vínculos não escolhe conta', ! str_contains((string) ($legacy['body']['redirect'] ?? $legacy['location'] ?? ''), '/contas/'.$A['code'].'/'), [$legacy['status'], $legacy['body']['redirect'] ?? $legacy['location']]);
status($j, 'Escrita em /conta recusada', $ana->call('POST', '/conta/etiquetas', ['name' => 'X', 'module' => 'customers', 'color' => '#111111']), [403, 404, 405]);

// Conta suspensa pela plataforma: só a C fica indisponível.
status($j, 'Plataforma inativa a Conta C', $platform->call('PATCH', '/saas/contas/'.$C['id'], ['name' => 'Filial Única Distribuidora', 'active' => false]), 200);
status($j, 'Conta inativa recusada', $ana->call('GET', $c), 403);
status($j, 'Conta A segue disponível', $ana->call('GET', $a), 200);
$selection = $ana->call('GET', '/contas');
$inactive = collect($selection['body']['accounts'])->firstWhere('code', $C['code']);
check($j, 'Seleção mostra a conta inativa sem endereço de entrada', $inactive !== null && $inactive['url'] === null, $inactive);
status($j, 'Plataforma reativa a Conta C', $platform->call('PATCH', '/saas/contas/'.$C['id'], ['name' => 'Filial Única Distribuidora', 'active' => true]), 200);
status($j, 'Conta C volta a abrir', $ana->call('GET', $c), 200);

// Vínculo suspenso só na B.
$anaB = DB::table('account_memberships')->where('account_id', $B['id'])->where('user_id', $anaId)->value('id');
status($j, 'Bruno suspende a Ana na B', $bruno->call('PATCH', $b.'/usuarios/'.$anaB.'/suspender'), 200);
status($j, 'Ana perde a B na requisição seguinte', $ana->call('GET', $b.'/etiquetas'), 403);
status($j, 'Ana mantém a A', $ana->call('GET', $a.'/empresas'), 200);
status($j, 'Ana mantém a C', $ana->call('GET', $c.'/empresas'), 200);
check($j, 'Suspensão na B não alterou a identidade nem os vínculos da A e C',
    DB::table('users')->where('id', $anaId)->value('access_enabled') === true
    && DB::table('account_memberships')->where('user_id', $anaId)->where('status', 'active')->count() === 2);
status($j, 'Bruno reativa a Ana na B', $bruno->call('PATCH', $b.'/usuarios/'.$anaB.'/reativar'), 200);
status($j, 'Ana volta a operar a B', $ana->call('GET', $b.'/etiquetas'), 200);

// Operador da B não infere os outros vínculos da Ana.
$users = $bruno->call('GET', $b.'/usuarios');
$raw = $users['raw'];
check($j, 'Usuários da B não revelam conta, cargos ou pessoa da A', ! str_contains($raw, 'Grupo Gartan') && ! str_contains($raw, 'Vendas A')
    && ! str_contains($raw, 'Filial Única'), null);
$anaRow = collect($users['body']['users']['data'] ?? [])->firstWhere('email', 'ana@gartan.e10.test');
check($j, 'Ana aparece na B só com a atribuição da B', $anaRow !== null && count($anaRow['assignments']) === 1
    && $anaRow['assignments'][0]['role'] === 'Operador B', $anaRow);
$selectionAudit = DB::table('audit_records')->where('account_id', $B['id'])->where('actor_id', $anaId)->pluck('account_id')->unique()->all();
check($j, 'Eventos da Ana na B pertencem só à B', $selectionAudit === [] || $selectionAudit === [$B['id']], $selectionAudit);

finish('05-multicontas');
