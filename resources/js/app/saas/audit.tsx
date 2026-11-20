import { useEffect, useRef, useState } from 'react';
import BusinessList, {
  type PageSummary,
} from '@/components/BusinessList/BusinessList';
import Button from '@/components/Button/Button';
import Modal from '@/components/Modal/Modal';
import SelectControl, {
  type SelectOption,
} from '@/components/SelectControl/SelectControl';
import InputGroup from '@/components/InputGroup/InputGroup';
import TextInput from '@/components/TextInput/TextInput';
import { useApiPage } from '@/hooks/useApiPage';
import type { SessionProps } from '@/types/Session.types';
import { apiRequest } from '@/utils/apiRequest';
import SaasLayout from './SaasLayout';
import AuditEventLabel from '@/components/AuditEventLabel/AuditEventLabel';
import AuditRecordDetails from './AuditRecordDetails';
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
  context: string;
  represented_user_id: number | null;
};
export type AuditDetail = AuditRow & {
  changes: { label: string; before: unknown; after: unknown }[];
  technical: Record<string, unknown> | null;
};
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
  options: Record<string, SelectOption[]>;
  platform: boolean;
}) {
  const { navigate, props } = useApiPage<SessionProps>();
  const [search, setSearch] = useState(filters.search ?? '');
  const [selected, setSelected] = useState<AuditRow>();
  const [detail, setDetail] = useState<AuditDetail>();
  const [error, setError] = useState('');
  const trigger = useRef<HTMLElement | null>(null);
  const searchTimer = useRef<number | undefined>(undefined);
  const base = platform ? '/saas/auditoria' : '/conta/auditoria';
  const canView = props.auth.permissions.includes(
    (platform ? 'platform' : 'account') + '.audit.view',
  );
  useEffect(() => {
    setSearch(filters.search ?? '');
  }, [filters.search]);
  function filter(key: string, v: string) {
    window.clearTimeout(searchTimer.current);
    const params = new URLSearchParams(location.search);
    params.delete('page');
    if (key !== 'search') {
      if (search) params.set('search', search);
      else params.delete('search');
    }
    if (v) params.set(key, v);
    else params.delete(key);
    if (key === 'from' && v && (params.get('until') ?? v) < v)
      params.delete('until');
    void navigate(base + (params.size ? '?' + params : ''), {
      preserveScroll: true,
    });
  }
  const filterRef = useRef(filter);
  filterRef.current = filter;
  useEffect(() => {
    if (search === (filters.search ?? '')) return;
    searchTimer.current = window.setTimeout(
      () => filterRef.current('search', search),
      350,
    );
    return () => window.clearTimeout(searchTimer.current);
  }, [search, filters.search]);
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
          filters={
            <div className="audit-filters" data-tour="audit-filters">
              <form
                className="saas-search"
                role="search"
                onSubmit={(e) => {
                  e.preventDefault();
                  filter('search', search);
                }}
              >
                <InputGroup
                  action={
                    <button
                      className="inputGroup__button waves-effect"
                      type="submit"
                      aria-label="Buscar"
                      title="Buscar"
                    >
                      <i className="fa fa-search" aria-hidden="true" />
                    </button>
                  }
                >
                  <TextInput
                    type="search"
                    aria-label="Buscar no histórico"
                    placeholder="Buscar registro ou autor"
                    value={search}
                    maxLength={255}
                    onChange={(e) => setSearch(e.target.value)}
                  />
                </InputGroup>
              </form>
              {[
                ['entity', 'Tipo', 'entities'],
                ['event', 'Ação', 'events'],
                ['actor', 'Autor', 'actors'],
                ...(platform ? [['account', 'Conta', 'accounts']] : []),
              ].map(([key, label, source]) => (
                <SelectControl
                  key={key}
                  id={'audit-' + key}
                  label={label}
                  value={filters[key] ?? ''}
                  searchable
                  options={[
                    { value: '', label: 'Todos: ' + label },
                    ...options[source],
                  ]}
                  onChange={(v) => filter(key, v)}
                />
              ))}
              <TextInput
                type="date"
                aria-label="Data inicial"
                title="Data inicial"
                max={filters.until}
                value={filters.from ?? ''}
                onChange={(e) => filter('from', e.target.value)}
              />
              <TextInput
                type="date"
                aria-label="Data final"
                title="Data final"
                min={filters.from}
                value={filters.until ?? ''}
                onChange={(e) => filter('until', e.target.value)}
              />
            </div>
          }
          columns={[
            {
              id: 'actions',
              label: 'Ações',
              width: 78,
              compactWidth: 64,
              locked: true,
              render: (r) => (
                <div className="business-list__actions">
                  {canView && (
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
              id: 'actor',
              label: 'Quem fez',
              width: 210,
              render: (r) => r.actor,
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
