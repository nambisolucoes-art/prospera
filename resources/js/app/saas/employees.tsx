import { useRef, useState } from 'react';
import BusinessList, { type PageSummary } from '@/components/BusinessList/BusinessList';
import BusinessListSearch from '@/components/BusinessList/BusinessListSearch';
import { useBusinessListFilters } from '@/components/BusinessList/useBusinessListFilters';
import Button from '@/components/Button/Button';
import Label from '@/components/Label/Label';
import TagMarquee from '@/components/TagMarquee/TagMarquee';
import SelectControl from '@/components/SelectControl/SelectControl';
import { useApiPage } from '@/hooks/useApiPage';
import type { SessionProps } from '@/types/Session.types';
import PersonPreview from './PersonPreview';
import SaasLayout from './SaasLayout';
import './Employees.css';

type Link = { id: number; company_code: string; company_name: string; company_active: boolean; active: boolean };
type Unit = { status: 'current' | 'future'; name: string; job_title: string | null; primary: boolean };
type EmployeeRow = {
  id: number;
  name: string;
  document: string | null;
  links: Link[];
  links_count: number;
  units: Unit[];
  assignments_count: number;
  active_count: number;
  can_view: boolean;
  can_edit: boolean;
  has_access?: boolean;
};
type Filters = {
  search: string; status: string; company: string; access: string;
  establishment: string; department: string; job_title: string; assignment: string;
};
type FilterOptions = {
  establishments: { code: string; name: string }[];
  departments: { key: string; name: string }[];
  job_titles: { key: string; name: string }[];
};
const unitText = (unit: Unit) => `${unit.name}${unit.primary ? ' (principal)' : ''}${unit.status === 'future' ? ' (futura)' : ''}`;

