<?php

// Convites com a chave prospera.multiple_accounts desligada: instância própria (porta 8002) sobre o mesmo banco.
use Illuminate\Support\Facades\DB;

require __DIR__.'/http.php';

$j = 'J4 chave desligada';
$accounts = state()['accounts'];
$roles = state()['roles'];
$A = $accounts['A'];
$B = $accounts['B'];
$a = '/contas/'.$A['code'];
$b = '/contas/'.$B['code'];
$on = 'http://127.0.0.1:8000';
$off = 'http://127.0.0.1:8002';
$run = run_id();

// Com a chave ligada, a B emite um convite pendente para uma identidade nova da A.
putenv('HARNESS_URL='.$on);
$recipient = 'chave-'.$run.'@gartan.e10.test';
$ana = login('ana@gartan.e10.test');
$ana->call('POST', $a.'/usuarios', ['name' => 'Chave '.$run, 'email' => $recipient, 'mode' => 'manual', 'password' => E10_PASSWORD,
    'password_confirmation' => E10_PASSWORD, 'role_id' => $roles['A']['Somente Ajuda'], 'scope' => 'tenant']);
$bruno = login('bruno@outra.e10.test');
$node = ['scope' => 'establishment', 'company_id' => $B['company'], 'establishment_id' => $B['primary']];
$bruno->call('POST', $b.'/usuarios', ['name' => 'Chave', 'email' => $recipient, 'mode' => 'invite', 'role_id' => $roles['B']['Operador B'], ...$node]);
$token = substr((string) mail_link($recipient, '#/convites/[A-Za-z0-9]{64}#'), -64);
check($j, 'Convite pendente emitido com a chave ligada', strlen($token) === 64);

// Instância com a chave desligada.
putenv('HARNESS_URL='.$off);
$anaOff = login('ana@gartan.e10.test', label: 'ana-off');
foreach ([$A['code'], $B['code'], $accounts['C']['code']] as $code) {
    status($j, 'Vínculo existente continua abrindo '.$code, $anaOff->call('GET', '/contas/'.$code.'/etiquetas'), 200);
}
$selection = $anaOff->call('GET', '/contas');
check($j, 'Seleção com três vínculos não escolhe conta com a chave desligada', count($selection['body']['accounts'] ?? []) === 3
    && ! isset($selection['body']['redirect']));
$recipientOff = login($recipient, label: 'destinatario-off');
$show = $recipientOff->call('GET', '/convites/'.$token);
check($j, 'Convite informa que a associação está desligada', ($show['body']['invitation']['enabled'] ?? null) === false, $show['body']['invitation'] ?? null);
status($j, 'Aceite bloqueado com a chave desligada', $recipientOff->call('POST', '/convites/'.$token), 403);
check($j, 'Convite continua pendente e sem vínculo', DB::table('account_invitations')->where('token_hash', hash('sha256', $token))->value('status') === 'pending'
    && DB::table('account_memberships')->where('account_id', $B['id'])->where('user_id', DB::table('users')->where('email', $recipient)->value('id'))->doesntExist());
$brunoOff = login('bruno@outra.e10.test', label: 'bruno-off');
$before = DB::table('account_invitations')->count();
$issue = $brunoOff->call('POST', $b.'/usuarios', ['name' => 'Outra', 'email' => 'consulta@gartan.e10.test', 'mode' => 'invite',
    'role_id' => $roles['B']['Operador B'], ...$node]);
status($j, 'Emissão para identidade existente responde neutra com a chave desligada', $issue, 200);
check($j, 'Nenhum convite novo com a chave desligada', DB::table('account_invitations')->count() === $before);

// Religada (instância padrão), o mesmo convite é aceito normalmente.
putenv('HARNESS_URL='.$on);
$recipientOn = login($recipient, label: 'destinatario-on');
status($j, 'Mesmo convite aceito com a chave ligada', $recipientOn->call('POST', '/convites/'.$token), 200);

finish('12-chave-desligada');
