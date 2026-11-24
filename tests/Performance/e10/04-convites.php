<?php

// Jornada 4: convites para identidades existentes e novas, com e-mail real no Mailpit isolado.
use Illuminate\Support\Facades\DB;

require __DIR__.'/http.php';

$j = 'J4 convites';
$accounts = state()['accounts'];
$roles = state()['roles'];
$A = $accounts['A'];
$B = $accounts['B'];
$C = $accounts['C'];
$a = '/contas/'.$A['code'];
$b = '/contas/'.$B['code'];
$c = '/contas/'.$C['code'];
$bruno = login('bruno@outra.e10.test');
$carla = login('carla@filial.e10.test');
$ana = login('ana@gartan.e10.test');
$tokenPattern = '#/convites/([A-Za-z0-9]{64})#';
$token = function (string $email) use ($tokenPattern): ?string {
    $link = mail_link($email, $tokenPattern);

    return $link === null ? null : substr($link, -64);
};
$invite = fn (Browser $operator, string $base, string $email, int $roleId, array $node = ['scope' => 'tenant']) => $operator->call('POST', $base.'/usuarios', [
    'name' => 'Convidado '.$email, 'email' => $email, 'mode' => 'invite', 'role_id' => $roleId, ...$node,
]);
$memberships = fn (): int => DB::table('account_memberships')->count();
$operatorB = DB::table('roles')->where('id', $roles['B']['Operador B'])->value('id');

// Resposta neutra: e-mail inexistente (identidade nova) e existente recebem a mesma mensagem.
$new = $invite($bruno, $b, 'nova.b@outra.e10.test', $operatorB, ['scope' => 'establishment', 'company_id' => $B['company'], 'establishment_id' => $B['primary']]);
$existing = $invite($bruno, $b, 'ana@gartan.e10.test', $operatorB, ['scope' => 'establishment', 'company_id' => $B['company'], 'establishment_id' => $B['primary']]);
status($j, 'Emissão para identidade nova', $new, 200);
status($j, 'Emissão para identidade existente', $existing, 200);
check($j, 'Respostas neutras idênticas (redirect e toast)', $new['raw'] === $existing['raw'], [$new['raw'], $existing['raw']]);
$toast = $bruno->call('GET', $b.'/usuarios')['body']['toast'] ?? null;
check($j, 'Mensagem pública "Solicitação de acesso registrada."', $toast === null || str_contains(json_encode($toast, JSON_UNESCAPED_UNICODE), 'Solicitação de acesso registrada'), $toast);

// Identidade nova: senha pendente bloqueia operar até a definição pelo Fortify.
$novaB = DB::table('users')->where('email', 'nova.b@outra.e10.test')->first();
check($j, 'Identidade nova criada com senha pendente', $novaB && $novaB->password_setup_pending);
$resetLink = mail_link('nova.b@outra.e10.test', '#http://[^\s"<]+/reset-password/[A-Za-z0-9]+\?email=[^\s"<&]+#');
check($j, 'Identidade nova recebe o link do Fortify, não um convite de conta', $resetLink !== null && $token('nova.b@outra.e10.test') === null);
preg_match('#/reset-password/([A-Za-z0-9]+)\?email=([^\s"<&]+)#', (string) $resetLink, $parts);
$guest = new Browser('nova-b');
status($j, 'Definição de senha da identidade nova', $guest->call('POST', '/reset-password', [
    'token' => $parts[1] ?? '', 'email' => urldecode($parts[2] ?? ''), 'password' => E10_PASSWORD, 'password_confirmation' => E10_PASSWORD,
]), [200, 302]);
$novaSession = login('nova.b@outra.e10.test');
status($j, 'Identidade nova opera a unidade após definir a senha', $novaSession->call('GET', $b.'/etiquetas'), 200);

