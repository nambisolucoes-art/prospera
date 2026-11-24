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

// Conta da URL (E09), validada pelo servidor nesta requisição. Não é estado global: cada aba tem a sua.
export type AccountContextProps = {
  code: string;
  name: string;
  base: string;
};

export type SessionProps = {
  [key: string]: unknown;
  name: string;
  account_context?: AccountContextProps | null;
  organization_context?: OrganizationContextProps | null;
  auth: {
    permissions: string[];
    inspection_mode: 'read-only' | 'edit' | null;
    // Quantidade de contas que a identidade pode abrir; a troca de conta aparece a partir de duas.
    accounts?: number;
    user: { id: number; name: string; email: string; email_verified_at: string | null; photo_url?: string | null; is_saas_admin: boolean } | null;
  };
};
