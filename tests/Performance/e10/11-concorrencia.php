<?php

// Concorrência real: processos independentes (fork) disparam requisições HTTP no mesmo instante contra
// FrankenPHP com dois workers e PostgreSQL. O estado final é conferido no banco, não só pelo status HTTP.
use Illuminate\Support\Facades\DB;

require __DIR__.'/http.php';

$j = 'Concorrencia';
$accounts = state()['accounts'];
$roles = state()['roles'];
$A = $accounts['A'];
$B = $accounts['B'];
$a = '/contas/'.$A['code'];
$b = '/contas/'.$B['code'];
$a1 = $a.'/empresa/'.$A['company_code'];
$a2 = $a.'/empresa/'.$A['company2_code'];

/**
 * Executa cada tarefa num processo próprio, liberadas juntas por um instante comum.
 *
 * @param  list<callable(): array>  $tasks
 * @return list<array>
 */
function race(array $tasks): array
{
    $start = microtime(true) + 0.6;
    $children = [];

    foreach ($tasks as $index => $task) {
        $file = '/tmp/e10-race-'.getmypid().'-'.$index.'.json';
        $pid = pcntl_fork();

        if ($pid === 0) {
            time_sleep_until($start);
            $result = ['started' => microtime(true)] + $task();
            file_put_contents($file, json_encode($result));
            // Sem destrutores: o processo filho não pode fechar a conexão PostgreSQL herdada do pai.
            posix_kill(getmypid(), SIGKILL);
        }

        $children[$pid] = $file;
    }

    $results = [];
    foreach ($children as $pid => $file) {
        pcntl_waitpid($pid, $status);
        $results[] = json_decode((string) @file_get_contents($file), true) ?? ['status' => 'sem resultado'];
        @unlink($file);
    }

    DB::reconnect();

    return $results;
}

$run = run_id();
$codes = fn (array $results): array => collect($results)->pluck('status')->sort()->values()->all();

// 1. Código de conta: duas contas com o mesmo nome criadas no mesmo instante.
$p1 = login('platform@e10.test', 'password', 'p1');
$p2 = login('platform-revoked@e10.test', 'password', 'p2');
$payload = fn (string $root, string $email) => [
    'name' => 'Colisão Concorrente E10', 'person_type' => 'company', 'document' => cnpj($root),
    'details' => ['phone' => '7130001111'], 'organization_setup' => 'automatic', 'contractor_classification' => 'headquarters',
    'administrator' => ['name' => 'Admin', 'email' => $email, 'mode' => 'manual', 'password' => E10_PASSWORD, 'password_confirmation' => E10_PASSWORD],
];
$results = race([
    fn () => ['status' => $p1->call('POST', '/saas/contas', $payload('81000001', 'colisao1@e10.test'))['status']],
    fn () => ['status' => $p2->call('POST', '/saas/contas', $payload('81000002', 'colisao2@e10.test'))['status']],
]);
$created = DB::table('clients')->where('name', 'Colisão Concorrente E10')->pluck('code')->sort()->values()->all();
check($j, 'Contas concorrentes com mesmo nome: duas contas e códigos distintos', count($created) === 2 && count(array_unique($created)) === 2, ['http' => $codes($results), 'codes' => $created]);
check($j, 'Cada conta concorrente com uma empresa e uma principal', DB::table('clients')->where('name', 'Colisão Concorrente E10')->get()->every(
    fn ($c) => DB::table('companies')->where('account_id', $c->id)->count() === 1 && DB::table('establishments')->where('account_id', $c->id)->where('is_primary', true)->count() === 1,
));

// 2. Aceite simultâneo do mesmo convite pela destinatária em duas sessões (identidade nova da A, convidada pela B).
$racer = 'corrida-'.$run.'@gartan.e10.test';
$anaSetup = login('ana@gartan.e10.test', label: 'setup');
$anaSetup->call('POST', $a.'/usuarios', ['name' => 'Corrida '.$run, 'email' => $racer, 'mode' => 'manual', 'password' => E10_PASSWORD,
    'password_confirmation' => E10_PASSWORD, 'role_id' => $roles['A']['Somente Ajuda'], 'scope' => 'tenant']);