// Identidade existente: o convite pendente não concede acesso.
$anaToken = $token('ana@gartan.e10.test');
check($j, 'Convite entregue à identidade existente com token de 64 caracteres', $anaToken !== null);
$pending = DB::table('account_invitations')->where('account_id', $B['id'])->where('email', 'ana@gartan.e10.test')->first();
check($j, 'Token persistido só como SHA-256', $pending && $pending->token_hash === hash('sha256', (string) $anaToken) && ! str_contains(json_encode($pending), (string) $anaToken));
status($j, 'Convite pendente não abre a Conta B', $ana->call('GET', $b), [403, 404]);

// Destinatário incorreto: Carlos não vê nem aceita o convite da Ana.
$carlos = login('carlos@gartan.e10.test');
$show = $carlos->call('GET', '/convites/'.$anaToken);
check($j, 'Outra identidade não vê os dados do convite', ($show['body']['invitation'] ?? null) === null, $show['status']);
$before = $memberships();
status($j, 'Outra identidade não aceita o convite', $carlos->call('POST', '/convites/'.$anaToken), [403, 404, 410]);
check($j, 'Convite continua pendente após tentativa alheia', DB::table('account_invitations')->where('id', $pending->id)->value('status') === 'pending' && $before === $memberships());

// Aceite legítimo cria um único vínculo e a pessoa da Conta B.
$show = $ana->call('GET', '/convites/'.$anaToken);
check($j, 'Destinatária vê conta e cargo do convite', ($show['body']['invitation']['account'] ?? null) === 'Outra Organização Serviços', $show['body']['invitation'] ?? null);
status($j, 'Aceite legítimo', $ana->call('POST', '/convites/'.$anaToken), 200);
$anaId = DB::table('users')->where('email', 'ana@gartan.e10.test')->value('id');
$membershipB = DB::table('account_memberships')->where('account_id', $B['id'])->where('user_id', $anaId)->get();
check($j, 'Exatamente um vínculo da Ana na Conta B, com pessoa da Conta B',
    $membershipB->count() === 1 && DB::table('people')->where('id', $membershipB[0]->person_id)->value('account_id') === $B['id']);
check($j, 'Atribuição do convite na unidade da Conta B',
    DB::table('role_assignments')->where('membership_id', $membershipB[0]->id)->where('establishment_id', $B['primary'])->exists());
status($j, 'Token reutilizado recusado', $ana->call('POST', '/convites/'.$anaToken), 410);
check($j, 'Reutilização não duplica vínculo', DB::table('account_memberships')->where('account_id', $B['id'])->where('user_id', $anaId)->count() === 1);

// Terceira conta: Carla (Conta C) convida a Ana como administradora.
$adminC = DB::table('roles')->where('account_id', $C['id'])->where('name', 'Administrador da conta')->value('id');
status($j, 'Conta C convida a Ana', $invite($carla, $c, 'ana@gartan.e10.test', $adminC), 200);
status($j, 'Ana aceita a Conta C', $ana->call('POST', '/convites/'.$token('ana@gartan.e10.test')), 200);

// Convite emitido duas vezes: o anterior é revogado.
$consultRole = $roles['B']['Operador B'];
$node = ['scope' => 'establishment', 'company_id' => $B['company'], 'establishment_id' => $B['primary']];
$invite($bruno, $b, 'consulta@gartan.e10.test', $consultRole, $node);
$first = $token('consulta@gartan.e10.test');
$invite($bruno, $b, 'consulta@gartan.e10.test', $consultRole, $node);
$second = $token('consulta@gartan.e10.test');
$states = DB::table('account_invitations')->where('account_id', $B['id'])->where('email', 'consulta@gartan.e10.test')->pluck('status')->sort()->values()->all();
check($j, 'Segunda emissão revoga a primeira', $first !== $second && $states === ['pending', 'revoked'], $states);
$consult = login('consulta@gartan.e10.test');
status($j, 'Token substituído recusado', $consult->call('POST', '/convites/'.$first), 410);

// Revogação explícita.
$pendingConsult = DB::table('account_invitations')->where('account_id', $B['id'])->where('email', 'consulta@gartan.e10.test')->where('status', 'pending')->value('id');
status($j, 'Revogar convite pela Conta B', $bruno->call('PATCH', $b.'/usuarios/convites/'.$pendingConsult.'/revogar'), 200);
status($j, 'Convite revogado recusado', $consult->call('POST', '/convites/'.$second), 410);
status($j, 'Conta A não revoga convite da Conta B', $ana->call('PATCH', $a.'/usuarios/convites/'.$pendingConsult.'/revogar'), [403, 404]);

