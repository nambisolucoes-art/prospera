<?php

// Jornada 9: inspeção SaaS de ponta a ponta, com autoria real e isolamento da conta inspecionada.
use Illuminate\Support\Facades\DB;

require __DIR__.'/http.php';

$j = 'J9 inspecao';
$accounts = state()['accounts'];
$A = $accounts['A'];
$B = $accounts['B'];
$a = '/contas/'.$A['code'];
$b = '/contas/'.$B['code'];
$platform = login('platform@e10.test', 'password', 'plataforma');
$platformId = DB::table('users')->where('email', 'platform@e10.test')->value('id');
$ana = login('ana@gartan.e10.test');
$anaId = DB::table('users')->where('email', 'ana@gartan.e10.test')->value('id');
$carlosId = DB::table('users')->where('email', 'carlos@gartan.e10.test')->value('id');
$preference = fn () => DB::table('account_memberships')->where('account_id', $A['id'])->where('user_id', $anaId)->value('workspace_establishments');

// Seleção da conta e do representado.
$options = $platform->call('GET', '/saas/contas/'.$A['id'].'/inspecao/usuarios');
status($j, 'Plataforma lista os representáveis da Conta A', $options, 200);
$listed = collect($options['body']['users'] ?? $options['body'] ?? [])->pluck('id')->map(fn ($v) => (int) $v)->all();
check($j, 'Representáveis só da Conta A (Bruno ausente)', in_array($anaId, $listed, true)
    && ! in_array(DB::table('users')->where('email', 'bruno@outra.e10.test')->value('id'), $listed, true), $listed);
status($j, 'Representado de outra conta recusado', $platform->call('POST', '/saas/contas/'.$A['id'].'/inspecao', [
    'user_id' => DB::table('users')->where('email', 'bruno@outra.e10.test')->value('id'), 'mode' => 'read-only',
]), [403, 404, 422]);

// Somente leitura: lê com a autoridade da Ana; não escreve; não troca de conta; não altera a preferência.
$preferenceBefore = $preference();
status($j, 'Início da inspeção somente leitura', $platform->call('POST', '/saas/contas/'.$A['id'].'/inspecao', ['user_id' => $anaId, 'mode' => 'read-only']), 200);
$inspectionId = DB::table('audit_records')->where('event', 'inspection-started')->orderByDesc('id')->value('inspection_id');
check($j, 'Evento de início com inspection_id', $inspectionId !== null);
status($j, 'Leitura na Conta A durante a inspeção', $platform->call('GET', $a.'/empresas'), 200);
$before = DB::table('audit_records')->count();
status($j, 'Escrita recusada em somente leitura', $platform->call('POST', $a.'/etiquetas', ['name' => 'Inspeção RO', 'module' => 'customers', 'color' => '#101010']), 403);
check($j, 'Somente leitura sem efeito e sem evento', DB::table('tags')->where('name', 'Inspeção RO')->doesntExist() && DB::table('audit_records')->count() === $before);
status($j, 'Troca de conta pela URL para a B (Ana tem vínculo) responde 404', $platform->call('GET', $b.'/etiquetas'), 404);
status($j, 'Troca para conta sem vínculo responde 404', $platform->call('GET', '/contas/'.$accounts['D']['code']), 404);
status($j, 'Preferência da representada não pode ser gravada', $platform->call('PUT', $a.'/area-de-trabalho/padrao', ['unidades' => [$A['brumado_code']]]), 403);
check($j, 'Preferência preservada', $preference() === $preferenceBefore);
status($j, 'Sessão própria da Ana continua independente', $ana->call('GET', $a.'/empresas'), 200);
status($j, 'Ana continua escrevendo na própria sessão', $ana->call('PUT', $a.'/area-de-trabalho/padrao', ['unidades' => [$A['primary_code'], $A['guanambi_code']]]), 204);
status($j, 'Encerramento', $platform->call('POST', '/inspecao/encerrar'), 200);
status($j, 'Após encerrar, a plataforma não lê a conta', $platform->call('GET', $a.'/empresas'), [403, 404]);

