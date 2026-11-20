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
import CompanyPreview from './CompanyPreview';
import type { CompanyRow } from './Organization.types';
export default function Companies({
  companies,
  filters,
}: {
  companies: PageSummary & { data: CompanyRow[] };
  filters: { search: string; status: string };
}) {
  const { auth } = useApiPage<SessionProps>().props;
  const writable = auth.inspection_mode !== 'read-only';
  const can = (action: string) =>
    auth.permissions.includes(`account.companies.${action}`);
  const [preview, setPreview] = useState<CompanyRow>();
  const trigger = useRef<HTMLElement | null>(null);
  const { query, setQuery, submit, apply } = useBusinessListFilters('/conta/empresas', filters.search);
  return (
    <SaasLayout
      tenant
      title="Empresas"
      actions={
        writable &&
        can('create') && (
          <Button href="/conta/empresas/cadastrar" tone="primary" leftIconClass="fa fa-plus">
            Cadastrar empresa
          </Button>
        )
      }
    >
      <div data-tour="companies-list">
        <BusinessList
          label="Empresas"
          preferenceKey="companies"
          page={companies}
          rows={companies.data}
          rowKey={(company) => company.id}
          empty={filters.search || filters.status ? 'Nenhuma empresa encontrada.' : 'Nenhuma empresa cadastrada.'}
          filters={<>
            <BusinessListSearch tour="companies-search" label="Buscar empresas" placeholder="Buscar por nome, código ou CNPJ"
              value={query} onChange={setQuery} onSubmit={submit} maxLength={100} />
            <div data-tour="companies-status">
              <SelectControl id="companies-status-filter" label="Situação" value={filters.status}
                onChange={(value) => apply({ status: value })}
                options={[{ value: '', label: 'Todas as situações' }, { value: 'active', label: 'Ativas' }, { value: 'inactive', label: 'Inativas' }]} />
            </div>
          </>}
          columns={[
            {
              id: 'actions',
              label: 'Ações',
              width: 92,
              compactWidth: 78,
              locked: true,
              render: (company) => (
                <div className="business-list__actions">
                  {can('view') && (
                    <Button
                      iconOnly
                      tone="primary"
                      leftIconClass="fa fa-info"
                      title="Visualizar cadastro"
                      aria-label={`Visualizar empresa ${company.name}`}
                      aria-haspopup="dialog"
                      onClick={(event) => {
                        trigger.current = event.currentTarget;
                        setPreview(company);
                      }}
                    />
                  )}
                  {writable && can('update') && (
                    <Button
                      href={`/conta/empresas/${company.id}/editar`}
                      iconOnly
                      tone="primary"
                      leftIconClass="fa fa-pencil"
                      title="Editar cadastro"
                      aria-label={`Editar empresa ${company.name}`}
                    />
                  )}
                </div>
              ),
            },
            {
              id: 'name',
              label: 'Razão social ou nome',
              width: 260,
              compact: true,
              render: (company) => <strong title={company.name}>{company.name}</strong>,
            },
            {
              id: 'trade_name',
              label: 'Nome fantasia',
              width: 200,
              render: (company) => company.trade_name || 'Não informado',
            },
            {
              id: 'document',
              label: 'CNPJ ou CPF',
              width: 170,
              render: (company) => company.document_label ?? 'Não informado',
            },
            {
              id: 'establishments',
              label: 'Estabelecimentos',
              width: 140,
              render: (company) => company.establishments_count ?? 0,
            },
            {
              id: 'active',
              label: 'Situação',
              width: 110,
              compact: true,
              render: (company) => <StatusLabel active={company.active} />,
            },
          ]}
        />
      </div>
      {preview && (
        <CompanyPreview
          company={preview}
          onClose={() => {
            setPreview(undefined);
            requestAnimationFrame(() => trigger.current?.focus());
          }}
        />
      )}
    </SaasLayout>
  );
}
