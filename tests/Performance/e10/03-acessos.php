<?php

// Jornada 5: cargos, disponibilidade, atribuições contextuais, vínculos e matriz de operações por ID.
use Illuminate\Support\Facades\DB;

require __DIR__.'/http.php';

$j = 'J5 acessos';
$accounts = state()['accounts'];
$A = $accounts['A'];
$B = $accounts['B'];
$a = '/contas/'.$A['code'];
$b = '/contas/'.$B['code'];
$ana = login('ana@gartan.e10.test');
$bruno = login('bruno@outra.e10.test');
$ids = fn (array $slugs): array => DB::table('permissions')->whereIn('slug', $slugs)->pluck('id')->map(fn ($id) => (int) $id)->all();
$roleId = fn (int $account, string $name): ?int => DB::table('roles')->where('account_id', $account)->where('name', $name)->value('id');
$membership = fn (int $account, string $email): ?object => DB::table('account_memberships as m')
    ->join('users as u', 'u.id', '=', 'm.user_id')
    ->where('m.account_id', $account)->where('u.email', $email)->select('m.*')->first();
$counts = fn (): array => [
    'roles' => DB::table('roles')->count(),
    'permission_role' => DB::table('permission_role')->count(),
    'assignments' => DB::table('role_assignments')->count(),
    'memberships' => DB::table('account_memberships')->select('status')->get()->countBy('status')->all(),
    'users' => DB::table('users')->count(),
    'customers' => DB::table('customers')->count(),
    'companies' => DB::table('companies')->select('active')->get()->countBy(fn ($c) => (int) $c->active)->all(),
    'audit' => DB::table('audit_records')->count(),
];

$catalog = [
    'Vendas A' => [
        'account.customers.list', 'account.customers.view', 'account.customers.create', 'account.customers.update',
        'account.customers.activate', 'account.customers.deactivate', 'account.suppliers.list', 'account.suppliers.view',
        'account.people.view', 'account.people.create', 'account.people.update', 'account.tags.list',
        'account.establishments.list', 'account.establishments.view', 'account.companies.list', 'account.companies.view',
        'self.help.list', 'self.help.view',
    ],
    'Consulta A' => [
        'account.customers.list', 'account.customers.view', 'account.suppliers.list', 'account.suppliers.view',
        'account.people.view', 'account.tags.list', 'account.tags.view', 'account.companies.list',
        'account.companies.view', 'account.establishments.list', 'account.establishments.view',
        'account.roles.list', 'account.roles.view', 'account.users.list', 'account.users.view',
        'self.help.list', 'self.help.view',
    ],
    // Alvos de customers.* e suppliers.* são só company: cargos de unidade não os recebem (config/permissions.php).
    'Gerente de unidade' => [
        'account.people.view', 'account.people.create', 'account.establishments.list', 'account.establishments.view',
        'account.establishments.update', 'account.users.list', 'account.users.view', 'account.users.create',
        'account.users.assign-role', 'account.audit.list', 'account.audit.view', 'self.help.list', 'self.help.view',
    ],
    'Logística restrita' => [
        'account.suppliers.list', 'account.suppliers.view', 'account.suppliers.create', 'account.people.view',
        'account.people.create', 'self.help.list', 'self.help.view',
    ],
    'Somente Ajuda' => ['self.help.list', 'self.help.view'],
];

foreach ($catalog as $name => $slugs) {
    $input = ['name' => $name, 'permission_ids' => $ids($slugs)];

    if ($name === 'Logística restrita') {
        $input += ['availability' => 'restricted', 'available_companies' => [$A['company2']]];
    }

    status($j, 'Cargo '.$name.' cadastrado', $ana->call('POST', $a.'/cargos', $input), 200);
}

$response = $bruno->call('POST', $b.'/cargos', ['name' => 'Operador B', 'permission_ids' => $ids([
    'account.tags.list', 'account.tags.view', 'account.establishments.list', 'account.establishments.view',
    'account.people.view', 'account.dashboard.view', 'self.help.list', 'self.help.view',
])]);
status($j, 'Cargo Operador B na Conta B', $response, 200);
check($j, 'Cargos da Conta A e da Conta B são independentes',
    $roleId($B['id'], 'Vendas A') === null && $roleId($A['id'], 'Operador B') === null);

