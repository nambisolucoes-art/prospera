<?php

// Jornada 8: auditoria contextual sobre os eventos reais produzidos pelas jornadas anteriores.
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

require __DIR__.'/http.php';

$j = 'J8 auditoria';
$accounts = state()['accounts'];
$commercial = state()['commercial'];
$A = $accounts['A'];
$B = $accounts['B'];
$a = '/contas/'.$A['code'];
$b = '/contas/'.$B['code'];
$ana = login('ana@gartan.e10.test');
$manager = login('unidade@gartan.e10.test');
$consult = login('consulta@gartan.e10.test');
$bruno = login('bruno@outra.e10.test');
$total = fn (array $r): ?int => $r['body']['records']['total'] ?? null;

// Visão integral da conta: total igual ao banco, nunca eventos da B.
$all = $ana->call('GET', $a.'/auditoria?per_page=100');
status($j, 'Auditoria da conta A', $all, 200);
$dbA = DB::table('audit_records')->where('account_id', $A['id'])->count();
check($j, 'Total da listagem igual ao total da conta no banco', $total($all) === $dbA, [$total($all), $dbA]);
$ids = collect($all['body']['records']['data'])->pluck('id')->all();
check($j, 'Nenhum evento de outra conta na página', DB::table('audit_records')->whereIn('id', $ids)->where('account_id', '<>', $A['id'])->doesntExist());

// Paginação estável: páginas sem interseção e soma igual ao total.
$page1 = $ana->call('GET', $a.'/auditoria?per_page=25&page=1');
$page2 = $ana->call('GET', $a.'/auditoria?per_page=25&page=2');
$p1 = collect($page1['body']['records']['data'])->pluck('id');
$p2 = collect($page2['body']['records']['data'])->pluck('id');
check($j, 'Páginas sem repetição', $p1->intersect($p2)->isEmpty() && $p2->isNotEmpty(), [$p1->count(), $p2->count()]);

// Filtros aplicados no banco: empresa, nível, entidade e busca.
$byCompany = $ana->call('GET', $a.'/auditoria?company='.$A['company2'].'&per_page=100');
$expected = DB::table('audit_records')->where('account_id', $A['id'])->where('company_id', $A['company2'])->count();
check($j, 'Filtro por empresa A2 confere com o banco', $total($byCompany) === $expected, [$total($byCompany), $expected]);
$tenantLevel = $ana->call('GET', $a.'/auditoria?level=tenant&per_page=100');
$expected = DB::table('audit_records')->where('account_id', $A['id'])->whereNull('company_id')->count();
check($j, 'Filtro de nível Conta confere com o banco', $total($tenantLevel) === $expected, [$total($tenantLevel), $expected]);
$customers = $ana->call('GET', $a.'/auditoria?entity=customer&per_page=100');
$expected = DB::table('audit_records')->where('account_id', $A['id'])->where('entity_type', 'customer')->count();
check($j, 'Filtro por entidade Cliente confere com o banco', $total($customers) === $expected, [$total($customers), $expected]);
$search = $ana->call('GET', $a.'/auditoria?search=Marta&per_page=100');
check($j, 'Busca textual retorna eventos da Pessoa e das relações', ($total($search) ?? 0) > 0, $total($search));
$companyOptions = collect($all['body']['options']['companies'] ?? [])->pluck('value')->map(fn ($v) => (int) $v)->sort()->values()->all();
check($j, 'Opções de empresa só da conta A', $companyOptions === collect([$A['company'], $A['company2']])->sort()->values()->all(), $companyOptions);

// Detalhe: autor real, nós, antes e depois, sem dados protegidos.
$personEvent = DB::table('audit_records')->where('entity_type', 'person')->where('entity_id', $commercial['person'])
    ->where('event', 'updated')->orderByDesc('id')->first();
$detail = $ana->call('GET', $a.'/auditoria/'.$personEvent->id.'/visualizar');
status($j, 'Detalhe do evento central', $detail, 200);
$changes = json_encode($detail['body']['changes'] ?? [], JSON_UNESCAPED_UNICODE);
check($j, 'Detalhe mostra antes e depois do nome alterado', str_contains($changes, 'Marta Queiroz') && str_contains($changes, 'Marta Queiroz Lima'), $changes);
$technical = $detail['body']['technical'] ?? [];
check($j, 'Dados técnicos da administradora com nó da conta', array_key_exists('company_id', $technical) && $technical['company_id'] === null
    && $technical['establishment_id'] === null && $technical['context'] === 'account'
    && (int) ($detail['body']['technical']['actor_id'] ?? 0) === DB::table('users')->where('email', 'ana@gartan.e10.test')->value('id'));
$documentEvent = DB::table('audit_records')->where('entity_type', 'person')->where('entity_id', $commercial['person'])->where('event', 'created')->first();
$documentDetail = json_encode($ana->call('GET', $a.'/auditoria/'.$documentEvent->id.'/visualizar')['body'], JSON_UNESCAPED_UNICODE);
check($j, 'CPF da pessoa não aparece no histórico (Documento informado)', ! str_contains($documentDetail, $commercial['document']), null);
check($j, 'CPF ausente de todo o histórico gravado', DB::table('audit_records')->where('changes', 'like', '%'.$commercial['document'].'%')->doesntExist());

// Gerente da unidade Brumado: só eventos da unidade, contagem e detalhe coerentes.
$local = $manager->call('GET', $a.'/auditoria?per_page=100');
status($j, 'Auditoria local do gerente de unidade', $local, 200);
$expected = DB::table('audit_records')->where('account_id', $A['id'])->where('establishment_id', $A['brumado'])->count();
check($j, 'Gerente de unidade vê só os eventos da Brumado (contagem igual ao banco)', $total($local) === $expected
    && collect($local['body']['records']['data'])->every(fn ($r) => DB::table('audit_records')->where('id', $r['id'])->value('establishment_id') == $A['brumado']),
    [$total($local), $expected]);
