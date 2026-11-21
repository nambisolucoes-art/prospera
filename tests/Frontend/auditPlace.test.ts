// Coluna Onde da auditoria (E08). Execute com: npm run test:frontend
import assert from 'node:assert/strict';
import { test } from 'node:test';

const { auditPlace } = await import('../../resources/js/utils/auditPlace.ts');

const base = { account: null, company: null, establishment: null };

test('evento de unidade mostra a unidade e a empresa responsável', () => {
  assert.deepEqual(
    auditPlace({ ...base, level: 'establishment', company: 'Gartan Roupas', establishment: 'Janaúba' }),
    { main: 'Janaúba', detail: 'Gartan Roupas' },
  );
});

test('evento de empresa não inventa unidade', () => {
  assert.deepEqual(auditPlace({ ...base, level: 'company', company: 'Gartan Express' }), {
    main: 'Gartan Express',
    detail: 'Empresa',
  });
});

test('evento da conta não recebe empresa nem unidade', () => {
  assert.deepEqual(auditPlace({ ...base, level: 'tenant' }), { main: 'Conta' });
  assert.deepEqual(auditPlace({ ...base, level: 'tenant', account: 'Grupo Gartan' }), {
    main: 'Grupo Gartan',
    detail: 'Conta',
  });
});

test('nomes ausentes aparecem como Não informado e a plataforma tem rótulo próprio', () => {
  assert.equal(auditPlace({ ...base, level: 'establishment' }).main, 'Não informado');
  assert.deepEqual(auditPlace({ ...base, level: 'platform' }), { main: 'Plataforma SaaS' });
});
