import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
  formatPersonDocumentInput,
  formatTaxDocument,
  isValidCnpj,
  isValidCpf,
  normalizeTaxDocument,
  taxDocumentProblem,
} from '../../resources/js/utils/formatTaxDocument.ts';

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

test('a máscara do CNPJ preserva letras e acompanha a digitação em todas as posições', () => {
  assert.equal(formatPersonDocumentInput('12abc', 'company'), '12.ABC');
  assert.equal(formatPersonDocumentInput('12ABC34501DE3A', 'company'), '12.ABC.345/01DE-3A');
  assert.equal(normalizeTaxDocument(' 12.abc.345/01de-35 '), '12ABC34501DE35');
  assert.equal(formatTaxDocument('12ABC34501DE35', 'company'), '12.ABC.345/01DE-35');
  assert.equal(formatTaxDocument('12abc345', 'company'), '12.ABC.345');
});

test('CNPJ alfanumérico valida os dígitos verificadores, que são sempre numéricos', () => {
  assert.equal(isValidCnpj('12ABC34501DE35'), true);
  assert.equal(isValidCnpj('11222333000181'), true);
  assert.equal(isValidCnpj('12ABC34501DE36'), false);
  assert.equal(isValidCnpj('12ABC34501DEA5'), false);
  assert.equal(isValidCnpj('AAAAAAAAAAAA00'), false);
  assert.equal(isValidCpf('52998224725'), true);
  assert.equal(isValidCpf('52998224724'), false);
  assert.equal(isValidCpf('11111111111'), false);
});

test('o aviso de digitação aponta o problema sem bloquear raiz nem documento incompleto', () => {
  assert.equal(taxDocumentProblem('12.ABC.345', 'company'), undefined);
  assert.equal(taxDocumentProblem('12.ABC.345/01DE-3', 'company'), undefined);
  assert.equal(taxDocumentProblem('12.ABC.345/01DE-35', 'company'), undefined);
  assert.match(taxDocumentProblem('12.ABC.345/01DE-3A', 'company') ?? '', /devem ser números/);
  assert.match(taxDocumentProblem('12.ABC.345/01DE-36', 'company') ?? '', /não conferem/);
  assert.match(taxDocumentProblem('12.ÁBC', 'company') ?? '', /letras sem acento/);
  assert.equal(taxDocumentProblem('529.982.247-25', 'individual'), undefined);
  assert.match(taxDocumentProblem('529.982.247-24', 'individual') ?? '', /não conferem/);
  assert.match(taxDocumentProblem('12ABC34501DE35', 'individual') ?? '', /somente números/);
});
