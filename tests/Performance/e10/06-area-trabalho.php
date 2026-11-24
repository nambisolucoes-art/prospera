<?php

// Jornada 6: Área de Trabalho Organizacional (E06), seleção, padrão por vínculo e revogação.
use Illuminate\Support\Facades\DB;

require __DIR__.'/http.php';

$j = 'J6 area de trabalho';
$accounts = state()['accounts'];
$roles = state()['roles'];
$A = $accounts['A'];
$B = $accounts['B'];
$a = '/contas/'.$A['code'];
$b = '/contas/'.$B['code'];
$ana = login('ana@gartan.e10.test');
$anaId = DB::table('users')->where('email', 'ana@gartan.e10.test')->value('id');
$stored = fn (int $account, int $user) => json_decode((string) DB::table('account_memberships')
    ->where('account_id', $account)->where('user_id', $user)->value('workspace_establishments'), true);
$enter = fn (Browser $browser, string $base, string $query) => $browser->call('GET', $base.'/estabelecimento'.$query);
$target = fn (array $response): string => (string) ($response['body']['redirect'] ?? $response['location'] ?? '');

$a1Units = [$A['primary_code'], $A['guanambi_code'], $A['brumado_code']];
$organization = $ana->call('GET', $a.'/etiquetas')['body']['organization_context'] ?? null;
$navigable = collect($organization['establishments'] ?? $organization['units'] ?? [])->pluck('code')->sort()->values()->all();
check($j, 'Seletor recebe as cinco unidades autorizadas da Ana na A', count($navigable) === 5, $organization === null ? null : array_keys($organization));

// Uma unidade.
$response = $enter($ana, $a, '?unidades='.$A['brumado_code']);
check($j, 'Uma unidade abre o contexto da unidade', str_ends_with($target($response), '/estabelecimento/'.$A['brumado_code']), $target($response));

// Várias unidades e todas as da empresa: consolidado somente leitura com a seleção.
$response = $enter($ana, $a, '?unidades='.implode(',', $a1Units).'&para=clientes');
check($j, 'Várias unidades abrem o consolidado do módulo', str_contains($target($response), '/estabelecimento/todos/clientes?unidades='), $target($response));
$consolidated = $ana->call('GET', $a.'/estabelecimento/todos/clientes?unidades='.implode(',', $a1Units));
status($j, 'Consolidado das unidades da A1', $consolidated, 200);

// Unidades de empresas distintas.
$mixed = [$A['brumado_code'], $A['company2_primary_code']];
status($j, 'Consolidado com unidades de empresas distintas', $ana->call('GET', $a.'/estabelecimento/todos/clientes?unidades='.implode(',', $mixed)), 200);

// Unidade não autorizada (de outra conta) e código inexistente.
status($j, 'Unidade da Conta B na seleção da A responde 404', $enter($ana, $a, '?unidades='.$B['primary_code']), 404);
status($j, 'Consolidado com unidade de outra conta responde 404', $ana->call('GET', $a.'/estabelecimento/todos/clientes?unidades='.$A['brumado_code'].','.$B['primary_code']), 404);

// Abrir área de trabalho grava o padrão pessoal por usuário e conta.
status($j, 'Grava o padrão da Ana na A', $ana->call('PUT', $a.'/area-de-trabalho/padrao', ['unidades' => $mixed]), 204);
check($j, 'Padrão gravado em account_memberships.workspace_establishments do vínculo da A', $stored($A['id'], $anaId) === $mixed, $stored($A['id'], $anaId));
check($j, 'Padrão da A não altera o vínculo da B', $stored($B['id'], $anaId) === null, $stored($B['id'], $anaId));
$default = $ana->call('GET', $a.'/area-de-trabalho/padrao')['body']['codes'] ?? null;
check($j, 'Padrão efetivo da A devolvido pela consulta', $default === $mixed, $default);
$response = $enter($ana, $a, '');
check($j, 'Entrada sem código usa o padrão salvo', str_contains($target($response), 'unidades='.implode(',', $mixed)), $target($response));
status($j, 'A não aceita códigos da B como padrão', $ana->call('PUT', $a.'/area-de-trabalho/padrao', ['unidades' => [$B['primary_code']]]), 404);
check($j, 'Tentativa inválida preserva o padrão', $stored($A['id'], $anaId) === $mixed);
status($j, 'Padrão da B é independente', $ana->call('PUT', $b.'/area-de-trabalho/padrao', ['unidades' => [$B['primary_code']]]), [204, 404]);
check($j, 'Padrão da A permanece após gravar a B', $stored($A['id'], $anaId) === $mixed);

