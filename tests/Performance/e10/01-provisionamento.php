<?php

// Jornada 1: provisionamento SaaS pela plataforma, com HTTP real e conferência no PostgreSQL.
use Illuminate\Support\Facades\DB;

require __DIR__.'/http.php';

$j = 'J1 provisionamento';
$platform = login('platform@e10.test', 'password', 'plataforma');
$platformId = DB::table('users')->where('email', 'platform@e10.test')->value('id');

$administrator = fn (string $email, string $mode = 'manual'): array => [
    'name' => 'Admin '.$email,
    'email' => $email,
    'mode' => $mode,
    ...($mode === 'manual' ? ['password' => E10_PASSWORD, 'password_confirmation' => E10_PASSWORD] : []),
];

$snapshot = fn (): array => [
    'clients' => DB::table('clients')->count(),
    'companies' => DB::table('companies')->count(),
    'establishments' => DB::table('establishments')->count(),
    'users' => DB::table('users')->count(),
    'memberships' => DB::table('account_memberships')->count(),
    'audit' => DB::table('audit_records')->count(),
];

// Conta A: contratante PJ, cadastro manual com a primeira empresa de outra raiz (Empresa A1, matriz).
$contractorA = cnpj('41200300');
$a1Document = cnpj('52300400');
$response = $platform->call('POST', '/saas/contas', [
    'name' => 'Grupo Gartan Participações',
    'person_type' => 'company',
    'document' => $contractorA,
    'details' => ['phone' => '7734510000', 'trade_name' => 'Grupo Gartan'],
    'organization_setup' => 'manual',
    'first_company' => [
        'name' => 'Gartan Comércio Ltda',
        'trade_name' => 'Gartan Comércio',
        'person_type' => 'company',
        'document' => $a1Document,
        'primary' => ['name' => 'Gartan Matriz', 'classification' => 'headquarters'],
    ],
    'administrator' => $administrator('ana@gartan.e10.test'),
]);
status($j, 'Conta A provisionada (manual)', $response, 200);

$accountA = DB::table('clients')->where('document', $contractorA)->first();
check($j, 'Conta A persistida com código público', $accountA !== null && preg_match('/^grupo-gartan(-[a-z0-9]+)*$/', (string) $accountA->code) === 1, $accountA?->code);
$companyA1 = DB::table('companies')->where('account_id', $accountA->id)->first();
check($j, 'Empresa A1 com raiz própria, sem reutilizar o CNPJ do contratante',
    $companyA1->tax_root === substr($a1Document, 0, 8) && $companyA1->tax_root !== substr($contractorA, 0, 8),
    ['tax_root' => $companyA1->tax_root]);
check($j, 'Conta e Empresa são registros distintos', $companyA1->name === 'Gartan Comércio Ltda' && $accountA->name !== $companyA1->name);
$primaryA1 = DB::table('establishments')->where('company_id', $companyA1->id)->first();
check($j, 'Principal da A1 é matriz explícita com o CNPJ informado',
    $primaryA1->is_primary && $primaryA1->is_headquarters && $primaryA1->tax_document === $a1Document && $primaryA1->active);

// Conta B: contratante PJ com cadastro automático declarado como matriz; administrador por convite (Fortify).
$contractorB = cnpj('63400500');
$response = $platform->call('POST', '/saas/contas', [
    'name' => 'Outra Organização Serviços',
    'person_type' => 'company',
    'document' => $contractorB,
    'details' => ['phone' => '7134520000'],
    'organization_setup' => 'automatic',
    'contractor_classification' => 'headquarters',
    'administrator' => $administrator('bruno@outra.e10.test', 'invite'),
]);
status($j, 'Conta B provisionada (automático, matriz)', $response, 200);
$accountB = DB::table('clients')->where('document', $contractorB)->first();
$companyB = DB::table('companies')->where('account_id', $accountB->id)->first();
$primaryB = DB::table('establishments')->where('company_id', $companyB->id)->first();
check($j, 'Automático usa os dados do contratante só por escolha explícita',
    $companyB->tax_root === substr($contractorB, 0, 8) && $primaryB->tax_document === $contractorB && $primaryB->is_headquarters);
$bruno = DB::table('users')->where('email', 'bruno@outra.e10.test')->first();
check($j, 'Administrador convidado fica com senha pendente', (bool) $bruno->password_setup_pending);
$link = mail_link('bruno@outra.e10.test', '#http://[^\s"<]+/reset-password/[A-Za-z0-9]+\?email=[^\s"<&]+#');
check($j, 'Link de definição de senha entregue somente ao Mailpit isolado', $link !== null);
if ($link !== null) {
    preg_match('#/reset-password/([A-Za-z0-9]+)\?email=([^\s"<&]+)#', $link, $parts);
    $guest = new Browser('convidado-b');
    $guest->call('GET', '/login');
    $before = login_attempt('bruno@outra.e10.test');
    check($j, 'Senha aleatória inicial não permite login', $before !== 200 && $before !== 204, $before);
    $reset = $guest->call('POST', '/reset-password', [
        'token' => $parts[1],
        'email' => urldecode($parts[2]),
        'password' => E10_PASSWORD,
        'password_confirmation' => E10_PASSWORD,
    ]);
    status($j, 'Definição de senha pelo Fortify', $reset, [200, 302]);
    $reused = $guest->call('POST', '/reset-password', [
        'token' => $parts[1],
        'email' => urldecode($parts[2]),
        'password' => 'Outra-senha-E10',
        'password_confirmation' => 'Outra-senha-E10',
    ]);
    status($j, 'Token de definição de senha não é reutilizável', $reused, 422);
    check($j, 'Pendência de senha limpa após a definição',
        ! DB::table('users')->where('id', $bruno->id)->value('password_setup_pending'));
}