// Cargos de conta não recebem permissões de plataforma, nem por envio forjado.
$platformPermission = DB::table('permissions')->where('scope', 'platform')->value('id');
$response = $ana->call('POST', $a.'/cargos', ['name' => 'Forjado', 'permission_ids' => [$platformPermission]]);
status($j, 'Permissão platform em cargo da conta recusada', $response, 422);

// Usuários novos (identidade, vínculo e primeira atribuição) em escopos diferentes.
$users = [
    'henrique@gartan.e10.test' => ['Administrador da conta', 'tenant', null, null],
    'carlos@gartan.e10.test' => ['Vendas A', 'company', $A['company'], null],
    'consulta@gartan.e10.test' => ['Consulta A', 'tenant', null, null],
    'unidade@gartan.e10.test' => ['Gerente de unidade', 'establishment', $A['company'], $A['brumado']],
    'logistica@gartan.e10.test' => ['Logística restrita', 'company', $A['company2'], null],
    'suspenso@gartan.e10.test' => ['Vendas A', 'company', $A['company'], null],
    'semvinculo@gartan.e10.test' => ['Consulta A', 'tenant', null, null],
    'ajuda@gartan.e10.test' => ['Somente Ajuda', 'tenant', null, null],
];

foreach ($users as $email => [$role, $scope, $company, $establishment]) {
    $response = $ana->call('POST', $a.'/usuarios', [
        'name' => ucfirst(strtok($email, '@')).' Gartan',
        'email' => $email,
        'mode' => 'manual',
        'password' => E10_PASSWORD,
        'password_confirmation' => E10_PASSWORD,
        'role_id' => $roleId($A['id'], $role),
        'scope' => $scope,
        'company_id' => $company,
        'establishment_id' => $establishment,
    ]);
    status($j, 'Usuário '.$email.' criado ('.$role.', '.$scope.')', $response, 200);
}
check($j, 'Cada usuário novo tem uma pessoa própria da Conta A',
    DB::table('account_memberships')->where('account_id', $A['id'])->whereNull('person_id')->count() === 0);

// Cargo restrito à A2 não pode ser atribuído na A1.
$response = $ana->call('POST', $a.'/usuarios', [
    'name' => 'Indevido', 'email' => 'indevido@gartan.e10.test', 'mode' => 'manual',
    'password' => E10_PASSWORD, 'password_confirmation' => E10_PASSWORD,
    'role_id' => $roleId($A['id'], 'Logística restrita'), 'scope' => 'company', 'company_id' => $A['company'],
]);
status($j, 'Cargo indisponível na empresa recusado', $response, [403, 422]);
check($j, 'Cargo indisponível: identidade não criada', DB::table('users')->where('email', 'indevido@gartan.e10.test')->doesntExist());

// Conta B: operador limitado a uma unidade.
$response = $bruno->call('POST', $b.'/usuarios', [
    'name' => 'Olga B', 'email' => 'olga@outra.e10.test', 'mode' => 'manual',
    'password' => E10_PASSWORD, 'password_confirmation' => E10_PASSWORD,
    'role_id' => $roleId($B['id'], 'Operador B'), 'scope' => 'establishment',
    'company_id' => $B['company'], 'establishment_id' => $B['primary'],
]);
status($j, 'Operador B criado numa unidade da Conta B', $response, 200);