// Unidade inativa sai do padrão efetivo sem apagar a preferência.
status($j, 'Inativa a filial de Brumado', $ana->call('PATCH', $a.'/estabelecimentos/'.$A['brumado'], ['active' => false]), 200);
$default = $ana->call('GET', $a.'/area-de-trabalho/padrao')['body']['codes'] ?? null;
check($j, 'Unidade inativa fora do padrão efetivo', $default === [$A['company2_primary_code']], $default);
status($j, 'Unidade inativa recusa operação', $ana->call('GET', $a.'/estabelecimento/'.$A['brumado_code'].'/clientes/cadastrar'), [403, 404]);
status($j, 'Reativa a filial de Brumado', $ana->call('PATCH', $a.'/estabelecimentos/'.$A['brumado'], ['active' => true]), 200);

// Autorização revogada prevalece sobre a preferência salva (Carlos: empresa A1 e Brumado).
$carlos = login('carlos@gartan.e10.test');
$carlosId = DB::table('users')->where('email', 'carlos@gartan.e10.test')->value('id');
status($j, 'Carlos grava padrão com Guanambi e Brumado', $carlos->call('PUT', $a.'/area-de-trabalho/padrao', ['unidades' => [$A['guanambi_code'], $A['brumado_code']]]), 204);
$membership = DB::table('account_memberships')->where('account_id', $A['id'])->where('user_id', $carlosId)->value('id');
$companyAssignment = DB::table('role_assignments')->where('membership_id', $membership)->where('scope', 'company')->value('id');
status($j, 'Ana retira a atribuição de empresa do Carlos', $ana->call('PATCH', $a.'/usuarios/'.$membership.'/atribuicoes', ['revocations' => [$companyAssignment]]), 200);
$default = $carlos->call('GET', $a.'/area-de-trabalho/padrao')['body']['codes'] ?? null;
check($j, 'Revogação prevalece: só Brumado continua no padrão efetivo', $default === [$A['brumado_code']], $default);
status($j, 'Guanambi revogada responde 404 mesmo estando na preferência', $carlos->call('GET', $a.'/estabelecimento/'.$A['guanambi_code']), 404);
check($j, 'Preferência gravada não foi reescrita pela revogação', $stored($A['id'], $carlosId) === [$A['guanambi_code'], $A['brumado_code']]);
status($j, 'Ana devolve a atribuição de empresa ao Carlos', $ana->call('PATCH', $a.'/usuarios/'.$membership.'/atribuicoes', ['grants' => [[
    'role_id' => $roles['A']['Vendas A'], 'scope' => 'company', 'company_id' => $A['company'],
]]]), 200);

// Uma única unidade disponível: sem seletor e entrada direta.
$olga = login('olga@outra.e10.test');
$etiquetas = $olga->call('GET', $b.'/etiquetas');
$units = $etiquetas['body']['organization_context']['establishments'] ?? $etiquetas['body']['organization_context']['units'] ?? [];
check($j, 'Uma unidade: contexto sem seleção múltipla', count($units) <= 1, count($units));
$response = $enter($olga, $b, '');
check($j, 'Uma unidade: entrada direta na unidade', str_ends_with($target($response), '/estabelecimento/'.$B['primary_code']), $target($response));
status($j, 'Uma unidade: outra unidade da mesma conta responde 404', $olga->call('GET', $b.'/estabelecimento/todos?unidades='.$B['primary_code'].',outra-filial-salvador'), 404);

// Escritas exigem o contexto específico: o consolidado não grava e todos não é empresa.
status($j, 'Código reservado todos não é empresa de escrita', $ana->call('POST', $a.'/empresa/todos/clientes', ['person_type' => 'individual', 'name' => 'X', 'document' => cpf('300400500')]), 404);
status($j, 'Empresa da B na rota de escrita da A responde 404', $ana->call('POST', $a.'/empresa/'.$B['company_code'].'/clientes', ['person_type' => 'individual', 'name' => 'X', 'document' => cpf('300400500')]), 404);
check($j, 'Nenhum cliente criado pelas tentativas', DB::table('people')->where('document', cpf('300400500'))->doesntExist());

finish('06-area-trabalho');