// Leitura e edição: escrita autorizada com autoria real e representado.
status($j, 'Início em leitura e edição representando o Carlos', $platform->call('POST', '/saas/contas/'.$A['id'].'/inspecao', ['user_id' => $carlosId, 'mode' => 'edit']), 200);
$editId = DB::table('audit_records')->where('event', 'inspection-started')->orderByDesc('id')->value('inspection_id');
$commercial = state()['commercial'];
status($j, 'Edição de cliente da A1 com a autoridade do Carlos', $platform->call('PATCH', $a.'/empresa/'.$A['company_code'].'/clientes/'.$commercial['customerA1'], ['whatsapp' => '7798888'.random_int(1000, 9999)]), [200, 204]);
$write = DB::table('audit_records')->where('entity_type', 'person')->where('entity_id', $commercial['person'])->orderByDesc('id')->first();
check($j, 'Autor real é a plataforma; representado é o Carlos; mesma inspeção', (int) $write->actor_id === $platformId
    && (int) $write->represented_user_id === $carlosId && $write->inspection_id === $editId && $write->context === 'platform-account'
    && (int) $write->account_id === $A['id']);
check($j, 'Vínculo usado é o do representado', (int) $write->membership_id === DB::table('account_memberships')->where('account_id', $A['id'])->where('user_id', $carlosId)->value('id'));
status($j, 'Mesmo em edição, a autoridade é só a do Carlos (sem Empresas: criar)', $platform->call('POST', $a.'/empresas', ['name' => 'X', 'person_type' => 'company', 'document' => '66554433', 'active' => true]), 403);
status($j, 'Edição não altera a preferência do representado', $platform->call('PUT', $a.'/area-de-trabalho/padrao', ['unidades' => [$A['brumado_code']]]), 403);
status($j, 'Encerramento da edição', $platform->call('POST', '/inspecao/encerrar'), 200);
check($j, 'Início e encerramento com o mesmo inspection_id', DB::table('audit_records')->where('inspection_id', $editId)
    ->whereIn('event', ['inspection-started', 'inspection-ended'])->count() === 2);
check($j, 'Inspeção não cria vínculo permanente', DB::table('account_memberships')->where('user_id', $platformId)->doesntExist());

// Perda de autoridade do inspetor durante a inspeção.
$revocable = login('platform-revoked@e10.test', 'password', 'inspetor-revogavel');
$revocableId = DB::table('users')->where('email', 'platform-revoked@e10.test')->value('id');
status($j, 'Segundo inspetor inicia', $revocable->call('POST', '/saas/contas/'.$A['id'].'/inspecao', ['user_id' => $anaId, 'mode' => 'read-only']), 200);
status($j, 'Leitura antes da revogação', $revocable->call('GET', $a.'/empresas'), 200);
DB::table('users')->where('id', $revocableId)->update(['is_saas_admin' => false]);
$after = $revocable->call('GET', $a.'/empresas');
check($j, 'Revogação encerra a inspeção na requisição seguinte', in_array($after['status'], [302, 401, 403, 404, 409], true) || isset($after['body']['redirect']), $after['status']);
$ended = DB::table('audit_records')->where('actor_id', $revocableId)->where('event', 'inspection-ended')->first();
check($j, 'Encerramento por perda de autoridade registrado', $ended !== null, $ended?->changes);
status($j, 'Depois disso, nenhuma leitura da conta', $revocable->call('GET', $a.'/empresas'), [302, 401, 403, 404]);
DB::table('users')->where('id', $revocableId)->update(['is_saas_admin' => true]);

// Histórico: a conta vê a inspeção; a plataforma vê início e encerramento, não a escrita de negócio.
$history = $ana->call('GET', $a.'/auditoria?origin=platform-account&per_page=100');
$names = collect($history['body']['records']['data'] ?? [])->pluck('id')->all();
check($j, 'Auditoria da conta mostra a escrita feita na inspeção', in_array($write->id, $names, true), count($names));
$platformHistory = $platform->call('GET', '/saas/auditoria?per_page=100');
$platformIds = collect($platformHistory['body']['records']['data'] ?? [])->pluck('id')->all();
check($j, 'Plataforma vê o início da inspeção', DB::table('audit_records')->whereIn('id', $platformIds)->where('inspection_id', $editId)->where('event', 'inspection-started')->exists());
check($j, 'Plataforma não vê a escrita de negócio da inspeção', ! in_array($write->id, $platformIds, true));
check($j, 'Representado nunca aparece como autor', DB::table('audit_records')->whereNotNull('represented_user_id')->whereColumn('actor_id', 'represented_user_id')->doesntExist());

finish('09-inspecao');
