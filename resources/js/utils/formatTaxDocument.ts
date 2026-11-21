export type PersonDocumentType = 'individual' | 'company';

export function normalizeTaxDocument(value: string): string {
  return value.replace(/[.\/\s()-]/g, '').toUpperCase();
}

export function formatPersonDocumentInput(value: string, type: PersonDocumentType): string {
  const normalized = normalizeTaxDocument(value);
  const validCharacters = type === 'individual' ? /^\d{0,11}$/ : /^[A-Z0-9]{0,12}\d{0,2}$/;
  if (!validCharacters.test(normalized)) return normalized;

  const mask = type === 'individual' ? '000.000.000-00' : '00.000.000/0000-00';
  let index = 0;
  let formatted = '';

  for (const character of mask) {
    if (index === normalized.length) break;
    formatted += character === '0' ? normalized[index++] : character;
  }

  return formatted;
}

export function formatTaxDocument(value: string | null, personType: string): string {
  if (!value) return 'Não informado';
  const normalized = value.replace(/[.\/\s-]/g, '').toUpperCase();
  if (personType === 'individual' && /^\d{11}$/.test(normalized)) {
    return normalized.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, '$1.$2.$3-$4');
  }
  if (personType === 'company' && /^[A-Z0-9]{12}\d{2}$/.test(normalized)) {
    return normalized.replace(/^(.{2})(.{3})(.{3})(.{4})(.{2})$/, '$1.$2.$3/$4-$5');
  }
  // Raiz do CNPJ, usada pelas empresas cujos estabelecimentos têm CNPJ próprio.
  if (personType === 'company' && /^[A-Z0-9]{8}$/.test(normalized)) {
    return normalized.replace(/^(.{2})(.{3})(.{3})$/, '$1.$2.$3');
  }
  return value;
}
