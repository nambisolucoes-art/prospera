// Contrato de navegação contextual (E06). Execute com: npm run test:frontend
import assert from 'node:assert/strict';
import { test } from 'node:test';

Object.assign(globalThis, { window: { location: { origin: 'http://prospera.test' } } });

const {
  contextualUrl,
  pageWorkspace,
  workspaceEntryUrl,
  workspaceModuleUrl,
  workspaceStorageKey,
  workspaceUrl,
} = await import('../../resources/js/utils/organizationContext.ts');

const unit = '/contas/primeira/estabelecimento/roupas-guanambi';
const consolidated = '/contas/primeira/estabelecimento/todos?unidades=roupas-guanambi,express-janauba';

function context(mode: 'tenant' | 'establishment' | 'consolidated', selected: string[]) {
  return {
    account_id: 1,
    mode,
    company: null,
    establishment: null,
    consolidated: mode !== 'tenant',
    selected_codes: selected,
    establishments: [],
  };
}

test('~/ resolve no contexto da página que declarou o endereço', () => {
  assert.equal(contextualUrl('~/estoque', unit), `${unit}/estoque`);
  assert.equal(contextualUrl('~/estoque/7', `${unit}/estoque?page=2`), `${unit}/estoque/7`);
  assert.equal(contextualUrl('~/produtos', '/contas/primeira/empresa/gartan/clientes'), '/contas/primeira/empresa/gartan/produtos');
});

test('endereços comuns não mudam', () => {
  for (const address of ['/contas/primeira/pessoas', '/contas/primeira/pessoas?search=ana', 'https://prospera.test/x', '#topo']) {
    assert.equal(contextualUrl(address, unit), address);
  }
});

test('a resolução é idempotente, para navigate aceitar endereços já resolvidos por ApiLink e useApiForm', () => {
  const resolved = contextualUrl('~/estoque', consolidated);
  assert.equal(contextualUrl(resolved, consolidated), resolved);
  assert.equal(contextualUrl(resolved, unit), resolved);
});

test('o consolidado preserva a seleção, inclusive com filtros no endereço', () => {
  assert.equal(
    contextualUrl('~/estoque', consolidated),
    '/contas/primeira/estabelecimento/todos/estoque?unidades=roupas-guanambi%2Cexpress-janauba',
  );
  const filtered = new URL(contextualUrl('~/estoque?search=camisa', consolidated), 'http://prospera.test');
  assert.equal(filtered.pathname, '/contas/primeira/estabelecimento/todos/estoque');
  assert.equal(filtered.searchParams.get('search'), 'camisa');
  assert.equal(filtered.searchParams.get('unidades'), 'roupas-guanambi,express-janauba');
});

test('a unidade aberta do conjunto mantém o retorno', () => {
  const page = `${unit}?voltar-unidades=roupas-guanambi,express-janauba`;
  const target = new URL(contextualUrl('~/estoque', page), 'http://prospera.test');
  assert.equal(target.pathname, `${unit}/estoque`);
  assert.equal(target.searchParams.get('voltar-unidades'), 'roupas-guanambi,express-janauba');
  assert.equal(target.searchParams.get('unidades'), null);
});

test('~/ fora de um contexto é recusado, em vez de cair numa rota da conta', () => {
  assert.throws(() => contextualUrl('~/estoque', '/contas/primeira/pessoas'), /empresa ou de um estabelecimento/);
});

test('a entrada da área de trabalho leva a seleção da aba e o destino ao servidor', () => {
  assert.equal(workspaceEntryUrl(['a1']), '@/estabelecimento?unidades=a1');
  assert.equal(workspaceEntryUrl(['a1', 'b2']), '@/estabelecimento?unidades=a1,b2');
  assert.equal(workspaceEntryUrl(['a1', 'b2'], 'estoque/7'), '@/estabelecimento?unidades=a1,b2&para=estoque%2F7');
});

test('a troca de seleção mantém só módulos compatíveis', () => {
  assert.equal(workspaceUrl(['a1'], `${unit}/estoque?page=3`), '@/estabelecimento/a1/estoque');
  assert.equal(workspaceUrl(['a1', 'b2'], `${unit}/estoque`), '@/estabelecimento/todos?unidades=a1,b2');
  assert.equal(
    workspaceUrl(['a1', 'b2'], `${unit}/estoque`, true),
    '@/estabelecimento/todos/estoque?unidades=a1,b2',
  );
});

test('nas telas com contexto, a seleção do seletor vem da URL', () => {
  assert.deepEqual(pageWorkspace(context('consolidated', ['a1', 'b2']), consolidated), ['a1', 'b2']);
  assert.deepEqual(pageWorkspace(context('establishment', ['a1']), `${unit}?voltar-unidades=a1,b2`), ['a1', 'b2']);
  assert.deepEqual(pageWorkspace(context('establishment', ['a1']), `${unit}?voltar-unidades=b2,c3`), ['a1']);
  assert.deepEqual(pageWorkspace(context('establishment', ['a1']), unit), ['a1']);
  assert.equal(pageWorkspace(context('tenant', ['a1', 'b2']), '/contas/primeira/pessoas'), null);
});

test('menu de Clientes e Fornecedores usa a seleção da URL ou da aba e passa pela entrada (E07)', () => {
  const units = ['roupas-guanambi', 'roupas-janauba', 'express-jequie'].map((code) => ({ code }));
  const account = { ...context('tenant', ['roupas-guanambi']), establishments: units };
  const workspace = { ...context('consolidated', ['roupas-janauba', 'express-jequie']), establishments: units };

  // Tela da conta: vale a seleção desta aba, só com unidades ainda disponíveis.
  assert.equal(
    workspaceModuleUrl(account as never, '/contas/primeira/pessoas', ['roupas-guanambi', 'revogada'], 'clientes'),
    '@/estabelecimento?unidades=roupas-guanambi&para=clientes',
  );
  // Tela com contexto: a URL manda, não a aba.
  assert.equal(
    workspaceModuleUrl(workspace as never, consolidated, ['roupas-guanambi'], 'fornecedores'),
    '@/estabelecimento?unidades=roupas-janauba,express-jequie&para=fornecedores',
  );
  // Sem seleção válida (ou com uma unidade só, sem seletor), a entrada usa o padrão pessoal.
  assert.equal(workspaceModuleUrl(null, '/contas/primeira', [], 'clientes'), '@/estabelecimento?para=clientes');
  assert.equal(workspaceStorageKey(3, 9, null), 'prospera.workspace.3.9.own');
  assert.equal(workspaceStorageKey(3, 9, 'read-only'), 'prospera.workspace.3.9.read-only');
});


test('cada aba resolve sua própria conta e recusa contexto ausente', () => {
  assert.equal(contextualUrl('@/usuarios', '/contas/primeira'), '/contas/primeira/usuarios');
  assert.equal(contextualUrl('@/usuarios', '/contas/segunda'), '/contas/segunda/usuarios');
  assert.throws(() => contextualUrl('@/usuarios', '/contas'), /conta na URL/);
  assert.throws(() => contextualUrl('~/clientes', '/conta/estabelecimento/unidade'), /empresa ou/);
});
