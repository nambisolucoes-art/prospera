export type ContextEstablishment = {
  code: string;
  name: string;
  primary: boolean;
  classification: 'headquarters' | 'branch' | 'unregistered';
  company_code: string;
  company_name: string;
};

// Contexto organizacional da página (E06). Só orienta a navegação; o servidor autoriza cada requisição.
export type OrganizationContextProps = {
  account_id: number | null;
  mode: 'tenant' | 'company' | 'establishment' | 'consolidated';
  company: { code: string; name: string } | null;
  establishment: ContextEstablishment | null;
  consolidated: boolean;
  selected_codes: string[];
  establishments: ContextEstablishment[];
};

export type SessionProps = {
  [key: string]: unknown;
  name: string;
  organization_context?: OrganizationContextProps | null;
  auth: {
    permissions: string[];
    inspection_mode: 'read-only' | 'edit' | null;
    user: { id: number; name: string; email: string; email_verified_at: string | null; photo_url?: string | null; is_saas_admin: boolean } | null;
  };
};
