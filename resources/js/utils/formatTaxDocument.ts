export type PersonDocumentType = 'individual' | 'company';

// Pesos da Receita Federal; no CNPJ alfanumérico, cada caractere vale o código ASCII menos 48.
const CNPJ_WEIGHTS = [
  [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
  [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
];

export function normalizeTaxDocument(value: string): string {
  return value.replace(/[.\/\s()-]/g, '').toUpperCase();
}

export function isValidCpf(normalized: string): boolean {
  if (!/^\d{11}$/.test(normalized) || /^(\d)\1{10}$/.test(normalized)) return false;

  for (let length = 9; length < 11; length++) {
    let sum = 0;

    for (let index = 0; index < length; index++) {
      sum += Number(normalized[index]) * (length + 1 - index);
    }

    const digit = (10 * sum) % 11;

    if (Number(normalized[length]) !== (digit === 10 ? 0 : digit)) return false;
  }

  return true;
}

// Os 12 primeiros caracteres podem ter letras; os dois dígitos verificadores são sempre numéricos.
export function isValidCnpj(normalized: string): boolean {
  if (!/^[A-Z0-9]{12}\d{2}$/.test(normalized) || /^(.)\1{13}$/.test(normalized)) return false;

  let base = normalized.slice(0, 12);

  for (const weights of CNPJ_WEIGHTS) {
    const sum = weights.reduce((total, weight, index) => total + (base.charCodeAt(index) - 48) * weight, 0);
    const rest = sum % 11;
    base += String(rest < 2 ? 0 : 11 - rest);
  }

  return base === normalized;
}

/**
 * Problema visível durante a digitação. A validação final continua no servidor.
 */
export function taxDocumentProblem(value: string, type: PersonDocumentType): string | undefined {
  const normalized = normalizeTaxDocument(value);

  if (type === 'individual') {
    if (/\D/.test(normalized)) return 'O CPF tem somente números.';
    if (normalized.length > 11) return 'O CPF tem 11 dígitos.';
    if (normalized.length === 11 && !isValidCpf(normalized)) return 'Confira o CPF: os dígitos verificadores não conferem.';
    return undefined;
  }

  if (/[^A-Z0-9]/.test(normalized)) return 'O CNPJ aceita somente letras sem acento e números.';
  if (normalized.length > 14) return 'O CNPJ tem 14 posições.';
  if (/\D/.test(normalized.slice(12))) return 'Os dois últimos caracteres do CNPJ são dígitos verificadores e devem ser números.';
  if (normalized.length === 14 && !isValidCnpj(normalized)) return 'Confira o CNPJ: os dígitos verificadores não conferem.';
  return undefined;
}

export function formatPersonDocumentInput(value: string, type: PersonDocumentType): string {
  const normalized = normalizeTaxDocument(value);
  // A máscara acompanha letras em qualquer posição do CNPJ; o aviso de validação aponta o caractere inválido.
  const validCharacters = type === 'individual' ? /^\d{0,11}$/ : /^[A-Z0-9]{0,14}$/;
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
  const normalized = normalizeTaxDocument(value);
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
