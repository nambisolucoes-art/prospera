<?php

// Jornada 2: empresas, matriz, filiais e principal operacional, pela interface HTTP da conta.
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

require __DIR__.'/http.php';

$j = 'J2 organizacao';
$accounts = state()['accounts'];
$a = '/contas/'.$accounts['A']['code'];
$b = '/contas/'.$accounts['B']['code'];
$ana = login('ana@gartan.e10.test');
$bruno = login('bruno@outra.e10.test');
$a1 = $accounts['A']['company'];
$rootA1 = $accounts['A']['root'];
$establishmentCount = fn (int $company): int => DB::table('establishments')->where('company_id', $company)->count();
$audit = fn (): int => DB::table('audit_records')->count();

// Duas filiais da A1 (mesma raiz), sem inferir classificação pela ordem.
foreach (['0002' => 'Gartan Filial Guanambi', '0003' => 'Gartan Filial Brumado'] as $order => $name) {
    $response = $ana->call('POST', $a.'/estabelecimentos', [
        'company_id' => $a1,
        'name' => $name,
        'classification' => 'branch',
        'tax_document' => cnpj($rootA1, $order),
    ]);
    status($j, 'Filial '.$order.' da A1 cadastrada', $response, 200);
}
$guanambi = DB::table('establishments')->where('company_id', $a1)->where('tax_document', cnpj($rootA1, '0002'))->first();
$brumado = DB::table('establishments')->where('company_id', $a1)->where('tax_document', cnpj($rootA1, '0003'))->first();
check($j, 'Filiais da A1 persistidas como filiais não principais',
    $guanambi && $brumado && ! $guanambi->is_headquarters && ! $guanambi->is_primary && ! $brumado->is_headquarters);

// Segunda matriz na mesma empresa.
$before = [$establishmentCount($a1), $audit()];
$response = $ana->call('POST', $a.'/estabelecimentos', [
    'company_id' => $a1, 'name' => 'Segunda matriz', 'classification' => 'headquarters',
    'tax_document' => cnpj($rootA1, '0004'),
]);
status($j, 'Segunda matriz recusada', $response, 422);
check($j, 'Segunda matriz: nada gravado', $before === [$establishmentCount($a1), $audit()]);

// Raiz incompatível.
$response = $ana->call('POST', $a.'/estabelecimentos', [
    'company_id' => $a1, 'name' => 'Raiz alheia', 'classification' => 'branch', 'tax_document' => cnpj('11223344', '0002'),
]);
status($j, 'CNPJ de raiz incompatível recusado', $response, 422);
check($j, 'Raiz incompatível: nada gravado', $before === [$establishmentCount($a1), $audit()]);

// Mesmo CNPJ de estabelecimento repetido na conta.
$response = $ana->call('POST', $a.'/estabelecimentos', [
    'company_id' => $a1, 'name' => 'Repetida', 'classification' => 'branch', 'tax_document' => cnpj($rootA1, '0002'),
]);
status($j, 'CNPJ de estabelecimento repetido recusado', $response, 422);

// Empresa A2 com CNPJ alfanumérico; ordem 0001 declarada como filial (sem matriz cadastrada).
$rootA2 = 'G7RT4N2B';
$response = $ana->call('POST', $a.'/empresas', [
    'name' => 'Gartan Logística Ltda',
    'trade_name' => 'Gartan Logística',
    'person_type' => 'company',
    'document' => cnpj($rootA2, '0001'),
    'active' => true,
    'primary' => ['name' => 'Logística Janaúba', 'classification' => 'branch'],
]);
status($j, 'Empresa A2 com CNPJ alfanumérico cadastrada', $response, 200);
$a2 = DB::table('companies')->where('account_id', $accounts['A']['id'])->where('tax_root', $rootA2)->first();
$a2Primary = DB::table('establishments')->where('company_id', $a2?->id)->first();
check($j, 'A2: raiz alfanumérica e raiz distinta da A1', $a2 !== null && $a2->tax_root === $rootA2 && $rootA2 !== $rootA1);
check($j, 'A2: ordem 0001 não vira matriz; filial é principal sem matriz cadastrada',
    $a2Primary && $a2Primary->is_primary && ! $a2Primary->is_headquarters && $a2Primary->tax_document === cnpj($rootA2, '0001'));
