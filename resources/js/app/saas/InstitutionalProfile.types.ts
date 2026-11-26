export type Resolved = {
  value: Record<string, unknown> | null;
  source: 'company' | 'establishment' | 'platform';
  mode: 'override' | 'inherit' | 'disabled' | 'default';
  inherited_from: 'company' | 'platform' | null;
};

export type ProfileSection = {
  key: string;
  label: string;
  kind: 'editorial' | 'operational';
  available: boolean;
  unavailable_reason: string | null;
  can_disable: boolean;
  establishment_only: boolean;
  draft: Resolved;
  published: Resolved;
  pending: boolean;
  empty_value: Record<string, unknown>;
};

export type ProfileOwner = {
  type: 'company' | 'establishment';
  code: string;
  name: string;
  active: boolean;
  company_code: string;
  company_name: string;
  show_public_address: boolean;
  has_address: boolean;
};

export type ProfileFont = { key: string; label: string };
export type ProfileNetwork = { key: string; label: string };
export type ProfileImpact = { changed: string[]; inheriting: number; overriding: number };

// Base das rotas do dono, relativa à conta: `@/empresa/{codigo}` ou `@/estabelecimento/{codigo}`.
export function ownerBase(owner: ProfileOwner) {
  return `@/${owner.type === 'company' ? 'empresa' : 'estabelecimento'}/${owner.code}/institucional`;
}

// De onde vem o valor mostrado: própria, herdada da empresa ou padrão da plataforma.
export function originText(section: ProfileSection, owner: ProfileOwner) {
  const { draft } = section;
  if (draft.mode === 'disabled') return 'Esta seção está desativada neste estabelecimento.';
  if (owner.type === 'establishment') {
    if (draft.source === 'establishment') return 'Informação própria deste estabelecimento.';
    if (draft.inherited_from === 'company') return `Herdado da empresa ${owner.company_name}.`;
    return section.establishment_only ? 'Ainda não cadastrada neste estabelecimento.' : 'Padrão da plataforma: a empresa ainda não definiu esta seção.';
  }
  return draft.source === 'company' ? 'Definido pela empresa.' : 'Padrão da plataforma: ainda não definido.';
}
