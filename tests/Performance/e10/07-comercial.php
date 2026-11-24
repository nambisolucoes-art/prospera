<?php

// Jornada 7: Clientes e Fornecedores sobre o cadastro central interno de Pessoas (E07).
use Illuminate\Support\Facades\DB;

require __DIR__.'/http.php';

$j = 'J7 comercial';
$accounts = state()['accounts'];
$A = $accounts['A'];
$B = $accounts['B'];
$a = '/contas/'.$A['code'];
$b = '/contas/'.$B['code'];
$a1 = $a.'/empresa/'.$A['company_code'];
$a2 = $a.'/empresa/'.$A['company2_code'];
$ana = login('ana@gartan.e10.test');
$bruno = login('bruno@outra.e10.test');
$carlos = login('carlos@gartan.e10.test');
$consult = login('consulta@gartan.e10.test');
$document = cpf('512834067');
$supplierDocument = cnpj('30405060', '0001');

// Etiquetas comerciais da conta (somente Clientes e Fornecedores) e recusa de etiqueta de Pessoas.
foreach ([['Cliente VIP', 'customers'], ['Atacado', 'customers'], ['Homologado', 'suppliers']] as [$name, $module]) {
    status($j, 'Etiqueta '.$name, $ana->call('POST', $a.'/etiquetas', ['name' => $name, 'module' => $module, 'color' => '#2266aa']), 200);
}
status($j, 'Nova etiqueta de Pessoas recusada', $ana->call('POST', $a.'/etiquetas', ['name' => 'Pessoa', 'module' => 'people', 'color' => '#2266aa']), 422);
$tag = fn (string $name) => (int) DB::table('tags')->where('account_id', $A['id'])->where('name', $name)->value('id');
$systemTags = DB::table('tags')->where('account_id', $A['id'])->whereNotNull('system_key')->get();
check($j, 'Etiquetas de sistema presentes na conta', $systemTags->pluck('system_key')->sort()->values()->all() === ['customer', 'supplier', 'user'], $systemTags->pluck('system_key'));
status($j, 'Etiqueta de sistema não é excluída', $ana->call('DELETE', $a.'/etiquetas/'.$systemTags->firstWhere('system_key', 'customer')->id), 403);

// 1. Cliente pessoa física na A1, com contatos e endereço centrais.
$response = $carlos->call('POST', $a1.'/clientes', [
    'person_type' => 'individual',
    'name' => 'Marta Queiroz',
    'document' => $document,
    'email' => 'marta@cliente.e10.test',
    'phone' => '(77) 3451-1000',
    'address' => ['postal_code' => '46430-000', 'street' => 'Rua Central', 'number' => '10', 'city' => 'Guanambi', 'state' => 'BA'],
    'tag_ids' => [$tag('Cliente VIP')],
]);
status($j, 'Cliente criado na A1 pelo operador de vendas', $response, [200, 204]);
$person = DB::table('people')->where('account_id', $A['id'])->where('document', $document)->first();
check($j, 'Pessoa central criada uma vez na Conta A', $person !== null && DB::table('people')->where('document', $document)->count() === 1);

// 2 e 3. A mesma Pessoa como Fornecedor (busca segura por POST, sem documento na URL).
$found = $ana->call('POST', $a1.'/fornecedores/pessoa', ['person_type' => 'individual', 'documento' => $document]);
status($j, 'Busca por CPF por POST', $found, 200);
check($j, 'Busca retorna a Pessoa central e informa o outro papel', ($found['body']['person']['id'] ?? null) === $person->id
    && ($found['body']['person']['other_role'] ?? null) === 'Cliente', $found['body']['person'] ?? null);
status($j, 'Fornecedor criado com a mesma Pessoa', $ana->call('POST', $a1.'/fornecedores', ['person_id' => $person->id, 'tag_ids' => [$tag('Homologado')]]), [200, 204]);
check($j, 'Uma Pessoa, um Cliente e um Fornecedor na A1', DB::table('people')->where('document', $document)->count() === 1
    && DB::table('customers')->where('person_id', $person->id)->count() === 1 && DB::table('suppliers')->where('person_id', $person->id)->count() === 1);

