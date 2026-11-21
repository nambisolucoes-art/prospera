import { useRef, useState } from 'react';
import BusinessList, {
  type PageSummary,
} from '@/components/BusinessList/BusinessList';
import BusinessListSearch from '@/components/BusinessList/BusinessListSearch';
import { useBusinessListFilters } from '@/components/BusinessList/useBusinessListFilters';
import Button from '@/components/Button/Button';
import SelectControl from '@/components/SelectControl/SelectControl';
import StatusLabel from '@/components/StatusLabel/StatusLabel';
import { useApiPage } from '@/hooks/useApiPage';
import type { SessionProps } from '@/types/Session.types';
import SaasLayout from './SaasLayout';
import EstablishmentPreview from './EstablishmentPreview';
import PromoteEstablishmentModal from './PromoteEstablishmentModal';
import EstablishmentLabels from './EstablishmentLabels';
import { companyName, type CompanyLabel, type EstablishmentRow } from './Organization.types';
import './Organization.css';
export default function Establishments({
  establishments,
  companies,
  filters,
}: {
  establishments: PageSummary & { data: EstablishmentRow[] };
  companies: CompanyLabel[];
  filters: { search: string; company: string; status: string };
}) {
  const { auth } = useApiPage<SessionProps>().props;
  const writable = auth.inspection_mode !== 'read-only';
  const can = (action: string) =>
    auth.permissions.includes(`account.establishments.${action}`);
  const [preview, setPreview] = useState<EstablishmentRow>();
  const [promoting, setPromoting] = useState<EstablishmentRow>();
  const trigger = useRef<HTMLElement | null>(null);
  const restoreFocus = () => requestAnimationFrame(() => trigger.current?.focus());
  const { query, setQuery, submit, apply } = useBusinessListFilters('/conta/estabelecimentos', filters.search);
  const severalCompanies = companies.length > 1;
  return (
    <SaasLayout
      tenant
      title="Estabelecimentos"
      actions={
        writable &&
        can('create') && (
          <Button
            href={filters.company ? `/conta/estabelecimentos/cadastrar?empresa=${filters.company}` : '/conta/estabelecimentos/cadastrar'}
            tone="primary"
            labelIconClass="fa fa-plus"
            aria-label="Cadastrar estabelecimento"
          >
            Cadastrar
          </Button>
        )
      }
    >
      <div data-tour="establishments-list">
        <BusinessList
          label="Estabelecimentos"
          preferenceKey="establishments"
          page={establishments}
          rows={establishments.data}
          rowKey={(establishment) => establishment.id}
          empty={filters.search || filters.company || filters.status ? 'Nenhum estabelecimento encontrado.' : 'Nenhum estabelecimento cadastrado.'}
          filters={<>
            <BusinessListSearch tour="establishments-search" label="Buscar estabelecimentos" placeholder="Buscar por nome, código ou CNPJ"
              value={query} onChange={setQuery} onSubmit={submit} maxLength={100} />
            {severalCompanies && (
              <div data-tour="establishments-company">
                <SelectControl id="establishments-company-filter" label="Empresa" value={filters.company} searchable
                  onChange={(value) => apply({ company: value })}
                  options={[{ value: '', label: 'Todas as empresas' },
                    ...companies.map((company) => ({ value: String(company.id), label: companyName(company) }))]} />
              </div>
            )}
            <div data-tour="establishments-status">
              <SelectControl id="establishments-status-filter" label="Situação" value={filters.status}
                onChange={(value) => apply({ status: value })}
                options={[{ value: '', label: 'Todas as situações' }, { value: 'active', label: 'Ativos' }, { value: 'inactive', label: 'Inativos' }]} />
            </div>
          </>}
          columns={[
            {
              id: 'actions',
              label: 'Ações',
              width: 130,
              compactWidth: 112,
              locked: true,
              render: (establishment) => (
                <div className="business-list__actions">
                  {can('view') && (
                    <Button
                      iconOnly
                      tone="primary"
                      leftIconClass="fa fa-info"
                      title="Visualizar cadastro"
                      aria-label={`Visualizar estabelecimento ${establishment.name}`}
                      aria-haspopup="dialog"
                      onClick={(event) => {
                        trigger.current = event.currentTarget;
                        setPreview(establishment);
                      }}
                    />
                  )}
                  {writable && can('update') && (
                    <Button
                      href={`/conta/estabelecimentos/${establishment.id}/editar`}
                      iconOnly
                      tone="primary"
                      leftIconClass="fa fa-pencil"
                      title="Editar cadastro"
                      aria-label={`Editar estabelecimento ${establishment.name}`}
                    />
                  )}
                  {writable && can('promote') && !establishment.is_primary && establishment.active && establishment.company.active && (
                    <Button
                      iconOnly
                      tone="primary"
                      leftIconClass="fa fa-flag"
                      title="Tornar principal"
                      aria-label={`Tornar ${establishment.name} o estabelecimento principal`}
                      aria-haspopup="dialog"
                      onClick={(event) => {
                        trigger.current = event.currentTarget;
                        setPromoting(establishment);
                      }}
                    />
                  )}
                </div>
              ),
            },
            {
              id: 'name',
              label: 'Nome',
              width: 200,
              compact: true,
              render: (establishment) => <strong title={establishment.name}>{establishment.name}</strong>,
            },
            ...(severalCompanies ? [{
              id: 'company',
              label: 'Empresa',
              width: 200,
              render: (establishment: EstablishmentRow) => companyName(establishment.company),
            }] : []),
            {
              id: 'classification',
              label: 'Matriz ou filial',
              width: 190,
              render: (establishment) => (
                <EstablishmentLabels classification={establishment.classification} primary={establishment.is_primary} />
              ),
            },
            {
              id: 'city',
              label: 'Município e UF',
              width: 170,
              render: (establishment) => establishment.city
                ? `${establishment.city}${establishment.state ? ` (${establishment.state})` : ''}`
                : 'Não informado',
            },
            {
              id: 'tax_document',
              label: 'CNPJ',
              width: 180,
              render: (establishment) => establishment.tax_document_label ?? 'Não informado',
            },
            {
              id: 'active',
              label: 'Situação',
              width: 130,
              compact: true,
              render: (establishment) => <StatusLabel active={establishment.active} masculine />,
            },
          ]}
        />
      </div>
      {preview && (
        <EstablishmentPreview
          establishment={preview}
          onClose={() => {
            setPreview(undefined);
            restoreFocus();
          }}
        />
      )}
      {promoting && (
        <PromoteEstablishmentModal
          establishment={promoting}
          onClose={() => {
            setPromoting(undefined);
            restoreFocus();
          }}
        />
      )}
    </SaasLayout>
  );
}
