export type AuditLevel = 'platform' | 'tenant' | 'company' | 'establishment';

export type AuditPlaceSource = {
  level: AuditLevel;
  account: string | null;
  company: string | null;
  establishment: string | null;
};

/** Organização afetada por um evento: a conta, a empresa ou a unidade com a empresa responsável abaixo. */
export function auditPlace(row: AuditPlaceSource): { main: string; detail?: string } {
  if (row.level === 'establishment') {
    return { main: row.establishment ?? 'Não informado', detail: row.company ?? undefined };
  }

  if (row.level === 'company') {
    return { main: row.company ?? 'Não informado', detail: row.account ?? 'Empresa' };
  }

  if (row.level === 'tenant') {
    return row.account ? { main: row.account, detail: 'Conta' } : { main: 'Conta' };
  }

  return { main: 'Plataforma SaaS' };
}