// 4. A mesma Pessoa vinculada à A2 como Cliente.
$found = $ana->call('POST', $a2.'/clientes/pessoa', ['person_type' => 'individual', 'documento' => $document]);
check($j, 'Busca na A2 identifica a Pessoa sem relação na A2', ($found['body']['person']['id'] ?? null) === $person->id, $found['body']['person'] ?? null);
status($j, 'Cliente na A2 com a mesma Pessoa', $ana->call('POST', $a2.'/clientes', ['person_id' => $person->id, 'tag_ids' => [$tag('Atacado')]]), [200, 204]);
$customerA1 = DB::table('customers')->where('person_id', $person->id)->where('company_id', $A['company'])->first();
$customerA2 = DB::table('customers')->where('person_id', $person->id)->where('company_id', $A['company2'])->first();
check($j, 'Relações independentes por empresa, uma Pessoa', $customerA1 && $customerA2 && $customerA1->id !== $customerA2->id);

// Etiquetas da relação: cada empresa guarda a sua seleção, sem passar para a Pessoa.
$a1Show = $ana->call('GET', $a1.'/clientes/'.$customerA1->id.'/visualizar');
$a2Show = $ana->call('GET', $a2.'/clientes/'.$customerA2->id.'/visualizar');
$names = fn (array $r) => collect($r['body']['relation']['tags'] ?? $r['body']['tags'] ?? [])->pluck('name')->sort()->values()->all();
check($j, 'Etiqueta da A1 não aparece na relação da A2', in_array('Cliente VIP', $names($a1Show), true) && ! in_array('Cliente VIP', $names($a2Show), true),
    [$names($a1Show), $names($a2Show)]);

// 5 e 6. Dados centrais compartilhados; contatos editáveis por quem cobre uma empresa; identificação protegida.
status($j, 'Carlos altera o telefone central pela A1', $carlos->call('PATCH', $a1.'/clientes/'.$customerA1->id, ['phone' => '77999990000']), [200, 204]);
check($j, 'Telefone central alterado vale para a A2', DB::table('people')->where('id', $person->id)->value('phone') === '77999990000');
$before = DB::table('people')->where('id', $person->id)->first();
status($j, 'Carlos não altera o nome (relação na A2 não coberta)', $carlos->call('PATCH', $a1.'/clientes/'.$customerA1->id, ['name' => 'Marta Alterada']), 403);
status($j, 'Carlos não altera o documento por envio forjado', $carlos->call('PATCH', $a1.'/clientes/'.$customerA1->id, ['document' => cpf('999888777')]), [403, 422]);
check($j, 'Identificação preservada após as recusas', DB::table('people')->where('id', $person->id)->first() == $before);
status($j, 'Ana (conta inteira) altera a identificação', $ana->call('PATCH', $a1.'/clientes/'.$customerA1->id, ['name' => 'Marta Queiroz Lima']), [200, 204]);
$event = DB::table('audit_records')->where('entity_type', 'person')->where('entity_id', $person->id)->orderByDesc('id')->first();
check($j, 'Alteração central auditada como evento da conta, sem empresa', $event && $event->company_id === null && $event->establishment_id === null);
check($j, 'Relações não foram tocadas pela alteração central',
    DB::table('customers')->where('id', $customerA2->id)->value('updated_at') === $customerA2->updated_at);

// 8. Inativação e reativação da relação da A1, sem afetar a A2 nem o Fornecedor.
status($j, 'Inativar cliente na A1', $carlos->call('PATCH', $a1.'/clientes/'.$customerA1->id.'/inativar'), [200, 204]);
check($j, 'Somente a relação da A1 ficou inativa', ! DB::table('customers')->where('id', $customerA1->id)->value('active')
    && DB::table('customers')->where('id', $customerA2->id)->value('active') && DB::table('suppliers')->where('person_id', $person->id)->value('active'));
$deactivation = DB::table('audit_records')->where('entity_type', 'customer')->where('entity_id', $customerA1->id)->orderByDesc('id')->first();
check($j, 'Inativação auditada com company_id da relação', $deactivation && (int) $deactivation->company_id === $A['company'] && $deactivation->establishment_id === null);
status($j, 'Reativar cliente na A1', $carlos->call('PATCH', $a1.'/clientes/'.$customerA1->id.'/ativar'), [200, 204]);