// Antiescalada: o gerente da unidade Brumado não concede cargo com permissões que não tem, nem fora da unidade.
$manager = login('unidade@gartan.e10.test');
$carlos = $membership($A['id'], 'carlos@gartan.e10.test');
$before = $counts();
$response = $manager->call('PATCH', $a.'/usuarios/'.$carlos->id.'/atribuicoes', ['grants' => [[
    'role_id' => $roleId($A['id'], 'Administrador da conta'), 'scope' => 'establishment',
    'company_id' => $A['company'], 'establishment_id' => $A['brumado'],
]]]);
status($j, 'Gerente de unidade não concede Administrador', $response, 403);
$response = $manager->call('PATCH', $a.'/usuarios/'.$carlos->id.'/atribuicoes', ['grants' => [[
    'role_id' => $roleId($A['id'], 'Gerente de unidade'), 'scope' => 'establishment',
    'company_id' => $A['company'], 'establishment_id' => $A['guanambi'],
]]]);
status($j, 'Gerente de unidade não atribui em outra unidade', $response, [403, 404]);
$response = $manager->call('PATCH', $a.'/usuarios/'.$carlos->id.'/atribuicoes', ['grants' => [[
    'role_id' => $roleId($A['id'], 'Gerente de unidade'), 'scope' => 'company', 'company_id' => $A['company'],
]]]);
status($j, 'Gerente de unidade não atribui no nível da empresa', $response, [403, 404]);
$carlosAssignment = DB::table('role_assignments')->where('membership_id', $carlos->id)->value('id');
$response = $manager->call('PATCH', $a.'/usuarios/'.$carlos->id.'/atribuicoes', ['revocations' => [$carlosAssignment]]);
status($j, 'Gerente de unidade não retira atribuição de nível superior', $response, [403, 404]);
$response = $manager->call('PATCH', $a.'/usuarios/'.$carlos->id.'/suspender');
status($j, 'Gerente de unidade não suspende vínculo', $response, 403);
$response = $manager->call('POST', $a.'/cargos', ['name' => 'Escalada', 'permission_ids' => []]);
status($j, 'Gerente de unidade não cria cargo', $response, 403);
check($j, 'Negações não alteraram dados nem auditoria', $before === $counts(), [$before, $counts()]);

// Concessão local legítima: o gerente concede Gerente de unidade a Carlos na própria unidade.
$response = $manager->call('PATCH', $a.'/usuarios/'.$carlos->id.'/atribuicoes', ['grants' => [[
    'role_id' => $roleId($A['id'], 'Gerente de unidade'), 'scope' => 'establishment',
    'company_id' => $A['company'], 'establishment_id' => $A['brumado'],
]]]);
status($j, 'Gerente de unidade concede cargo local na própria unidade', $response, 200);
$granted = DB::table('role_assignments')->where('membership_id', $carlos->id)->where('establishment_id', $A['brumado'])->first();
check($j, 'Atribuição local gravada com empresa e unidade', $granted && (int) $granted->company_id === $A['company']);
$event = DB::table('audit_records')->where('entity_type', 'role-assignment')->where('entity_id', $granted->id)->first();
check($j, 'Evento da atribuição com nó da unidade e autor real',
    $event && (int) $event->establishment_id === $A['brumado'] && (int) $event->company_id === $A['company']
    && (int) $event->actor_id === DB::table('users')->where('email', 'unidade@gartan.e10.test')->value('id'));
$view = $manager->call('GET', $a.'/usuarios/'.$carlos->id.'/atribuicoes');
$visible = collect($view['body']['assignments'] ?? [])->pluck('id')->all();
check($j, 'Gerente de unidade vê só as atribuições do próprio escopo',
    in_array($granted->id, $visible, true) && ! in_array($carlosAssignment, $visible, true), $visible);