// Conta C: contratante PJ, automático declarado como filial: o único estabelecimento é filial e principal.
$contractorC = cnpj('74500600', '0003');
$response = $platform->call('POST', '/saas/contas', [
    'name' => 'Filial Única Distribuidora',
    'person_type' => 'company',
    'document' => $contractorC,
    'details' => ['phone' => '3134530000'],
    'organization_setup' => 'automatic',
    'contractor_classification' => 'branch',
    'administrator' => $administrator('carla@filial.e10.test'),
]);
status($j, 'Conta C provisionada (automático, filial)', $response, 200);
$accountC = DB::table('clients')->where('document', $contractorC)->first();
$establishmentsC = DB::table('establishments')->where('account_id', $accountC->id)->get();
check($j, 'Conta C: única unidade é filial principal, sem matriz',
    $establishmentsC->count() === 1
    && $establishmentsC[0]->is_primary
    && ! $establishmentsC[0]->is_headquarters
    && $establishmentsC[0]->tax_document === $contractorC);

// Conta D: contratante pessoa física, automático: empresa individual pelo CPF, sem matriz ou filial.
$contractorD = cpf('482193057');
$response = $platform->call('POST', '/saas/contas', [
    'name' => 'Diana Ferraz',
    'person_type' => 'individual',
    'document' => $contractorD,
    'details' => ['phone' => '11934540000'],
    'organization_setup' => 'automatic',
    'administrator' => $administrator('diana@pf.e10.test'),
]);
status($j, 'Conta D provisionada (pessoa física)', $response, 200);
$accountD = DB::table('clients')->where('document', $contractorD)->first();
$companyD = DB::table('companies')->where('account_id', $accountD->id)->first();
$primaryD = DB::table('establishments')->where('company_id', $companyD->id)->first();
check($j, 'Pessoa física gera empresa individual sem classificação fiscal',
    $companyD->individual_document === $contractorD && $companyD->tax_root === null
    && $primaryD->tax_document === null && ! $primaryD->is_headquarters);

// Classificação explícita: CNPJ do contratante no automático sem Matriz ou Filial é recusado.
$before = $snapshot();
$response = $platform->call('POST', '/saas/contas', [
    'name' => 'Sem Classificação', 'person_type' => 'company', 'document' => cnpj('85600700'),
    'details' => ['phone' => '7134550000'], 'organization_setup' => 'automatic',
    'administrator' => $administrator('semclass@e10.test'),
]);
status($j, 'Automático exige classificação declarada', $response, 422);
check($j, 'Sem classificação: nenhum dado criado', $before === $snapshot());
check($j, 'Não infere Matriz pelo sufixo 0001',
    isset($response['body']['errors']['contractor_classification']));

// Falha transacional: administrador com e-mail existente aborta depois de criar conta e empresa em memória.
$before = $snapshot();
$response = $platform->call('POST', '/saas/contas', [
    'name' => 'Conta Abortada', 'person_type' => 'company', 'document' => cnpj('96700800'),
    'details' => ['phone' => '7134560000'], 'organization_setup' => 'automatic',
    'contractor_classification' => 'headquarters',
    'administrator' => $administrator('ana@gartan.e10.test'),
]);
status($j, 'Administrador com e-mail já usado recusado', $response, 422);
check($j, 'Falha transacional não deixa conta, empresa, unidade, vínculo nem auditoria', $before === $snapshot(),
    [$before, $snapshot()]);
check($j, 'Resposta neutra sobre o e-mail existente',
    ($response['body']['errors']['administrator.email'][0] ?? '') === 'Não foi possível criar o acesso com os dados informados.');

// Primeira empresa manual inválida (raiz incompatível na unidade não existe aqui: documento inválido).
$before = $snapshot();
$response = $platform->call('POST', '/saas/contas', [
    'name' => 'Manual Inválida', 'person_type' => 'company', 'document' => cnpj('17800900'),
    'details' => ['phone' => '7134570000'], 'organization_setup' => 'manual',
    'first_company' => ['name' => 'X', 'person_type' => 'company', 'document' => '12345678000100',
        'primary' => ['name' => 'X', 'classification' => 'headquarters']],
    'administrator' => $administrator('manualinvalida@e10.test'),
]);
status($j, 'Primeira empresa com CNPJ inválido recusada', $response, 422);
check($j, 'Empresa inválida: nada persistido', $before === $snapshot());