// 10. Pesquisa por CPF na listagem: só a busca, e a URL do documento nunca vai ao log.
$listing = $carlos->call('GET', $a.'/estabelecimento/'.$A['primary_code'].'/clientes?search=Marta');
check($j, 'Listagem pesquisável da A1 mostra só a relação da A1 ao operador da A1',
    collect($listing['body']['relations']['data'] ?? [])->pluck('company.code')->unique()->values()->all() === [$A['company_code']],
    collect($listing['body']['relations']['data'] ?? [])->pluck('company.code'));

// 11. Sem permissão.
status($j, 'Consulta não cadastra cliente', $consult->call('POST', $a1.'/clientes', ['person_type' => 'individual', 'name' => 'X', 'document' => cpf('700800900')]), 403);
status($j, 'Consulta não altera cliente', $consult->call('PATCH', $a1.'/clientes/'.$customerA1->id, ['phone' => '77000000000']), 403);
status($j, 'Consulta não busca documento para cadastro', $consult->call('POST', $a1.'/clientes/pessoa', ['person_type' => 'individual', 'documento' => $document]), 403);
status($j, 'Carlos não lê relação da A2', $carlos->call('GET', $a2.'/clientes/'.$customerA2->id.'/visualizar'), [403, 404]);

// 12. Pessoa de outro tenant: a B não descobre nem reutiliza a Pessoa da A.
$foundB = $bruno->call('POST', $b.'/empresa/'.$B['company_code'].'/clientes/pessoa', ['person_type' => 'individual', 'documento' => $document]);
check($j, 'Busca na B não revela a Pessoa da A', $foundB['status'] === 200 && array_key_exists('person', (array) $foundB['body'])
    && $foundB['body']['person'] === null, $foundB['body']);
status($j, 'B não vincula a Pessoa da A pelo ID', $bruno->call('POST', $b.'/empresa/'.$B['company_code'].'/clientes', ['person_id' => $person->id]), [403, 404, 422]);
status($j, 'B cria a própria Pessoa com o mesmo CPF', $bruno->call('POST', $b.'/empresa/'.$B['company_code'].'/clientes', [
    'person_type' => 'individual', 'name' => 'Marta na B', 'document' => $document, 'phone' => '7130000000',
]), [200, 204]);
$personB = DB::table('people')->where('account_id', $B['id'])->where('document', $document)->first();
check($j, 'Pessoas distintas por conta com o mesmo CPF', $personB && $personB->id !== $person->id && $personB->name === 'Marta na B'
    && DB::table('people')->where('id', $person->id)->value('name') === 'Marta Queiroz Lima');
status($j, 'A não lê o cliente da B pelo ID', $ana->call('GET', $a1.'/clientes/'.DB::table('customers')->where('person_id', $personB->id)->value('id').'/visualizar'), 404);

// Pessoa jurídica com CNPJ, fornecedor da A2 pelo operador logístico (cargo restrito à A2).
$logistic = login('logistica@gartan.e10.test');
status($j, 'Fornecedor PJ na A2 pelo cargo restrito', $logistic->call('POST', $a2.'/fornecedores', [
    'person_type' => 'company', 'name' => 'Transportes Serra Ltda', 'trade_name' => 'Serra Transportes',
    'document' => $supplierDocument, 'phone' => '7734001122',
]), [200, 204]);
status($j, 'Cargo restrito à A2 não cria fornecedor na A1', $logistic->call('POST', $a1.'/fornecedores', [
    'person_type' => 'company', 'name' => 'Indevida', 'document' => cnpj('30405061', '0001'),
]), [403, 404]);

// Pessoas continua estrutura interna: sem tela, rota ou cadastro avulso.
status($j, 'Sem tela de Pessoas', $ana->call('GET', $a.'/pessoas'), 404);
status($j, 'Sem cadastro avulso de Pessoas', $ana->call('POST', $a.'/pessoas', ['name' => 'X']), [404, 405]);

remember('commercial', ['person' => $person->id, 'customerA1' => $customerA1->id, 'customerA2' => $customerA2->id, 'document' => $document, 'personB' => $personB->id]);
finish('07-comercial');