// Matriz de operações por perfil e por ID.
$consult = login('consulta@gartan.e10.test');
$seller = login('carlos@gartan.e10.test');
$before = $counts();
$matrix = [
    ['consulta', $consult, 'GET', $a.'/empresas', null, 200],
    ['consulta', $consult, 'GET', $a.'/empresas/'.$A['company'].'/visualizar', null, 200],
    ['consulta', $consult, 'GET', $a.'/empresas/'.$A['company'].'/editar', null, [403, 404]],
    ['consulta', $consult, 'PATCH', $a.'/empresas/'.$A['company'], ['name' => 'Alterada'], [403, 404]],
    ['consulta', $consult, 'PATCH', $a.'/empresas/'.$A['company'], ['active' => false], [403, 404]],
    ['consulta', $consult, 'POST', $a.'/empresas', ['name' => 'Nova', 'person_type' => 'company', 'document' => '99001122', 'active' => true], 403],
    ['consulta', $consult, 'POST', $a.'/estabelecimentos', ['company_id' => $A['company'], 'name' => 'X', 'classification' => 'unregistered'], [403, 404]],
    ['consulta', $consult, 'PATCH', $a.'/estabelecimentos/'.$A['brumado'], ['active' => false], [403, 404]],
    ['consulta', $consult, 'POST', $a.'/estabelecimentos/'.$A['brumado'].'/principal', null, [403, 404]],
    ['consulta', $consult, 'GET', $a.'/cargos', null, 200],
    ['consulta', $consult, 'POST', $a.'/cargos', ['name' => 'X'], 403],
    ['consulta', $consult, 'PATCH', $a.'/cargos/'.$roleId($A['id'], 'Consulta A'), ['name' => 'X'], 403],
    ['consulta', $consult, 'GET', $a.'/usuarios', null, 200],
    ['consulta', $consult, 'POST', $a.'/usuarios', ['name' => 'X', 'email' => 'x@e10.test', 'mode' => 'invite', 'role_id' => $roleId($A['id'], 'Consulta A')], 403],
    ['consulta', $consult, 'PATCH', $a.'/usuarios/'.$carlos->id.'/suspender', null, 403],
    ['consulta', $consult, 'PATCH', $a.'/usuarios/'.$carlos->id.'/atribuicoes', ['revocations' => [$carlosAssignment]], 403],
    ['consulta', $consult, 'GET', $a.'/auditoria', null, 403],
    ['consulta', $consult, 'GET', $a.'/editar', null, 403],
    ['consulta', $consult, 'GET', $a.'/etiquetas/cadastrar', null, 403],
    ['consulta', $consult, 'POST', $a.'/etiquetas', ['name' => 'X', 'module' => 'customers', 'color' => '#123456'], 403],
    ['consulta', $consult, 'GET', $a.'/estabelecimento/'.$A['primary_code'].'/clientes', null, 200],
    ['consulta', $consult, 'GET', $a.'/estabelecimento/'.$A['primary_code'].'/clientes/cadastrar', null, 403],
    ['consulta', $consult, 'POST', $a.'/empresa/'.$A['company_code'].'/clientes', ['person_type' => 'individual', 'name' => 'X', 'document' => cpf('100200300')], 403],
    ['vendas', $seller, 'GET', $a.'/cargos', null, 403],
    ['vendas', $seller, 'GET', $a.'/empresa/'.$A['company2_code'].'/clientes/1/visualizar', null, [403, 404]],
    ['vendas', $seller, 'POST', $a.'/empresa/'.$A['company2_code'].'/clientes', ['person_type' => 'individual', 'name' => 'X', 'document' => cpf('100200300')], [403, 404]],
    ['vendas', $seller, 'GET', $a.'/estabelecimento/'.$A['company2_primary_code'].'/clientes', null, [403, 404]],
    ['vendas', $seller, 'GET', $b.'/empresas', null, 404],
    ['vendas', $seller, 'GET', $a.'/empresas/'.$B['company'].'/visualizar', null, [403, 404]],
    ['gerente', $manager, 'PATCH', $a.'/estabelecimentos/'.$A['brumado'], ['active' => false], [403, 404]],
    ['gerente', $manager, 'POST', $a.'/estabelecimentos/'.$A['brumado'].'/principal', null, [403, 404]],
    ['gerente', $manager, 'PATCH', $a.'/estabelecimentos/'.$A['guanambi'], ['name' => 'Invadida'], [403, 404]],
    ['gerente', $manager, 'PATCH', $a.'/empresas/'.$A['company'], ['name' => 'Invadida'], [403, 404]],
];

