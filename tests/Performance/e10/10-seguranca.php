<?php

// Matriz de isolamento entre contas: o administrador da B tenta alcançar cada recurso da A por ID, formulário e filtro.
use Illuminate\Support\Facades\DB;

require __DIR__.'/http.php';

$j = 'Seguranca';
$accounts = state()['accounts'];
$A = $accounts['A'];
$B = $accounts['B'];
$a = '/contas/'.$A['code'];
$b = '/contas/'.$B['code'];
$bruno = login('bruno@outra.e10.test');
$tables = ['clients', 'companies', 'establishments', 'people', 'customers', 'suppliers', 'tags', 'roles', 'permission_role',
    'role_availability', 'account_memberships', 'role_assignments', 'account_invitations', 'audit_records', 'users'];
$fingerprint = fn (): array => collect($tables)->mapWithKeys(fn ($t) => [$t => hash('sha256', json_encode(
    DB::table($t)->orderBy(DB::getSchemaBuilder()->hasColumn($t, 'id') ? 'id' : DB::raw('1'))->get(),
))])->all();

$idsA = [
    'company' => $A['company'],
    'establishment' => $A['brumado'],
    'role' => DB::table('roles')->where('account_id', $A['id'])->where('name', 'Vendas A')->value('id'),
    'membership' => DB::table('account_memberships')->where('account_id', $A['id'])->value('id'),
    'invitation' => DB::table('account_invitations')->where('account_id', $A['id'])->value('id'),
    'tag' => DB::table('tags')->where('account_id', $A['id'])->where('name', 'Cliente VIP')->value('id'),
    'customer' => state()['commercial']['customerA1'],
    'supplier' => DB::table('suppliers')->where('account_id', $A['id'])->value('id'),
    'audit' => DB::table('audit_records')->where('account_id', $A['id'])->orderByDesc('id')->value('id'),
    'person' => state()['commercial']['person'],
];
$bCompany = $b.'/empresa/'.$B['company_code'];
$matrix = [
    ['GET', $b.'/empresas/'.$idsA['company'].'/visualizar', null],
    ['GET', $b.'/empresas/'.$idsA['company'].'/editar', null],
    ['PATCH', $b.'/empresas/'.$idsA['company'], ['name' => 'Invadida']],
    ['PATCH', $b.'/empresas/'.$idsA['company'], ['active' => false]],
    ['GET', $b.'/estabelecimentos/'.$idsA['establishment'].'/visualizar', null],
    ['GET', $b.'/estabelecimentos/'.$idsA['establishment'].'/editar', null],
    ['PATCH', $b.'/estabelecimentos/'.$idsA['establishment'], ['name' => 'Invadida']],
    ['POST', $b.'/estabelecimentos/'.$idsA['establishment'].'/principal', null],
    ['POST', $b.'/estabelecimentos', ['company_id' => $idsA['company'], 'name' => 'X', 'classification' => 'unregistered']],
    ['GET', $b.'/cargos/'.$idsA['role'].'/visualizar', null],
    ['GET', $b.'/cargos/'.$idsA['role'].'/editar', null],
    ['GET', $b.'/cargos/'.$idsA['role'].'/impacto-abrangencia', null],
    ['PATCH', $b.'/cargos/'.$idsA['role'], ['name' => 'Invadido']],
    ['GET', $b.'/usuarios/'.$idsA['membership'].'/atribuicoes', null],
    ['PATCH', $b.'/usuarios/'.$idsA['membership'].'/atribuicoes', ['revocations' => [1]]],
    ['PATCH', $b.'/usuarios/'.$idsA['membership'].'/suspender', null],
    ['PATCH', $b.'/usuarios/'.$idsA['membership'].'/reativar', null],
    ['POST', $b.'/usuarios/'.$idsA['membership'].'/recuperacao', null],
    ['PATCH', $b.'/usuarios/convites/'.$idsA['invitation'].'/revogar', null],
    ['GET', $b.'/etiquetas/'.$idsA['tag'].'/visualizar', null],
    ['GET', $b.'/etiquetas/'.$idsA['tag'].'/editar', null],
    ['PATCH', $b.'/etiquetas/'.$idsA['tag'], ['name' => 'Invadida', 'module' => 'customers', 'color' => '#000000']],
    ['DELETE', $b.'/etiquetas/'.$idsA['tag'], null],
    ['GET', $bCompany.'/clientes/'.$idsA['customer'].'/visualizar', null],
    ['PATCH', $bCompany.'/clientes/'.$idsA['customer'], ['phone' => '7100000000']],
    ['PATCH', $bCompany.'/clientes/'.$idsA['customer'].'/inativar', null],
    ['PATCH', $bCompany.'/fornecedores/'.$idsA['supplier'].'/ativar', null],
    ['GET', $b.'/estabelecimento/'.$B['primary_code'].'/clientes/'.$A['company_code'].'/'.$idsA['customer'].'/editar', null],
    ['GET', $b.'/auditoria/'.$idsA['audit'].'/visualizar', null],
    ['GET', $b.'/empresa/'.$A['company_code'].'/clientes/'.$idsA['customer'].'/visualizar', null],
    ['GET', $b.'/estabelecimento/'.$A['primary_code'].'/clientes', null],
    ['GET', $b.'/estabelecimento/todos/clientes?unidades='.$A['primary_code'], null],
    ['GET', $a.'/empresas', null],
];

$before = $fingerprint();
foreach ($matrix as [$method, $path, $input]) {
    $response = $bruno->call($method, $path, $input);
    $passed = in_array($response['status'], [403, 404], true);
    $leak = str_contains($response['raw'], 'Gartan') || str_contains($response['raw'], 'Marta Queiroz');
    check($j, 'B '.$method.' '.$path.' -> 403/404 sem dados da A', $passed && ! $leak, ['status' => $response['status'], 'leak' => $leak]);
}