$response = $ana->call('POST', $a.'/estabelecimentos', [
    'company_id' => $a2->id, 'name' => 'Logística Montes Claros', 'classification' => 'branch',
    'tax_document' => cnpj($rootA2, '0002'),
]);
status($j, 'Filial alfanumérica adicional da A2', $response, 200);
$response = $ana->call('POST', $a.'/empresas', [
    'name' => 'Raiz repetida', 'person_type' => 'company', 'document' => $rootA2, 'active' => true,
]);
status($j, 'Raiz de CNPJ repetida na mesma conta recusada', $response, 422);

// Mesma raiz em outra conta é aceita sem revelar a existência (PD-03).
$response = $bruno->call('POST', $b.'/empresas', [
    'name' => 'Homônima em B', 'person_type' => 'company', 'document' => cnpj($rootA2, '0009'), 'active' => true,
    'primary' => ['name' => 'Homônima', 'classification' => 'branch'],
]);
status($j, 'Mesma raiz em outra conta aceita', $response, 200);
$homonym = DB::table('companies')->where('account_id', $accounts['B']['id'])->where('tax_root', $rootA2)->first();

// Conta B: estabelecimento próprio adicional (filial da empresa B).
$response = $bruno->call('POST', $b.'/estabelecimentos', [
    'company_id' => $accounts['B']['company'], 'name' => 'Outra Filial Salvador', 'classification' => 'branch',
    'tax_document' => cnpj($accounts['B']['root'], '0002'),
]);
status($j, 'Filial própria da Conta B', $response, 200);

// Referência cruzada: Ana não cria unidade em empresa da Conta B pela URL da Conta A, nem B pela própria URL.
$response = $ana->call('POST', $a.'/estabelecimentos', [
    'company_id' => $accounts['B']['company'], 'name' => 'Invasora', 'classification' => 'unregistered',
]);
status($j, 'Empresa de outra conta pelo ID responde 404', $response, 404);
$response = $ana->call('GET', $a.'/empresas/'.$accounts['B']['company'].'/editar');
status($j, 'Edição de empresa de outra conta responde 404', $response, 404);

// Principal operacional: promover a filial de Guanambi; a matriz continua matriz.
$response = $ana->call('POST', $a.'/estabelecimentos/'.$guanambi->id.'/principal');
status($j, 'Promoção da filial a principal', $response, 200);
$headquarters = DB::table('establishments')->where('id', $accounts['A']['primary'])->first();
$guanambi = DB::table('establishments')->where('id', $guanambi->id)->first();
check($j, 'Principal mudou sem alterar a classificação fiscal',
    $guanambi->is_primary && ! $guanambi->is_headquarters && ! $headquarters->is_primary && $headquarters->is_headquarters);
check($j, 'Uma única principal na A1', DB::table('establishments')->where('company_id', $a1)->where('is_primary', true)->count() === 1);

// Inativar a principal é recusado; inativar e reativar a antiga matriz funciona.
$response = $ana->call('PATCH', $a.'/estabelecimentos/'.$guanambi->id, ['active' => false]);
status($j, 'Inativar a principal recusado', $response, 422);
$response = $ana->call('PATCH', $a.'/estabelecimentos/'.$brumado->id, ['active' => false]);
status($j, 'Inativar filial', $response, 200);
$response = $ana->call('POST', $a.'/estabelecimentos/'.$brumado->id.'/principal');
status($j, 'Promover unidade inativa recusado', $response, 422);
check($j, 'Principal preservada após tentativa inválida',
    DB::table('establishments')->where('id', $guanambi->id)->value('is_primary') === true);
