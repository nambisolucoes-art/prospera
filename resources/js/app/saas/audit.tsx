import { useEffect, useRef, useState } from 'react';
import BusinessList, {
  type PageSummary,
} from '@/components/BusinessList/BusinessList';
import Button from '@/components/Button/Button';
import Modal from '@/components/Modal/Modal';
import SelectControl, {
  type SelectOption,
} from '@/components/SelectControl/SelectControl';
import BusinessListSearch from '@/components/BusinessList/BusinessListSearch';
import { useBusinessListFilters } from '@/components/BusinessList/useBusinessListFilters';
import DatePicker from '@/components/DatePicker/DatePicker';
import { useApiPage } from '@/hooks/useApiPage';
import type { SessionProps } from '@/types/Session.types';
import { apiRequest } from '@/utils/apiRequest';
import SaasLayout from './SaasLayout';
import AuditEventLabel from '@/components/AuditEventLabel/AuditEventLabel';
import AuditRecordDetails from './AuditRecordDetails';
import Label from '@/components/Label/Label';
import { auditPlace, type AuditLevel } from '@/utils/auditPlace';
import './AccountPreview.css';
import './Guidance.css';
export type AuditRow = {
  id: number;
  name: string;
  type: string;
  event: string;
  event_key: string;
  actor: string;
  created_at: string;
  account_id: number | null;
  account: string | null;
  context: string;
  origin: string;
  level: AuditLevel;
  company: string | null;
  establishment: string | null;
  represented_user_id: number | null;
  represented: string | null;
  can_view?: boolean;
};
export type AuditDetail = AuditRow & {
  inspection_mode: string | null;
  changes: { label: string; before: unknown; after: unknown }[];
  technical: Record<string, unknown> | null;
};
type AuditOption = SelectOption & { company?: string };
type Filters = Record<string, string>;
const date = (value: string) => new Date(value).toLocaleString('pt-BR');
export default function Audit({
  records,
  filters,
  options,
  platform,
}: {
  records: PageSummary & { data: AuditRow[] };
  filters: Filters;
  options: Record<string, AuditOption[]>;
  platform: boolean;
}) {
  const { props } = useApiPage<SessionProps>();
  const [selected, setSelected] = useState<AuditRow>();
  const [detail, setDetail] = useState<AuditDetail>();
  const [error, setError] = useState('');
  const trigger = useRef<HTMLElement | null>(null);
  const base = platform ? '/saas/auditoria' : '/conta/auditoria';
  const canView = props.auth.permissions.includes(
    (platform ? 'platform' : 'account') + '.audit.view',
  );
  const { query, setQuery, submit, apply } = useBusinessListFilters(base, filters.search ?? '');
  useEffect(() => {
    if (!selected) return;
    const abort = new AbortController();
    setDetail(undefined);
    setError('');
    apiRequest<AuditDetail>(`${base}/${selected.id}/visualizar`, {
      signal: abort.signal,
    })
      .then(setDetail)
      .catch(() => {
        if (!abort.signal.aborted)
          setError('Não foi possível abrir o registro.');
      });
    return () => abort.abort();
  }, [selected, base]);
  return (
    <SaasLayout tenant={!platform} title="Auditoria">
      <div data-tour="audit-list">
        <BusinessList
          label="Auditoria"
          preferenceKey="audit"
          page={records}
          rows={records.data}
          rowKey={(r) => r.id}
          empty="Nenhum registro encontrado."
          filters={<>
              <BusinessListSearch label="Buscar no histórico" placeholder="Buscar registro ou autor"
                value={query} onChange={setQuery} onSubmit={submit} />
              {[
                ['entity', 'Tipo', 'entities'],
                ['event', 'Ação', 'events'],
                ['actor', 'Autor', 'actors'],
                ['origin', 'Origem', 'origins'],
                ...(platform
                  ? [['account', 'Conta', 'accounts']]
                  : [
                      ['level', 'Nível', 'levels'],
                      ['company', 'Empresa', 'companies'],
                      ['establishment', 'Estabelecimento', 'establishments'],
                    ]),
              ].map(([key, label, source]) => {
                // Com uma empresa escolhida, só as unidades dela; sem alternativa, o filtro fica oculto.
                const choices = (options[source] ?? []).filter(
                  (option) =>
                    key !== 'establishment' ||
                    !filters.company ||
                    option.company === filters.company,
                );
                const organizational = ['level', 'company', 'establishment'].includes(key);
                if (organizational && choices.length < 2 && !filters[key]) return null;
                return (
                  <SelectControl
                    key={key}
                    id={'audit-' + key}
                    label={label}
                    value={filters[key] ?? ''}
                    searchable
                    options={[{ value: '', label: 'Todos: ' + label }, ...choices]}
                    onChange={(v) =>
                      apply(
                        key === 'company' ? { company: v, establishment: '' } : { [key]: v },
                      )
                    }
                  />
                );
              })}
              <DatePicker id="audit-from" label="Data inicial" placeholder="Data inicial" max={filters.until}
                value={filters.from ?? ''}
                onChange={(v) => apply({ from: v, ...(v && filters.until && filters.until < v ? { until: '' } : {}) })} />
              <DatePicker id="audit-until" label="Data final" placeholder="Data final" min={filters.from}
                value={filters.until ?? ''} onChange={(v) => apply({ until: v })} />
          </>}
          columns={[
            {
              id: 'actions',
              label: 'Ações',
              width: 78,
              compactWidth: 64,
              locked: true,
              render: (r) => (
                <div className="business-list__actions">
                  {(r.can_view ?? canView) && (
                    <Button
                      iconOnly
                      tone="primary"
                      leftIconClass="fa fa-info"
                      aria-label={'Visualizar registro ' + r.name}
                      title="Visualizar registro"
                      aria-haspopup="dialog"
                      onClick={(e) => {
                        trigger.current = e.currentTarget;
                        setSelected(r);
                      }}
                    />
                  )}
                </div>
              ),
            },
            {
              id: 'name',
              label: 'Registro',
              width: 300,
              compact: true,
              render: (r) => <strong title={r.name}>{r.name}</strong>,
            },
            {
              id: 'event',
              label: 'Ação',
              width: 140,
              compact: true,
              render: (r) => <AuditEventLabel event={r.event_key}>{r.event}</AuditEventLabel>,
            },
            {
              id: 'type',
              label: 'Tipo',
              width: 130,
              render: (r) => r.type,
            },
            {
              id: 'place',
              label: 'Onde',
              width: 200,
              render: (r) => {
                const place = auditPlace(r);
                return (
                  <span className="audit-list__stack" title={[place.main, place.detail].filter(Boolean).join(', ')}>
                    <span>{place.main}</span>
                    {place.detail && <small>{place.detail}</small>}
                  </span>
                );
              },
            },
            {
              id: 'actor',
              label: 'Quem fez',
              width: 220,
              render: (r) =>
                r.context === 'platform-account' ? (
                  <span className="audit-list__stack" title={r.actor}>
                    <span>{r.actor}</span>
                    <small>
                      <Label variant="purple">Inspeção</Label>
                      {r.represented ? 'Representando ' + r.represented : 'Representado não registrado'}
                    </small>
                  </span>
                ) : (
                  r.actor
                ),
            },
            {
              id: 'date',
              label: 'Quando',
              width: 180,
              render: (r) => date(r.created_at),
            },
          ]}
        />
      </div>
      {selected && (
        <Modal
          open
          title={
            detail
              ? `${detail.actor}: ${detail.event.toLocaleLowerCase()} de ${detail.name}`
              : 'Registro de auditoria'
          }
          bootstrap
          manageFocus
          size="large"
          contentClassName="account-preview audit-record-modal"
          footer={
            <div
              data-tour="audit-modal-footer"
              className="audit-record__footer"
            >
              <Button
                tone="white"
                onClick={(event) => {
                  setSelected(undefined);
                  if (event.nativeEvent.isTrusted)
                    requestAnimationFrame(() => trigger.current?.focus());
                }}
              >
                Fechar
              </Button>
            </div>
          }
          onClose={() => {
            setSelected(undefined);
            requestAnimationFrame(() => trigger.current?.focus());
          }}
        >
          {!detail ? (
            <p role={error ? 'alert' : 'status'}>{error || 'Carregando…'}</p>
          ) : (
            <AuditRecordDetails key={detail.id} detail={detail} />
          )}
        </Modal>
      )}
    </SaasLayout>
  );
}
