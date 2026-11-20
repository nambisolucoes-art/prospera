import type { Address } from './ClientForm.types';

export type Classification = 'headquarters' | 'branch' | 'unregistered';

export type CompanyLabel = {
  id: number;
  name: string;
  trade_name: string | null;
  active: boolean;
  person_type: 'company' | 'individual' | null;
  document_label: string | null;
};

export type CompanyRow = CompanyLabel & {
  code: string;
  document: string | null;
  establishments_count?: number;
  tax_regime?: string | null;
  main_cnae?: string | null;
};

export type EstablishmentRow = {
  id: number;
  company_id: number;
  code: string;
  name: string;
  trade_name: string | null;
  classification: Classification;
  is_primary: boolean;
  active: boolean;
  tax_document: string | null;
  tax_document_label: string | null;
  city: string | null;
  state: string | null;
  company: CompanyLabel;
};

export type EstablishmentDetails = EstablishmentRow & {
  state_registration: string | null;
  municipal_registration: string | null;
  address: Address | null;
  phone: string | null;
  whatsapp: string | null;
  email: string | null;
  additional_phones: string[] | null;
  additional_emails: string[] | null;
};

export const classificationLabels: Record<Classification, string> = {
  headquarters: 'Matriz',
  branch: 'Filial',
  unregistered: 'Sem CNPJ próprio',
};

export function companyName(company: CompanyLabel): string {
  return company.trade_name || company.name;
}