$bruno = login('bruno@outra.e10.test');
$bruno->call('POST', $b.'/usuarios', ['name' => 'Corrida', 'email' => $racer, 'mode' => 'invite',
    'role_id' => $roles['B']['Operador B'], 'scope' => 'establishment', 'company_id' => $B['company'], 'establishment_id' => $B['primary']]);
$token = substr((string) mail_link($racer, '#/convites/[A-Za-z0-9]{64}#'), -64);
$h1 = login($racer, label: 'h1');
$h2 = login($racer, label: 'h2');
$results = race([
    fn () => ['status' => $h1->call('POST', '/convites/'.$token)['status']],
    fn () => ['status' => $h2->call('POST', '/convites/'.$token)['status']],
]);
$henriqueId = DB::table('users')->where('email', $racer)->value('id');
check($j, 'Aceite simultâneo: um aceito e um 410', $codes($results) === [200, 410], $codes($results));
check($j, 'Aceite simultâneo: um único vínculo e convite aceito', DB::table('account_memberships')->where('account_id', $B['id'])->where('user_id', $henriqueId)->count() === 1
    && DB::table('account_invitations')->where('token_hash', hash('sha256', $token))->value('status') === 'accepted');
check($j, 'Aceite simultâneo: um único evento de aceite', DB::table('audit_records')->where('account_id', $B['id'])
    ->where('entity_type', 'account-membership')->where('actor_id', $henriqueId)->where('event', 'created')->count() === 1);

// 3. Revogação durante o aceite (outra identidade nova da A).
$racer2 = 'revoga-'.$run.'@gartan.e10.test';
$anaSetup->call('POST', $a.'/usuarios', ['name' => 'Revoga '.$run, 'email' => $racer2, 'mode' => 'manual', 'password' => E10_PASSWORD,
    'password_confirmation' => E10_PASSWORD, 'role_id' => $roles['A']['Somente Ajuda'], 'scope' => 'tenant']);
$bruno->call('POST', $b.'/usuarios', ['name' => 'Revoga', 'email' => $racer2, 'mode' => 'invite',
    'role_id' => $roles['B']['Operador B'], 'scope' => 'establishment', 'company_id' => $B['company'], 'establishment_id' => $B['primary']]);
$token = substr((string) mail_link($racer2, '#/convites/[A-Za-z0-9]{64}#'), -64);
$invitationId = DB::table('account_invitations')->where('token_hash', hash('sha256', $token))->value('id');
$revokeRacer = login($racer2, label: 'r2');
$results = race([
    fn () => ['who' => 'accept', 'status' => $revokeRacer->call('POST', '/convites/'.$token)['status']],
    fn () => ['who' => 'revoke', 'status' => $bruno->call('PATCH', $b.'/usuarios/convites/'.$invitationId.'/revogar')['status']],
]);
$invitation = DB::table('account_invitations')->where('id', $invitationId)->first();
$membership = DB::table('account_memberships')->where('account_id', $B['id'])
    ->where('user_id', DB::table('users')->where('email', $racer2)->value('id'))->count();
$carlos = login('carlos@gartan.e10.test');
$carlosId = DB::table('users')->where('email', 'carlos@gartan.e10.test')->value('id');
check($j, 'Revogação x aceite: estado coerente (aceito com vínculo ou revogado sem vínculo)',
    ($invitation->status === 'accepted' && $membership === 1) || ($invitation->status === 'revoked' && $membership === 0),
    ['status' => $invitation->status, 'membership' => $membership, 'http' => $results]);

// 4. Último administrador: Ana e Henrique suspendem um ao outro ao mesmo tempo.
$ana = login('ana@gartan.e10.test');
$henrique = login('henrique@gartan.e10.test', label: 'h3');
$anaM = DB::table('account_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')->where('m.account_id', $A['id'])->where('u.email', 'ana@gartan.e10.test')->value('m.id');
$henM = DB::table('account_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')->where('m.account_id', $A['id'])->where('u.email', 'henrique@gartan.e10.test')->value('m.id');
$results = race([
    fn () => ['who' => 'ana suspende henrique', 'status' => $ana->call('PATCH', $a.'/usuarios/'.$henM.'/suspender')['status']],
    fn () => ['who' => 'henrique suspende ana', 'status' => $henrique->call('PATCH', $a.'/usuarios/'.$anaM.'/suspender')['status']],
]);
$activeAdmins = DB::table('role_assignments as r')->join('account_memberships as m', 'm.id', '=', 'r.membership_id')
    ->where('m.account_id', $A['id'])->where('m.status', 'active')->where('r.role_id', $roles['A']['Administrador da conta'])->where('r.scope', 'tenant')->count();