foreach ($matrix as [$profile, $browser, $method, $path, $input, $expected]) {
    status($j, $profile.' '.$method.' '.str_replace($a, '{A}', $path), $browser->call($method, $path, $input), $expected);
}
// Carlos acumula Vendas A (empresa A1) e Gerente de unidade (Brumado): Usuários mostra só atribuições de Brumado.
$listing = $seller->call('GET', $a.'/usuarios');
$locations = collect($listing['body']['users']['data'] ?? [])->pluck('assignments')->flatten(1)->pluck('location')->unique()->values()->all();
check($j, 'Lista de usuários limitada às atribuições do escopo do operador', $locations === ['Gartan Filial Brumado'], $locations);
check($j, 'Operações negadas preservaram todos os dados e não geraram auditoria', $before === $counts(), [$before, $counts()]);

// O gerente de unidade edita a própria unidade (permissão de estabelecimento sobre o nó).
$response = $manager->call('PATCH', $a.'/estabelecimentos/'.$A['brumado'], ['phone' => '7734519999']);
status($j, 'Gerente de unidade edita a própria unidade', $response, 200);

// Revogação de permissão do cargo vale na requisição seguinte, sem reiniciar o worker.
$consultRole = $roleId($A['id'], 'Consulta A');
$withoutCompanies = array_values(array_diff($ids($catalog['Consulta A']), $ids(['account.companies.list'])));
status($j, 'Retira Empresas: listar do cargo Consulta A',
    $ana->call('PATCH', $a.'/cargos/'.$consultRole, ['name' => 'Consulta A', 'permission_ids' => $withoutCompanies]), 200);
status($j, 'Revogação vale na requisição seguinte', $consult->call('GET', $a.'/empresas'), 403);
status($j, 'Restaura a permissão', $ana->call('PATCH', $a.'/cargos/'.$consultRole, ['name' => 'Consulta A', 'permission_ids' => $ids($catalog['Consulta A'])]), 200);
status($j, 'Concessão vale na requisição seguinte', $consult->call('GET', $a.'/empresas'), 200);
$roleEvent = DB::table('audit_records')->where('entity_type', 'role')->where('entity_id', $consultRole)->where('event', 'updated')->count();
check($j, 'Alterações do cargo auditadas (duas)', $roleEvent === 2, $roleEvent);

// Suspensão e reativação do vínculo; sem vínculo ativo; vínculo sem atribuições.
$suspended = $membership($A['id'], 'suspenso@gartan.e10.test');
$suspendedSession = login('suspenso@gartan.e10.test');
status($j, 'Antes da suspensão o vínculo opera', $suspendedSession->call('GET', $a.'/estabelecimento/'.$A['primary_code'].'/clientes'), 200);
status($j, 'Suspender vínculo', $ana->call('PATCH', $a.'/usuarios/'.$suspended->id.'/suspender'), 200);
status($j, 'Sessão ativa perde acesso na requisição seguinte', $suspendedSession->call('GET', $a.'/estabelecimento/'.$A['primary_code'].'/clientes'), 403);
check($j, 'Suspensão preserva atribuições e identidade',
    DB::table('role_assignments')->where('membership_id', $suspended->id)->count() === 1
    && DB::table('users')->where('id', $suspended->user_id)->value('access_enabled') === true);
status($j, 'Reativar vínculo', $ana->call('PATCH', $a.'/usuarios/'.$suspended->id.'/reativar'), 200);
status($j, 'Reativado volta a operar', $suspendedSession->call('GET', $a.'/estabelecimento/'.$A['primary_code'].'/clientes'), 200);
status($j, 'Suspender novamente para o estado da massa', $ana->call('PATCH', $a.'/usuarios/'.$suspended->id.'/suspender'), 200);

