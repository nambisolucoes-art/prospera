export type MediaVariantInfo = { kind: string; width: number; height: number; bytes: number; mime: string };

export type MediaItem = {
  id: number;
  purpose: string;
  purpose_label: string;
  scope: 'corporate' | 'local';
  establishment_name: string | null;
  mime: string;
  bytes: number;
  width: number;
  height: number;
  status: 'ready' | 'removal_requested';
  alt_text: string | null;
  license_note: string | null;
  created_at: string | null;
  thumb_kind: string | null;
  variants: MediaVariantInfo[];
  can_update: boolean;
  can_delete: boolean;
};

export type MediaOwner = { type: 'company' | 'establishment'; code: string; name: string; company_name?: string };
export type MediaPurposeOption = { value: string; label: string };
export type MediaLimits = { max_mb: number; max_megapixels: number; nominal_megapixels: number; types: string[] };
export type MediaPageData = {
  data: MediaItem[];
  current_page: number;
  last_page: number;
  total: number;
  prev_page_url: string | null;
  next_page_url: string | null;
};

// Base das rotas do dono, relativa à conta: `@/empresa/{codigo}/midia` ou `@/estabelecimento/{codigo}/midia`.
export function mediaBase(owner: MediaOwner) {
  return `@/${owner.type === 'company' ? 'empresa' : 'estabelecimento'}/${owner.code}/midia`;
}

export function formatBytes(bytes: number) {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1048576) return `${(bytes / 1024).toFixed(0)} KB`;
  return `${(bytes / 1048576).toFixed(1).replace('.', ',')} MB`;
}

export function formatDate(iso: string | null) {
  if (!iso) return '';
  const date = new Date(iso);
  return `${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}/${date.getFullYear()}`;
}