// Documento do contratante duplicado.
$response = $platform->call('POST', '/saas/contas', [
    'name' => 'Duplicada', 'person_type' => 'company', 'document' => $contractorA,
    'details' => ['phone' => '7134580000'], 'organization_setup' => 'automatic',
    'contractor_classification' => 'headquarters', 'administrator' => $administrator('dup@e10.test'),
]);
status($j, 'Documento de contratante duplicado recusado pela plataforma', $response, 422);

// Permissões do administrador provisionado: todas account e self, nenhuma platform.
$ana = login('ana@gartan.e10.test');
$dashboard = $ana->call('GET', '/contas/'.$accountA->code);
status($j, 'Administradora da Conta A entra na conta', $dashboard, 200);
$slugs = $dashboard['body']['auth']['permissions'] ?? [];
$catalog = DB::table('permissions')->whereIn('scope', ['account', 'self'])->where('is_system', true)->pluck('slug')->all();
check($j, 'Administrador integral recebe todas as permissões account e self',
    array_diff($catalog, $slugs) === [], array_values(array_diff($catalog, $slugs)));
check($j, 'Administrador integral não recebe platform.*',
    array_filter($slugs, fn ($s) => str_starts_with($s, 'platform.')) === []);
$selection = $ana->call('GET', '/contas');
check($j, 'Vínculo único: seleção leva direto à conta',
    in_array($selection['status'], [200, 302], true)
    && (str_contains((string) ($selection['body']['redirect'] ?? $selection['location'] ?? ''), $accountA->code)
        || count($selection['body']['accounts'] ?? []) === 1), $selection['body']['redirect'] ?? null);

// Auditoria do provisionamento: autoria da plataforma, nós corretos e sem dados sensíveis.
// Só o provisionamento: o login posterior da administradora é evento de identidade dela.
$events = DB::table('audit_records')->where('account_id', $accountA->id)->where('event', '<>', 'login')->orderBy('id')->get();
check($j, 'Login posterior registrado com a própria administradora como autora',
    DB::table('audit_records')->where('account_id', $accountA->id)->where('event', 'login')->value('actor_id')
        == DB::table('users')->where('email', 'ana@gartan.e10.test')->value('id'));
$types = $events->map(fn ($e) => $e->entity_type.':'.$e->event.':'.($e->company_id ?? '-').':'.($e->establishment_id ?? '-'))->all();
check($j, 'Eventos da conta, empresa, unidade, vínculo e atribuição registrados',
    $events->contains(fn ($e) => $e->entity_type === 'account' && $e->company_id === null)
    && $events->contains(fn ($e) => $e->entity_type === 'company' && (int) $e->company_id === $companyA1->id && $e->establishment_id === null)
    && $events->contains(fn ($e) => $e->entity_type === 'establishment' && (int) $e->establishment_id === $primaryA1->id)
    && $events->contains(fn ($e) => $e->entity_type === 'account-membership')
    && $events->contains(fn ($e) => $e->entity_type === 'role-assignment'),
    $types);
check($j, 'Autor real é o administrador SaaS em todos os eventos do provisionamento',
    $events->every(fn ($e) => (int) $e->actor_id === $platformId && $e->represented_user_id === null),
    $events->pluck('actor_id')->unique()->values());
check($j, 'Origem Plataforma SaaS nos eventos do provisionamento',
    $events->every(fn ($e) => $e->context === 'platform'), $events->pluck('context')->unique()->values());
$serialized = $events->pluck('changes')->implode(' ');
check($j, 'Histórico sem senha, hash ou documento do contratante em claro',
    ! str_contains($serialized, E10_PASSWORD) && ! str_contains($serialized, '$2y$') && ! str_contains($serialized, $contractorA));

remember('accounts', [
    'A' => ['id' => $accountA->id, 'code' => $accountA->code, 'company' => $companyA1->id, 'company_code' => $companyA1->code, 'primary' => $primaryA1->id, 'primary_code' => $primaryA1->code, 'root' => $companyA1->tax_root],
    'B' => ['id' => $accountB->id, 'code' => $accountB->code, 'company' => $companyB->id, 'company_code' => $companyB->code, 'primary' => $primaryB->id, 'primary_code' => $primaryB->code, 'root' => $companyB->tax_root],
    'C' => ['id' => $accountC->id, 'code' => $accountC->code, 'company' => $establishmentsC[0]->company_id, 'primary' => $establishmentsC[0]->id, 'primary_code' => $establishmentsC[0]->code],
    'D' => ['id' => $accountD->id, 'code' => $accountD->code, 'company' => $companyD->id],
]);

finish('01-provisionamento');

function login_attempt(string $email): int
{
    $browser = new Browser('tentativa');
    $browser->call('GET', '/login');

    return $browser->call('POST', '/login', ['email' => $email, 'password' => E10_PASSWORD])['status'];
}