check($j, 'Último administrador: ao menos um administrador ativo e no máximo uma suspensão', $activeAdmins >= 1
    && collect($results)->where('status', 200)->count() <= 1, ['admins' => $activeAdmins, 'http' => $results]);
// Restaura o administrador suspenso pelo administrador remanescente.
foreach ([[$ana, $henM], [$henrique, $anaM]] as [$operator, $target]) {
    if (DB::table('account_memberships')->where('id', $target)->value('status') === 'suspended') {
        $operator->call('PATCH', $a.'/usuarios/'.$target.'/reativar');
    }
}
check($j, 'Ambos administradores reativados após a prova', DB::table('account_memberships')->whereIn('id', [$anaM, $henM])->where('status', 'active')->count() === 2);

// 5. Mesmo documento cadastrado ao mesmo tempo na mesma empresa (duas sessões).
$carlos2 = login('carlos@gartan.e10.test', label: 'c2');
$document = cpf((string) random_int(200000000, 299999999));
$customer = fn (string $name) => ['person_type' => 'individual', 'name' => $name, 'document' => $document, 'phone' => '7730000000'];
$results = race([
    fn () => ['status' => $carlos->call('POST', $a1.'/clientes', $customer('Concorrente 1'))['status']],
    fn () => ['status' => $carlos2->call('POST', $a1.'/clientes', $customer('Concorrente 2'))['status']],
]);
$people = DB::table('people')->where('account_id', $A['id'])->where('document', $document)->get();
check($j, 'Mesmo CPF simultâneo: uma Pessoa e um Cliente', $people->count() === 1
    && DB::table('customers')->where('person_id', $people[0]->id ?? 0)->count() === 1, ['people' => $people->count(), 'http' => $codes($results)]);
check($j, 'Mesmo CPF simultâneo: nenhum erro 500', ! in_array(500, $codes($results), true), $codes($results));
check($j, 'Mesmo CPF simultâneo: uma Pessoa auditada uma vez', DB::table('audit_records')->where('entity_type', 'person')
    ->where('entity_id', $people[0]->id ?? 0)->where('event', 'created')->count() === 1);

// 6. Mesma Pessoa nova vinculada a duas empresas ao mesmo tempo (A1 e A2), pela administradora em duas sessões.
$ana2 = login('ana@gartan.e10.test', label: 'a2');
$document = cpf((string) random_int(300000000, 399999999));
$payload = ['person_type' => 'individual', 'name' => 'Duas empresas', 'document' => $document, 'phone' => '7731111111'];
$results = race([
    fn () => ['status' => $ana->call('POST', $a1.'/clientes', $payload)['status']],
    fn () => ['status' => $ana2->call('POST', $a2.'/clientes', $payload)['status']],
]);
$people = DB::table('people')->where('account_id', $A['id'])->where('document', $document)->get();
$relations = DB::table('customers')->whereIn('person_id', $people->pluck('id'))->count();
check($j, 'Pessoa nova em duas empresas ao mesmo tempo: uma Pessoa', $people->count() === 1, ['people' => $people->count(), 'relations' => $relations, 'http' => $codes($results)]);
check($j, 'Pessoa nova em duas empresas: sem 500', ! in_array(500, $codes($results), true), $codes($results));

