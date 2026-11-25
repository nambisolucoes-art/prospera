// Departamentos e funções das empresas do acesso (S03): as duas telas usam o mesmo contrato de linha e de configuração.
export type CatalogRow = {
  id: number;
  name: string;
  active: boolean;
  /** Empresa do registro: nome de exibição e código. */
  company: string;
  company_code: string;
  updated_at: string | null;
};

export type CompanyOption = { code: string; name: string };

export type CatalogPreviewRow = {
  id: number;
  name: string;
  active: boolean;
  company_name: string;
  created_at: string | null;
  updated_at: string | null;
};

export type CatalogConfig = {
  /** Título da tela: Departamentos ou Funções. */
  title: string;
  /** Nome no singular, em minúsculas, para as frases: departamento, função. */
  noun: string;
  /** Concordância: o departamento (masculino) ou a função (feminino). */
  masculine: boolean;
  /** Segmento da URL da conta: departamentos ou funcoes. */
  segment: string;
  /** Módulo das permissões: departments ou job-titles. */
  module: string;
  /** Prefixo dos alvos do tour e identificador da tela. */
  tour: string;
};

export const departmentsConfig: CatalogConfig = {
  title: 'Departamentos',
  noun: 'departamento',
  masculine: true,
  segment: 'departamentos',
  module: 'departments',
  tour: 'departments',
};

export const jobTitlesConfig: CatalogConfig = {
  title: 'Funções',
  noun: 'função',
  masculine: false,
  segment: 'funcoes',
  module: 'job-titles',
  tour: 'job-titles',
};

/** Artigo definido do recurso: o ou a. */
export const article = (config: CatalogConfig) => (config.masculine ? 'o' : 'a');

/** Evento que o tour da tela usa para fechar somente a janela de cadastro que ele próprio abriu. */
export const catalogEditorCloseDemo = 'catalog-editor:close-demo';
