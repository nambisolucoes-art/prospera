import assert from 'node:assert/strict';
import { test } from 'node:test';
import { formatPersonDocumentInput } from '../../resources/js/utils/formatTaxDocument.ts';

test('CPF e CNPJ mantêm a máscara do tipo selecionado durante a digitação', () => {
  assert.equal(formatPersonDocumentInput('5299', 'individual'), '529.9');
  assert.equal(formatPersonDocumentInput('52998224725', 'individual'), '529.982.247-25');
  assert.equal(formatPersonDocumentInput('529.982.247-25', 'company'), '52.998.224/725');
  assert.equal(formatPersonDocumentInput('11222333000181', 'company'), '11.222.333/0001-81');
  assert.equal(formatPersonDocumentInput('12abc34501de35', 'company'), '12.ABC.345/01DE-35');
});

test('um CNPJ completo no campo CPF não é truncado para outro documento', () => {
  assert.equal(formatPersonDocumentInput('11.222.333/0001-81', 'individual'), '11222333000181');
  assert.equal(formatPersonDocumentInput('12.ABC.345/01DE-35', 'individual'), '12ABC34501DE35');
});