// 7. Dez alterações concorrentes da mesma Pessoa: cada evento coerente e o valor final é o do último evento.
$commercial = state()['commercial'];
$sessions = [];
foreach (range(1, 10) as $index) {
    $sessions[$index] = login($index % 2 === 0 ? 'ana@gartan.e10.test' : 'henrique@gartan.e10.test', label: 'w'.$index);
}
$beforeId = (int) DB::table('audit_records')->max('id');
$results = race(array_map(fn ($index) => fn () => ['status' => $sessions[$index]->call('PATCH', $a1.'/clientes/'.$commercial['customerA1'], [
    'phone' => '77'.substr((string) hexdec($run), -4).str_pad((string) $index, 4, '0', STR_PAD_LEFT),
])['status']], array_keys($sessions)));
$events = DB::table('audit_records')->where('id', '>', $beforeId)->where('entity_type', 'person')->where('entity_id', $commercial['person'])->orderBy('id')->get();
$final = DB::table('people')->where('id', $commercial['person'])->value('phone');
$lastAfter = json_decode((string) $events->last()?->changes, true);
check($j, 'Escritas concorrentes: dez respostas sem erro', collect($results)->every(fn ($r) => in_array($r['status'], [200, 204], true)), $codes($results));
check($j, 'Escritas concorrentes: um evento por alteração efetiva', $events->count() === 10, $events->count());
check($j, 'Escritas concorrentes: valor final igual ao depois do último evento', str_contains(json_encode($lastAfter), $final), ['final' => $final]);
$chain = true;
for ($i = 1; $i < $events->count(); $i++) {
    $chain = $chain && str_contains((string) $events[$i]->changes, (string) collect(json_decode((string) $events[$i - 1]->changes, true))->flatten()->last());
}
check($j, 'Escritas concorrentes: cadeia antes/depois coerente (serializada)', $chain);
check($j, 'Escritas concorrentes: autor de cada evento é quem escreveu', $events->pluck('actor_id')->unique()->count() === 2);

// 8. Estado da empresa e da unidade alterado durante cadastros comerciais (cinco rodadas de cada).
$logistic = login('logistica@gartan.e10.test');
$rounds = [];
foreach (range(1, 5) as $round) {
    $document = cnpj((string) random_int(40000000, 49999999));
    $beforeId = (int) DB::table('audit_records')->max('id');
    $results = race([
        fn () => ['who' => 'cadastro', 'status' => $logistic->call('POST', $a2.'/fornecedores', ['person_type' => 'company', 'name' => 'Fornecedor em corrida', 'document' => $document, 'phone' => '7732222222'])['status']],
        fn () => ['who' => 'inativa', 'status' => $ana->call('PATCH', $a.'/empresas/'.$A['company2'], ['active' => false])['status']],
    ]);
    $supplier = DB::table('suppliers as s')->join('people as p', 'p.id', '=', 's.person_id')->where('p.document', $document)->select('s.*')->first();
    $deactivationId = DB::table('audit_records')->where('id', '>', $beforeId)->where('entity_type', 'company')->where('entity_id', $A['company2'])->value('id');
    $supplierEventId = $supplier ? DB::table('audit_records')->where('entity_type', 'supplier')->where('entity_id', $supplier->id)->value('id') : null;
    $rounds[] = ['http' => collect($results)->pluck('status', 'who')->all(), 'supplier' => $supplier !== null,
        'coherent' => $supplier === null || ($supplierEventId !== null && $supplierEventId < $deactivationId)];
    $ana->call('PATCH', $a.'/empresas/'.$A['company2'], ['active' => true]);
}
check($j, 'Empresa inativada durante cadastro: cinco rodadas sem 500', collect($rounds)->every(fn ($r) => ! in_array(500, $r['http'], true)), $rounds);
check($j, 'Empresa inativada durante cadastro: fornecedor só existe se confirmado antes da inativação', collect($rounds)->every(fn ($r) => $r['coherent']), $rounds);
$ana->call('PATCH', $a.'/empresas/'.$A['company2'], ['active' => false]);
check($j, 'Empresa inativa recusa cadastro comercial', $logistic->call('POST', $a2.'/fornecedores', [
    'person_type' => 'company', 'name' => 'Depois', 'document' => cnpj((string) random_int(50000000, 59999999)), 'phone' => '7732222223',
])['status'] !== 200 && DB::table('people')->where('name', 'Depois')->doesntExist());
$ana->call('PATCH', $a.'/empresas/'.$A['company2'], ['active' => true]);

$unitRounds = [];
foreach (range(1, 5) as $round) {
    $document = cpf((string) random_int(700000000, 799999999));
    $results = race([
        fn () => ['who' => 'cadastro', 'status' => $carlos->call('POST', $a1.'/clientes', ['person_type' => 'individual', 'name' => 'Unidade em corrida', 'document' => $document, 'phone' => '7734444444'])['status']],
        fn () => ['who' => 'unidade', 'status' => $ana->call('PATCH', $a.'/estabelecimentos/'.$A['brumado'], ['phone' => '77345'.random_int(10000, 99999)])['status']],
    ]);
    $unitRounds[] = collect($results)->pluck('status', 'who')->all();
}
check($j, 'Edição de unidade durante cadastro na mesma empresa: cinco rodadas sem 500 e ambas concluídas',
    collect($unitRounds)->every(fn ($r) => in_array($r['cadastro'], [200, 204], true) && $r['unidade'] === 200), $unitRounds);

