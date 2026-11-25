import { useRef, useState } from 'react';
import BusinessList, { type PageSummary } from '@/components/BusinessList/BusinessList';
import BusinessListSearch from '@/components/BusinessList/BusinessListSearch';
import { useBusinessListFilters } from '@/components/BusinessList/useBusinessListFilters';
import Button from '@/components/Button/Button';
import SelectControl from '@/components/SelectControl/SelectControl';
import StatusLabel from '@/components/StatusLabel/StatusLabel';
import { useApiPage } from '@/hooks/useApiPage';
import type { SessionProps } from '@/types/Session.types';
import CatalogEditorModal from './CatalogEditorModal';
import CatalogPreview from './CatalogPreview';
import { article, type CatalogConfig, type CatalogRow, type CompanyOption } from './CompanyCatalog.types';
import SaasLayout from './SaasLayout';

const dateTime = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' });

/**
 * Lista e janela de cadastro de departamentos e funções das empresas que o acesso alcança (S03).
 * Cada tela de negócio declara a própria configuração; a composição é a de Etiquetas e Empresas.
 */
export default function CompanyCatalog({ config, page, filters, companies, creatable }: {
  config: CatalogConfig;
  page: PageSummary & { data: CatalogRow[] };
  filters: { search: string; status: string; company: string };
  companies: CompanyOption[];
  creatable: CompanyOption[];
}) {
  const { auth } = useApiPage<SessionProps>().props;
  const writable = auth.inspection_mode !== 'read-only';
  const can = (action: string) => auth.permissions.includes(`account.${config.module}.${action}`);
  const [editor, setEditor] = useState<CatalogRow | null | undefined>(undefined);
  const [preview, setPreview] = useState<CatalogRow>();
  const trigger = useRef<HTMLElement | null>(null);
  const { query, setQuery, submit, apply } = useBusinessListFilters(`@/${config.segment}`, filters.search);
  const restoreFocus = () => requestAnimationFrame(() => trigger.current?.focus());
  const closeEditor = (restore = true) => {
    setEditor(undefined);
    if (restore) restoreFocus();
  };
  const plural = config.title.toLowerCase();
  const the = article(config);

  return (
    <SaasLayout
      tenant
      title={config.title}
      breadcrumb={[{ label: 'Institucional' }, { label: config.title }]}
      actions={
        writable && can('create') && creatable.length > 0 && (
          <Button
            onClick={(event) => {
              trigger.current = event.currentTarget;
              setEditor(null);
            }}
            aria-haspopup="dialog"
            tone="primary"
            labelIconClass="fa fa-plus"
            aria-label={`Cadastrar ${config.noun}`}
          >
            Cadastrar
          </Button>
        )
      }
    >
      <div data-tour={`${config.tour}-list`}>
        <BusinessList
          label={config.title}
          preferenceKey={config.tour}
          page={page}
          rows={page.data}
          rowKey={(row) => row.id}
          empty={
            filters.search || filters.status
              ? `Nenhum${config.masculine ? '' : 'a'} ${config.noun} encontrad${the}.`
              : `Nenhum${config.masculine ? '' : 'a'} ${config.noun} cadastrad${the}. Use Cadastrar para começar.`
          }
          filters={<>
            <BusinessListSearch tour={`${config.tour}-search`} label={`Buscar ${plural}`} placeholder="Buscar por nome"
              value={query} onChange={setQuery} onSubmit={submit} maxLength={100} />
            {companies.length > 1 && (
              <div data-tour={`${config.tour}-company`}>
                <SelectControl id={`${config.tour}-company-filter`} label="Empresa" value={filters.company}
                  onChange={(value) => apply({ company: value })}
                  options={[
                    { value: '', label: 'Todas as empresas' },
                    ...companies.map((company) => ({ value: company.code, label: company.name })),
                  ]} />
              </div>
            )}
            <div data-tour={`${config.tour}-status`}>
              <SelectControl id={`${config.tour}-status-filter`} label="Situação" value={filters.status}
                onChange={(value) => apply({ status: value })}
                options={[
                  { value: '', label: 'Todas as situações' },
                  { value: 'active', label: config.masculine ? 'Ativos' : 'Ativas' },
                  { value: 'inactive', label: config.masculine ? 'Inativos' : 'Inativas' },
                ]} />
            </div>
          </>}
          columns={[
            {
              id: 'actions',
              label: 'Ações',
              width: 92,
              compactWidth: 78,
              locked: true,
              render: (row) => (
                <div className="business-list__actions">
                  {can('view') && (
                    <Button
                      iconOnly
                      tone="primary"
                      leftIconClass="fa fa-info"
                      title="Visualizar cadastro"
                      aria-label={`Visualizar ${config.noun} ${row.name}`}
                      aria-haspopup="dialog"
                      onClick={(event) => {
                        trigger.current = event.currentTarget;
                        setPreview(row);
                      }}
                    />
                  )}
                  {writable && can('update') && (
                    <Button
                      iconOnly
                      tone="primary"
                      leftIconClass="fa fa-pencil"
                      title="Editar cadastro"
                      aria-label={`Editar ${config.noun} ${row.name}`}
                      aria-haspopup="dialog"
                      onClick={(event) => {
                        trigger.current = event.currentTarget;
                        setEditor(row);
                      }}
                    />
                  )}
                </div>
              ),
            },
            {
              id: 'name',
              label: 'Nome',
              width: 320,
              compactWidth: 118,
              compact: true,
              render: (row) => <strong title={row.name}>{row.name}</strong>,
            },
            ...(companies.length > 1
              ? [{
                id: 'company',
                label: 'Empresa',
                width: 220,
                render: (row: CatalogRow) => <span title={row.company}>{row.company}</span>,
              }]
              : []),
            {
              id: 'active',
              label: 'Situação',
              width: 120,
              compact: true,
              render: (row) => <StatusLabel active={row.active} masculine={config.masculine} />,
            },
            {
              id: 'updated_at',
              label: 'Última alteração',
              width: 170,
              render: (row) => (row.updated_at ? dateTime.format(new Date(row.updated_at)) : 'Não informado'),
            },
          ]}
        />
      </div>
      {editor !== undefined && <CatalogEditorModal config={config} row={editor} creatable={creatable} onClose={closeEditor} />}
      {preview && (
        <CatalogPreview
          config={config}
          row={preview}
          onClose={() => {
            setPreview(undefined);
            restoreFocus();
          }}
        />
      )}
    </SaasLayout>
  );
}
