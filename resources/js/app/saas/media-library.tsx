import { useState } from 'react';
import { Select } from '@/components/BusinessForm/BusinessFormFields';
import Button from '@/components/Button/Button';
import CardBox from '@/components/CardBox/CardBox';
import Label from '@/components/Label/Label';
import TextInput from '@/components/TextInput/TextInput';
import { useApiPage } from '@/hooks/useApiPage';
import { useContextUrl } from '@/hooks/useContextUrl';
import type { SessionProps } from '@/types/Session.types';
import MediaDetailsModal from './MediaDetailsModal';
import MediaUploadModal from './MediaUploadModal';
import { formatBytes, mediaBase, type MediaItem, type MediaLimits, type MediaOwner, type MediaPageData, type MediaPurposeOption } from './Media.types';
import SaasLayout from './SaasLayout';
import './Institutional.css';
import './Media.css';

type Filters = { purpose: string; scope: string; search: string };

// Biblioteca de mídia de uma empresa ou de um estabelecimento (S08): miniaturas autorizadas, filtros, envio e detalhes.
export default function MediaLibrary({ owner, media, filters, purposes, canCreate, limits }: {
  owner: MediaOwner; media: MediaPageData; filters: Filters; purposes: MediaPurposeOption[]; canCreate: boolean; limits: MediaLimits;
}) {
  const page = useApiPage<SessionProps>();
  const { auth } = page.props;
  const { navigate, url } = page;
  const contextUrl = useContextUrl();
  const writable = auth.inspection_mode !== 'read-only';
  const [uploading, setUploading] = useState(false);
  const [selectedId, setSelectedId] = useState<number>();
  const [search, setSearch] = useState(filters.search);
  const selected = media.data.find((item) => item.id === selectedId);
  const base = mediaBase(owner);
  const unit = owner.type === 'establishment';

  const go = (next: Partial<Filters>, page?: number) => {
    const query = new URLSearchParams();
    const merged = { ...filters, ...next };
    if (merged.purpose) query.set('purpose', merged.purpose);
    if (merged.scope) query.set('scope', merged.scope);
    if (merged.search) query.set('search', merged.search);
    if (page && page > 1) query.set('page', String(page));
    const qs = query.toString();
    void navigate(`${url.split('?')[0]}${qs ? `?${qs}` : ''}`);
  };

  return (
    <SaasLayout
      tenant
      title="Mídia"
      breadcrumb={[{ label: 'Institucional' }, { label: 'Mídia', href: '@/midia' }, { label: owner.name }]}
      actions={canCreate && writable && (
        <span data-tour="media-upload">
          <Button tone="primary" labelIconClass="fa fa-upload" aria-haspopup="dialog" onClick={() => setUploading(true)}>Enviar imagem</Button>
        </span>
      )}
    >
      <CardBox>
        <div className="institutional__owner">
          <div>
            <h2 className="institutional__owner-name">{owner.name}</h2>
            <p className="institutional__note">
              {unit
                ? `Biblioteca local de ${owner.name} e biblioteca corporativa de ${owner.company_name} (somente consulta).`
                : 'Biblioteca corporativa e bibliotecas locais das unidades.'}
            </p>
          </div>
          <Label variant={unit ? 'info' : 'primary'}>{unit ? 'Estabelecimento' : 'Empresa'}</Label>
        </div>
      </CardBox>

      <CardBox>
        <form className="media__filters" data-tour="media-filters" role="search" onSubmit={(event) => { event.preventDefault(); go({ search }); }}>
          <Select label="Uso" layout="stacked" value={filters.purpose} onChange={(purpose) => go({ purpose })}
            options={[{ value: '', label: 'Todos os usos' }, ...purposes]} />
          <Select label="Biblioteca" layout="stacked" value={filters.scope} onChange={(scope) => go({ scope })}
            options={[{ value: '', label: 'Todas' }, { value: 'corporate', label: 'Corporativa' }, { value: 'local', label: 'Local' }]} />
          <label className="media__search">
            <span className="media__search-label">Buscar no texto alternativo</span>
            <TextInput type="search" value={search} maxLength={100} onChange={(event) => setSearch(event.target.value)} />
          </label>
          <Button type="submit" tone="white">Buscar</Button>
        </form>
      </CardBox>

      {media.data.length === 0 ? (
        <CardBox>
          <p className="institutional__empty" data-tour="media-empty">
            {filters.purpose || filters.scope || filters.search
              ? 'Nenhuma imagem encontrada com estes filtros.'
              : 'Esta biblioteca ainda não tem imagens.'}
            {canCreate && writable && ' Use Enviar imagem para incluir a primeira.'}
          </p>
        </CardBox>
      ) : (
        <ul className="media__grid" data-tour="media-grid" aria-label="Imagens da biblioteca">
          {media.data.map((item: MediaItem) => (
            <li key={item.id} className="media__card">
              <button type="button" className="media__thumb" aria-haspopup="dialog"
                aria-label={`Detalhes da imagem ${item.id}: ${item.purpose_label}${item.alt_text ? `, ${item.alt_text}` : ''}`}
                onClick={() => setSelectedId(item.id)}>
                {item.thumb_kind
                  ? <img src={contextUrl(`${base}/${item.id}/variantes/${item.thumb_kind}`)} alt={item.alt_text ?? ''} loading="lazy" />
                  : <span className="media__nothumb">Sem miniatura</span>}
              </button>
              <div className="media__meta">
                <strong>{item.purpose_label}</strong>
                <span>{item.width} x {item.height} px, {formatBytes(item.bytes)}</span>
                <div className="institutional__badges">
                  <Label variant={item.scope === 'corporate' ? 'primary' : 'info'}>{item.scope === 'corporate' ? 'Corporativa' : (item.establishment_name ?? 'Local')}</Label>
                  {item.status === 'removal_requested' && <Label variant="warning">Exclusão solicitada</Label>}
                  {!item.alt_text && <Label variant="warning">Sem texto alternativo</Label>}
                </div>
              </div>
            </li>
          ))}
        </ul>
      )}

      {media.last_page > 1 && (
        <nav className="media__pages" aria-label="Paginação">
          <Button tone="white" size="sm" disabled={!media.prev_page_url} onClick={() => go({}, media.current_page - 1)}>Anterior</Button>
          <span>Página {media.current_page} de {media.last_page} ({media.total} imagens)</span>
          <Button tone="white" size="sm" disabled={!media.next_page_url} onClick={() => go({}, media.current_page + 1)}>Próxima</Button>
        </nav>
      )}

      {uploading && <MediaUploadModal owner={owner} purposes={purposes} limits={limits} onClose={() => setUploading(false)} />}
      {selected && <MediaDetailsModal owner={owner} item={selected} writable={writable} onClose={() => setSelectedId(undefined)} />}
    </SaasLayout>
  );
}