// Colaboradores (S04): uma linha por Pessoa, com os vínculos das empresas que o acesso alcança.
export default function Employees({ employees, filters, filterOptions, companies, canCreate, canSeeAccess, canViewPeople }: {
  employees: PageSummary & { data: EmployeeRow[] };
  filters: Filters;
  filterOptions: FilterOptions;
  companies: { code: string; name: string }[];
  canCreate: boolean;
  canSeeAccess: boolean;
  canViewPeople: boolean;
}) {
  const { auth } = useApiPage<SessionProps>().props;
  const writable = auth.inspection_mode !== 'read-only';
  const { query, setQuery, submit, apply } = useBusinessListFilters('@/colaboradores', filters.search);
  const trigger = useRef<HTMLElement | null>(null);
  const [preview, setPreview] = useState<EmployeeRow>();
  const filtered = Boolean(Object.values(filters).some(Boolean));

  return (
    <SaasLayout
      tenant
      title="Colaboradores"
      breadcrumb={[{ label: 'Institucional' }, { label: 'Colaboradores' }]}
      actions={writable && canCreate && (
        <span data-tour="employees-register">
          <Button href="@/colaboradores/cadastrar" tone="primary" labelIconClass="fa fa-plus"
            aria-label="Cadastrar colaborador">Cadastrar</Button>
        </span>
      )}
    >
      <div className="employees__directory" data-tour="employees-list">
        <BusinessList
          label="Colaboradores"
          preferenceKey="employees"
          page={employees}
          rows={employees.data}
          rowKey={(row) => row.id}
          empty={filtered
            ? 'Nenhum colaborador encontrado.'
            : 'Nenhum colaborador cadastrado. Use Cadastrar para começar.'}
          filters={<>
            <BusinessListSearch tour="employees-search" label="Buscar colaboradores"
              placeholder={canViewPeople ? 'Nome, e-mail, CPF ou CNPJ' : 'Buscar por nome'}
              value={query} onChange={setQuery} onSubmit={submit} maxLength={100} />
            {companies.length > 1 && (
              <div data-tour="employees-company">
                <SelectControl id="employees-company-filter" label="Empresa" value={filters.company}
                  onChange={(value) => apply({ company: value })}
                  options={[{ value: '', label: 'Todas as empresas' },
                    ...companies.map((company) => ({ value: company.code, label: company.name }))]} />
              </div>
            )}
            {filterOptions.establishments.length > 0 && (
              <div data-tour="employees-establishment">
                <SelectControl id="employees-establishment-filter" label="Estabelecimento" value={filters.establishment}
                  onChange={(value) => apply({ establishment: value })}
                  options={[{ value: '', label: 'Todos os estabelecimentos' },
                    ...filterOptions.establishments.map((item) => ({ value: item.code, label: item.name }))]} />
              </div>
            )}
            {filterOptions.departments.length > 0 && (
              <div data-tour="employees-department">
                <SelectControl id="employees-department-filter" label="Departamento" value={filters.department}
                  onChange={(value) => apply({ department: value })}
                  options={[{ value: '', label: 'Todos os departamentos' },
                    ...filterOptions.departments.map((item) => ({ value: item.key, label: item.name }))]} />
              </div>
            )}
            {filterOptions.job_titles.length > 0 && (
              <div data-tour="employees-job-title">
                <SelectControl id="employees-job-title-filter" label="Função" value={filters.job_title}
                  onChange={(value) => apply({ job_title: value })}
                  options={[{ value: '', label: 'Todas as funções' },
                    ...filterOptions.job_titles.map((item) => ({ value: item.key, label: item.name }))]} />
              </div>
            )}
            <div data-tour="employees-assignment">
              <SelectControl id="employees-assignment-filter" label="Situação da lotação" value={filters.assignment}
                onChange={(value) => apply({ assignment: value })}
                options={[{ value: '', label: 'Todas as lotações' }, { value: 'current', label: 'Vigente' },
                  { value: 'future', label: 'Futura' }, { value: 'ended', label: 'Encerrada' },
                  { value: 'none', label: 'Sem lotação' }]} />
            </div>
            <div data-tour="employees-status">
              <SelectControl id="employees-status-filter" label="Situação do vínculo" value={filters.status}
                onChange={(value) => apply({ status: value })}
                options={[{ value: '', label: 'Todas as situações' }, { value: 'active', label: 'Com vínculo ativo' },
                  { value: 'inactive', label: 'Com vínculo inativo' }]} />
            </div>
            {canSeeAccess && (
              <div data-tour="employees-access">
                <SelectControl id="employees-access-filter" label="Acesso à conta" value={filters.access}
                  onChange={(value) => apply({ access: value })}
                  options={[{ value: '', label: 'Com ou sem acesso' }, { value: 'yes', label: 'Possui acesso' },
                    { value: 'no', label: 'Sem acesso' }]} />
              </div>
            )}
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
                  {row.can_view && (
                    <Button iconOnly tone="primary" leftIconClass="fa fa-info" title="Visualizar cadastro"
                      aria-label={`Visualizar colaborador ${row.name}`} aria-haspopup="dialog"
                      onClick={(event) => { trigger.current = event.currentTarget; setPreview(row); }} />
                  )}
                  {writable && row.can_edit && (
                    <Button iconOnly tone="primary" leftIconClass="fa fa-pencil" title="Editar colaborador"
                      aria-label={`Editar colaborador ${row.name}`} href={`@/colaboradores/${row.id}/editar`} />
                  )}
                </div>
              ),
            },
            {
              id: 'name',
              label: 'Nome',
              width: 240,
              compactWidth: 140,
              compact: true,
              render: (row) => (
                <>
                  <strong title={row.name}>{row.name}</strong>
                  <span className="employees__compact-companies">
                    {row.links.map((link) => link.company_name + (link.active ? '' : ' (inativo)')).join(', ')}
                    {row.units.length > 0 && ` · ${row.units.map(unitText).join(', ')}`}
                  </span>
                </>
              ),
            },
            ...(canViewPeople
              ? [{ id: 'document', label: 'CPF ou CNPJ', width: 170, render: (row: EmployeeRow) => row.document ?? 'Não informado' }]
              : []),
            {
              id: 'companies',
              label: 'Empresas',
              width: 280,
              render: (row) => (
                <TagMarquee>
                  {row.links.map((link) => (
                    <Label key={link.id} variant={link.active ? 'success' : 'danger'}>
                      {link.company_name}{link.active ? '' : ' (inativo)'}
                    </Label>
                  ))}
                </TagMarquee>
              ),
            },
            {
              id: 'units',
              label: 'Unidades',
              width: 260,
              render: (row) => row.units.length > 0
                ? (
                  <TagMarquee>
                    {row.units.map((unit, index) => (
                      <Label key={`${unit.name}-${index}`} variant={unit.status === 'future' ? 'info' : 'primary'}>
                        {unitText(unit)}
                      </Label>
                    ))}
                  </TagMarquee>
                )
                : (row.assignments_count > 0 ? 'Sem lotação vigente' : 'Sem lotação'),
            },
            {
              id: 'links',
              label: 'Vínculos',
              width: 130,
              render: (row) => row.links_count === 1
                ? (row.active_count === 1 ? 'Ativo' : 'Inativo')
                : `${row.active_count} de ${row.links_count} ativos`,
            },
            ...(canSeeAccess
              ? [{
                id: 'access',
                label: 'Acesso à conta',
                width: 140,
                render: (row: EmployeeRow) => (
                  <Label variant={row.has_access ? 'success' : 'info'}>{row.has_access ? 'Possui acesso' : 'Sem acesso'}</Label>
                ),
              }]
              : []),
          ]}
        />
      </div>
      {preview && (
        <PersonPreview name={preview.name} endpoint={`@/colaboradores/${preview.id}/visualizar`}
          onClose={() => { setPreview(undefined); requestAnimationFrame(() => trigger.current?.focus()); }} />
      )}
    </SaasLayout>
  );
}