$none = $membership($A['id'], 'semvinculo@gartan.e10.test');
status($j, 'Vínculo único suspenso: identidade sem vínculo ativo', $ana->call('PATCH', $a.'/usuarios/'.$none->id.'/suspender'), 200);
$noneSession = login('semvinculo@gartan.e10.test');
$selection = $noneSession->call('GET', '/contas');
check($j, 'Sem vínculo ativo: a seleção só informa a situação, sem endereço de entrada',
    $selection['status'] === 200 && collect($selection['body']['accounts'] ?? [])->every(fn ($x) => $x['url'] === null && $x['status'] === 'suspended'),
    ['status' => $selection['status'], 'accounts' => $selection['body']['accounts'] ?? null]);
status($j, 'Sem vínculo ativo: conta recusada', $noneSession->call('GET', $a), 403);

// Revogação de todas as atribuições: vínculo continua ativo e sem permissões.
$logistic = $membership($A['id'], 'logistica@gartan.e10.test');
$logisticAssignment = DB::table('role_assignments')->where('membership_id', $logistic->id)->value('id');
status($j, 'Retirar a última atribuição', $ana->call('PATCH', $a.'/usuarios/'.$logistic->id.'/atribuicoes', ['revocations' => [$logisticAssignment]]), 200);
check($j, 'Última atribuição retirada não suspende o vínculo',
    DB::table('account_memberships')->where('id', $logistic->id)->value('status') === 'active');
$logisticSession = login('logistica@gartan.e10.test');
$dashboard = $logisticSession->call('GET', $a);
check($j, 'Vínculo sem atribuições não tem permissões de conta',
    array_filter($dashboard['body']['auth']['permissions'] ?? [], fn ($s) => str_starts_with($s, 'account.')) === [],
    $dashboard['status']);
status($j, 'Reatribuir Logística restrita na A2',
    $ana->call('PATCH', $a.'/usuarios/'.$logistic->id.'/atribuicoes', ['grants' => [[
        'role_id' => $roleId($A['id'], 'Logística restrita'), 'scope' => 'company', 'company_id' => $A['company2'],
    ]]]), 200);

// Último administrador: Henrique suspenso, Ana não perde o próprio cargo; Henrique reativado.
$henrique = $membership($A['id'], 'henrique@gartan.e10.test');
$anaMembership = $membership($A['id'], 'ana@gartan.e10.test');
status($j, 'Suspender o segundo administrador', $ana->call('PATCH', $a.'/usuarios/'.$henrique->id.'/suspender'), 200);
$anaAdmin = DB::table('role_assignments')->where('membership_id', $anaMembership->id)->value('id');
status($j, 'Último administrador não retira o próprio cargo',
    $ana->call('PATCH', $a.'/usuarios/'.$anaMembership->id.'/atribuicoes', ['revocations' => [$anaAdmin]]), 422);
status($j, 'Último administrador não se suspende', $ana->call('PATCH', $a.'/usuarios/'.$anaMembership->id.'/suspender'), [403, 422]);
check($j, 'Administração utilizável preservada', DB::table('role_assignments')->where('id', $anaAdmin)->exists()
    && DB::table('account_memberships')->where('id', $anaMembership->id)->value('status') === 'active');
status($j, 'Reativar o segundo administrador', $ana->call('PATCH', $a.'/usuarios/'.$henrique->id.'/reativar'), 200);

// Usuário com consulta, mas sem edição de identidade de terceiros (nenhuma rota de conta altera senha ou e-mail).
$response = $ana->call('PATCH', $a.'/usuarios/'.$carlos->id.'/atribuicoes', ['email' => 'trocado@e10.test', 'password' => 'x']);
check($j, 'Envio forjado de e-mail e senha não altera a identidade',
    DB::table('users')->where('id', $carlos->user_id)->value('email') === 'carlos@gartan.e10.test', $response['status']);

remember('roles', [
    'A' => collect(array_keys($catalog))->mapWithKeys(fn ($n) => [$n => $roleId($A['id'], $n)])->all()
        + ['Administrador da conta' => $roleId($A['id'], 'Administrador da conta')],
    'B' => ['Operador B' => $roleId($B['id'], 'Operador B'), 'Administrador da conta' => $roleId($B['id'], 'Administrador da conta')],
]);

finish('03-acessos');
