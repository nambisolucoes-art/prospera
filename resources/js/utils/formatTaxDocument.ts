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