status($j, 'Gerente não abre evento central da conta', $manager->call('GET', $a.'/auditoria/'.$personEvent->id.'/visualizar'), 404);
$companyEvent = DB::table('audit_records')->where('entity_type', 'company')->where('company_id', $A['company'])->value('id');
status($j, 'Gerente não abre evento da empresa', $manager->call('GET', $a.'/auditoria/'.$companyEvent.'/visualizar'), 404);
$eventB = DB::table('audit_records')->where('account_id', $B['id'])->value('id');
status($j, 'Gerente não abre evento da Conta B', $manager->call('GET', $a.'/auditoria/'.$eventB.'/visualizar'), 404);
$brumadoEvent = DB::table('audit_records')->where('establishment_id', $A['brumado'])->value('id');
$localDetail = $manager->call('GET', $a.'/auditoria/'.$brumadoEvent.'/visualizar');
status($j, 'Gerente abre evento da própria unidade', $localDetail, 200);
check($j, 'Sem auditoria técnica, o detalhe não traz dados técnicos', array_key_exists('technical', $localDetail['body'] ?? [])
    && $localDetail['body']['technical'] === null);
$localFilter = $manager->call('GET', $a.'/auditoria?company='.$A['company2'].'&per_page=100');
check($j, 'Filtro forjado de outra empresa não amplia o escopo', ($total($localFilter) ?? 0) === 0, $total($localFilter));
$actors = collect($local['body']['options']['actors'] ?? [])->pluck('value')->map(fn ($v) => (int) $v)->all();
$allowedActors = DB::table('audit_records')->where('account_id', $A['id'])->where('establishment_id', $A['brumado'])->distinct()->pluck('actor_id')->filter()->all();
check($j, 'Opções de autor calculadas só no escopo autorizado', array_diff($actors, $allowedActors) === [], [$actors, $allowedActors]);

// Sem permissão de auditoria e outra conta.
status($j, 'Consulta sem auditoria recebe 403', $consult->call('GET', $a.'/auditoria'), 403);
status($j, 'Administrador da B não abre evento da A pela URL da B', $bruno->call('GET', $b.'/auditoria/'.$personEvent->id.'/visualizar'), 404);
$bList = $bruno->call('GET', $b.'/auditoria?per_page=100&search=Marta');
check($j, 'Busca na B só encontra a Marta da B', collect($bList['body']['records']['data'] ?? [])->every(
    fn ($r) => (int) DB::table('audit_records')->where('id', $r['id'])->value('account_id') === $B['id']), $total($bList));
$saas = login('platform@e10.test', 'password', 'plataforma');
$platformList = $saas->call('GET', '/saas/auditoria?per_page=100');
$platformIds = collect($platformList['body']['records']['data'] ?? [])->pluck('id')->all();
// PD-02: sem conta, tipo Conta ou origem Plataforma SaaS (provisionamento); nada da operação das contas.
$outside = DB::table('audit_records')->whereIn('id', $platformIds)->whereNotNull('account_id')
    ->where('entity_type', '<>', 'account')->where('context', '<>', 'platform')->count();
check($j, 'Plataforma não vê eventos de negócio das contas (PD-02)', $outside === 0 && $platformIds !== [], $outside);
check($j, 'Plataforma não vê as relações comerciais criadas nas contas', DB::table('audit_records')->whereIn('id', $platformIds)
    ->whereIn('entity_type', ['customer', 'supplier'])->doesntExist());

// Alteração sem mudança efetiva não gera evento; alteração efetiva gera um, com o nó da unidade.
$before = DB::table('audit_records')->count();
$phone = DB::table('establishments')->where('id', $A['brumado'])->value('phone');
status($j, 'Salvar unidade sem mudança', $manager->call('PATCH', $a.'/estabelecimentos/'.$A['brumado'], ['phone' => $phone]), 200);
check($j, 'Sem mudança efetiva, nenhum evento', DB::table('audit_records')->count() === $before);
$newPhone = '773451'.random_int(1000, 9999);
status($j, 'Alteração efetiva da unidade', $manager->call('PATCH', $a.'/estabelecimentos/'.$A['brumado'], ['phone' => $newPhone]), 200);
$last = DB::table('audit_records')->orderByDesc('id')->first();
check($j, 'Um evento com nó da unidade, autor real e antes e depois', DB::table('audit_records')->count() === $before + 1
    && (int) $last->establishment_id === $A['brumado'] && (int) $last->company_id === $A['company']
    && str_contains((string) $last->changes, $newPhone) && $last->represented_user_id === null);

// Falha de validação depois de uma escrita parcial desfaz o histórico (EnforceAudit).
$before = DB::table('audit_records')->count();
status($j, 'Edição com dado inválido', $manager->call('PATCH', $a.'/estabelecimentos/'.$A['brumado'], ['name' => 'Nome válido', 'email' => 'invalido']), 422);
check($j, 'Resposta 422 sem evento e sem alteração', DB::table('audit_records')->count() === $before
    && DB::table('establishments')->where('id', $A['brumado'])->value('name') !== 'Nome válido');

// Histórico confirmado é imutável no PostgreSQL.
try {
    DB::table('audit_records')->where('id', $personEvent->id)->update(['actor_name' => 'Reescrito']);
    check($j, 'UPDATE no histórico confirmado recusado', false);
} catch (QueryException $exception) {
    check($j, 'UPDATE no histórico confirmado recusado', true, $exception->getCode());
}

finish('08-auditoria');