// Referências da A dentro de formulários válidos da B.
$forms = [
    ['POST', $b.'/usuarios', ['name' => 'X', 'email' => 'x.b@e10.test', 'mode' => 'invite', 'role_id' => $idsA['role']]],
    ['POST', $b.'/usuarios', ['name' => 'X', 'email' => 'y.b@e10.test', 'mode' => 'invite', 'role_id' => state()['roles']['B']['Operador B'],
        'scope' => 'company', 'company_id' => $idsA['company']]],
    ['POST', $b.'/usuarios', ['name' => 'X', 'email' => 'z.b@e10.test', 'mode' => 'invite', 'role_id' => state()['roles']['B']['Operador B'],
        'scope' => 'establishment', 'company_id' => $B['company'], 'establishment_id' => $idsA['establishment']]],
    ['POST', $b.'/usuarios', ['person_registration' => 'existing', 'person_id' => $idsA['person'], 'email' => 'w.b@e10.test', 'mode' => 'invite',
        'role_id' => state()['roles']['B']['Operador B']]],
    ['POST', $b.'/cargos', ['name' => 'Ref A', 'availability' => 'restricted', 'available_companies' => [$idsA['company']]]],
    ['POST', $b.'/cargos', ['name' => 'Ref A2', 'availability' => 'restricted', 'available_establishments' => [$idsA['establishment']]]],
    ['POST', $bCompany.'/clientes', ['person_type' => 'individual', 'name' => 'X', 'document' => cpf('811911011'), 'tag_ids' => [$idsA['tag']]]],
    ['POST', $bCompany.'/clientes', ['person_id' => $idsA['person']]],
    ['POST', $bCompany.'/clientes', ['person_type' => 'individual', 'name' => 'X', 'document' => cpf('811911012'), 'add_companies' => [$A['company_code']]]],
    ['PATCH', $b.'/usuarios/'.DB::table('account_memberships')->where('account_id', $B['id'])->value('id').'/atribuicoes',
        ['grants' => [['role_id' => $idsA['role'], 'scope' => 'tenant']]]],
];
foreach ($forms as [$method, $path, $input]) {
    $response = $bruno->call($method, $path, $input);
    check($j, 'Formulário da B com referência da A recusado: '.$method.' '.str_replace($b, '{B}', $path),
        in_array($response['status'], [403, 404, 422], true), ['status' => $response['status'], 'body' => mb_substr($response['raw'], 0, 200)]);
}

// Filtros e contagens da B não são influenciados pela A.
$audit = $bruno->call('GET', $b.'/auditoria?company='.$idsA['company']);
check($j, 'Filtro de auditoria com empresa da A não conta eventos', ($audit['body']['records']['total'] ?? -1) === 0, $audit['status']);
$tagsB = $bruno->call('GET', $b.'/etiquetas?search=Cliente%20VIP');
check($j, 'Busca de etiquetas da B não traz a etiqueta da A', collect($tagsB['body']['tags']['data'] ?? [])->pluck('id')->doesntContain($idsA['tag']));
check($j, 'Nenhuma tentativa alterou o banco (hash de 15 tabelas)', $before === $fingerprint(),
    array_keys(array_diff_assoc($before, $fingerprint())));

// Autenticação e CSRF.
$anonymous = new Browser('anonimo');
status($j, 'Anônimo não abre conta', $anonymous->call('GET', $a), 401);
status($j, 'Anônimo não abre seleção', $anonymous->call('GET', '/contas'), 401);
$noCsrf = $bruno->call('POST', $b.'/etiquetas', ['_token' => 'invalido', 'name' => 'Sem CSRF', 'module' => 'customers', 'color' => '#000000']);
status($j, 'POST sem CSRF válido recusado', $noCsrf, 419);
check($j, 'Sem CSRF nada gravado', DB::table('tags')->where('name', 'Sem CSRF')->doesntExist());

// Limitação de tentativas de login (mensagem em português, sem revelar a existência do e-mail).
$guest = new Browser('forca-bruta');
$statuses = [];
foreach (range(1, 7) as $attempt) {
    $statuses[] = $guest->call('POST', '/login', ['email' => 'inexistente@e10.test', 'password' => 'errada'])['status'];
}
check($j, 'Login limitado após tentativas', in_array(429, $statuses, true), $statuses);
$existing = (new Browser('forca-bruta-2'));
$wrong = $existing->call('POST', '/login', ['email' => 'ana@gartan.e10.test', 'password' => 'errada']);
$missing = $existing->call('POST', '/login', ['email' => 'naoexiste@e10.test', 'password' => 'errada']);
check($j, 'Erro de login idêntico para e-mail existente e inexistente', $wrong['raw'] === $missing['raw'], [$wrong['status'], $missing['status']]);
$forgot1 = (new Browser('esqueci'))->call('POST', '/forgot-password', ['email' => 'ana@gartan.e10.test']);
$forgot2 = (new Browser('esqueci-2'))->call('POST', '/forgot-password', ['email' => 'ninguem@e10.test']);
check($j, 'Recuperação de acesso com resposta neutra', $forgot1['status'] === $forgot2['status'] && $forgot1['raw'] === $forgot2['raw'],
    [$forgot1['status'], $forgot2['status']]);

// Sessões e cookies: HttpOnly na sessão.
$headers = get_headers('http://127.0.0.1:8000/login', true);
$cookies = implode(' | ', (array) ($headers['Set-Cookie'] ?? $headers['set-cookie'] ?? []));
check($j, 'Cookie de sessão HttpOnly e SameSite', str_contains(strtolower($cookies), 'httponly') && str_contains(strtolower($cookies), 'samesite'), null);

finish('10-seguranca');