// Expirado: o laboratório retrocede a validade de um convite legítimo (único ajuste direto no banco).
$invite($bruno, $b, 'henrique@gartan.e10.test', $consultRole, $node);
$expiredToken = $token('henrique@gartan.e10.test');
DB::table('account_invitations')->where('token_hash', hash('sha256', (string) $expiredToken))->update(['expires_at' => now()->subMinute()]);
$henrique = login('henrique@gartan.e10.test');
status($j, 'Convite expirado recusado', $henrique->call('POST', '/convites/'.$expiredToken), 410);
check($j, 'Expirado não cria vínculo', DB::table('account_memberships')->where('account_id', $B['id'])
    ->where('user_id', DB::table('users')->where('email', 'henrique@gartan.e10.test')->value('id'))->doesntExist());

// Vínculo existente (inclusive suspenso) não é reativado por convite: o suspenso da Conta A.
$adminA = $roles['A']['Administrador da conta'];
$suspendedBefore = DB::table('account_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')
    ->where('u.email', 'suspenso@gartan.e10.test')->where('m.account_id', $A['id'])->value('m.status');
status($j, 'Emissão para quem já tem vínculo suspenso responde neutro', $invite($ana, $a, 'suspenso@gartan.e10.test', $adminA), 200);
check($j, 'Vínculo suspenso não recebe convite nem é reativado',
    $token('suspenso@gartan.e10.test') === null
    && DB::table('account_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')
        ->where('u.email', 'suspenso@gartan.e10.test')->where('m.account_id', $A['id'])->value('m.status') === $suspendedBefore);

// Enumeração: token inexistente e formato inválido não diferenciam convites de outras contas.
$random = str_repeat('Z', 64);
$show = $consult->call('GET', '/convites/'.$random);
check($j, 'Token inexistente responde indisponível sem dados', ($show['body']['invitation'] ?? null) === null && $show['status'] === 200);
status($j, 'Token com formato inválido não é rota', $consult->call('GET', '/convites/abc'), 404);

// Cargo só de Ajuda (correção da E09): entra na Ajuda da conta.
$help = login('ajuda@gartan.e10.test');
$entry = $help->call('GET', $a);
check($j, 'Cargo só de Ajuda é levado à Central de Ajuda da conta',
    in_array($entry['status'], [200, 302], true) && str_contains((string) ($entry['body']['redirect'] ?? $entry['location'] ?? ''), '/ajuda'),
    ['status' => $entry['status'], 'redirect' => $entry['body']['redirect'] ?? $entry['location']]);
status($j, 'Ajuda da conta acessível ao cargo só de Ajuda', $help->call('GET', $a.'/ajuda'), 200);

// Auditoria e privacidade do convite.
$events = DB::table('audit_records')->where('account_id', $B['id'])->where('entity_type', 'account-invitation')->get();
check($j, 'Eventos de convite: criação, envio, aceite e revogação', collect(['created', 'sent', 'accepted', 'revoked'])->every(
    fn ($e) => $events->contains(fn ($x) => str_contains($x->event, $e) || str_contains((string) $x->changes, $e))
), $events->pluck('event')->unique()->values());
$tokens = array_filter([$anaToken, $first, $second, $expiredToken]);
$serialized = DB::table('audit_records')->pluck('changes')->implode(' ');
check($j, 'Nenhum token de convite no histórico', collect($tokens)->every(fn ($t) => ! str_contains($serialized, $t)));
check($j, 'Eventos de aceite com autoria da destinatária na conta B',
    DB::table('audit_records')->where('account_id', $B['id'])->where('entity_type', 'account-membership')->where('actor_id', $anaId)->exists());

remember('invite_tokens_used', ['ana_b' => hash('sha256', (string) $anaToken)]);
finish('04-convites');