// 9. Atribuições de cargo concorrentes iguais: no máximo uma atribuição.
$consultM = DB::table('account_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')->where('m.account_id', $A['id'])->where('u.email', 'consulta@gartan.e10.test')->value('m.id');
$grant = ['grants' => [['role_id' => $roles['A']['Vendas A'], 'scope' => 'company', 'company_id' => $A['company2']]]];
$results = race([
    fn () => ['status' => $ana->call('PATCH', $a.'/usuarios/'.$consultM.'/atribuicoes', $grant)['status']],
    fn () => ['status' => $henrique->call('PATCH', $a.'/usuarios/'.$consultM.'/atribuicoes', $grant)['status']],
]);
$assignments = DB::table('role_assignments')->where('membership_id', $consultM)->where('role_id', $roles['A']['Vendas A'])->where('company_id', $A['company2'])->count();
check($j, 'Atribuição igual concorrente: exatamente uma', $assignments === 1, ['http' => $codes($results), 'assignments' => $assignments]);
check($j, 'Atribuição igual concorrente: sem 500', ! in_array(500, $codes($results), true), $codes($results));

// 10. Suspensão durante uma sequência de escritas do próprio usuário.
$suspendTarget = DB::table('account_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')->where('m.account_id', $A['id'])->where('u.email', 'carlos@gartan.e10.test')->value('m.id');
$beforeId = (int) DB::table('audit_records')->max('id');
$results = race([
    function () use ($carlos, $a1, $run) {
        $statuses = [];
        foreach (range(1, 8) as $i) {
            $statuses[] = $carlos->call('POST', $a1.'/clientes', ['person_type' => 'individual', 'name' => 'Em voo '.$run.' '.$i,
                'document' => cpf((string) random_int(600000000, 699999999)), 'phone' => '7733333333'])['status'];
        }

        return ['who' => 'escritas', 'status' => $statuses];
    },
    function () use ($ana, $a, $suspendTarget) {
        usleep(30000);

        return ['who' => 'suspensao', 'status' => $ana->call('PATCH', $a.'/usuarios/'.$suspendTarget.'/suspender')['status']];
    },
]);
$writes = collect($results)->firstWhere('who', 'escritas')['status'] ?? [];
$suspensionEvent = DB::table('audit_records')->where('id', '>', $beforeId)->where('entity_type', 'account-membership')->where('entity_id', $suspendTarget)->value('id');
$writesAfter = DB::table('audit_records')->where('id', '>', $suspensionEvent ?? PHP_INT_MAX)->where('actor_id', $carlosId)->where('entity_type', 'customer')->count();
$firstDenied = array_search(403, $writes, true);
check($j, 'Suspensão em voo: depois da primeira negação, nenhuma escrita aceita', $firstDenied === false
    || collect(array_slice($writes, $firstDenied))->every(fn ($s) => $s === 403), $writes);
// O ID da auditoria é atribuído na inserção, não na confirmação: a escrita já autorizada em voo termina (E09).
$confirmed = DB::table('customers as c')->join('people as p', 'p.id', '=', 'c.person_id')
    ->where('p.account_id', $A['id'])->where('p.name', 'like', 'Em voo '.$run.' %')->count();
$confirmedEvents = DB::table('audit_records')->where('id', '>', $beforeId)->where('actor_id', $carlosId)->where('entity_type', 'customer')->count();
check($j, 'Suspensão em voo: cada 204 corresponde a um cliente e um evento confirmados, sem sobras das negadas',
    $confirmed === count(array_filter($writes, fn ($s) => $s === 204)) && $confirmedEvents === $confirmed,
    ['writes' => $writes, 'confirmed' => $confirmed, 'events' => $confirmedEvents]);
$ana->call('PATCH', $a.'/usuarios/'.$suspendTarget.'/reativar');

finish('11-concorrencia');