$response = $ana->call('PATCH', $a.'/estabelecimentos/'.$brumado->id, ['active' => true]);
status($j, 'Reativar filial', $response, 200);

// Inativar e reativar a empresa A2.
$response = $ana->call('PATCH', $a.'/empresas/'.$a2->id, ['active' => false]);
status($j, 'Inativar empresa A2', $response, 200);
check($j, 'A2 inativa preserva principal ativa conforme contrato',
    DB::table('companies')->where('id', $a2->id)->value('active') === false);
$response = $ana->call('PATCH', $a.'/empresas/'.$a2->id, ['active' => true]);
status($j, 'Reativar empresa A2', $response, 200);

// Código imutável: o PATCH ignora o código enviado.
$response = $ana->call('PATCH', $a.'/empresas/'.$a2->id, ['code' => 'outro-codigo']);
check($j, 'Código da empresa imutável', DB::table('companies')->where('id', $a2->id)->value('code') === $a2->code, $response['status']);

// Auditoria: eventos de empresa com company_id; de unidade com company_id e establishment_id.
$establishmentEvents = DB::table('audit_records')->where('account_id', $accounts['A']['id'])
    ->where('entity_type', 'establishment')->get();
check($j, 'Eventos de unidade gravam empresa e unidade',
    $establishmentEvents->isNotEmpty() && $establishmentEvents->every(fn ($e) => $e->company_id !== null && (int) $e->establishment_id === (int) $e->entity_id));
$companyEvents = DB::table('audit_records')->where('entity_type', 'company')->where('entity_id', $a2->id)->get();
check($j, 'Eventos da A2: criação, inativação e reativação com company_id e sem unidade',
    $companyEvents->count() >= 3 && $companyEvents->every(fn ($e) => (int) $e->company_id === $a2->id && $e->establishment_id === null),
    $companyEvents->pluck('event'));
check($j, 'Tentativas recusadas não geraram evento de segunda matriz ou raiz alheia',
    DB::table('audit_records')->whereIn('entity_name', ['Segunda matriz', 'Raiz alheia', 'Invasora', 'Repetida'])->count() === 0);
check($j, 'Evento da homônima pertence à Conta B',
    DB::table('audit_records')->where('entity_type', 'company')->where('entity_id', $homonym->id)->pluck('account_id')->unique()->all() === [$accounts['B']['id']]);

// Restrições no PostgreSQL, além das validações em PHP.
try {
    DB::transaction(fn () => DB::table('establishments')->where('id', $brumado->id)->update(['is_headquarters' => true]));
    check($j, 'Índice parcial impede segunda matriz no banco', false);
} catch (QueryException $exception) {
    check($j, 'Índice parcial impede segunda matriz no banco', $exception->getCode() === '23505', $exception->getCode());
}
try {
    DB::transaction(fn () => DB::table('establishments')->where('id', $guanambi->id)->update(['company_id' => $a2->id]));
    check($j, 'FK/índice impede mover unidade entre empresas com principal duplicada', false);
} catch (QueryException $exception) {
    check($j, 'FK/índice impede mover unidade entre empresas com principal duplicada', true, $exception->getCode());
}
$organizationCheck = shell_exec('cd /app && php artisan organization:check 2>&1');
check($j, 'organization:check sem violações', str_contains((string) $organizationCheck, 'nenhuma violação'), trim((string) $organizationCheck));

$state = state()['accounts'];
$state['A'] += [
    'company2' => $a2->id, 'company2_code' => $a2->code, 'company2_primary' => $a2Primary->id,
    'company2_primary_code' => $a2Primary->code, 'guanambi' => $guanambi->id, 'guanambi_code' => $guanambi->code,
    'brumado' => $brumado->id, 'brumado_code' => $brumado->code,
];
$state['B']['homonym'] = $homonym->id;
remember('accounts', $state);

finish('02-organizacao');
