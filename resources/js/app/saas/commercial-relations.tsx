import { useRef, useState } from 'react';
import BusinessList, { type PageSummary } from '@/components/BusinessList/BusinessList';
import BusinessListSearch from '@/components/BusinessList/BusinessListSearch';
import { useBusinessListFilters } from '@/components/BusinessList/useBusinessListFilters';
import Button from '@/components/Button/Button';
import SelectControl from '@/components/SelectControl/SelectControl';
import StatusLabel from '@/components/StatusLabel/StatusLabel';
import TagLabel from '@/components/TagLabel/TagLabel';
import { useApiForm } from '@/hooks/useApiForm';
import { useApiPage } from '@/hooks/useApiPage';
import type { SessionProps } from '@/types/Session.types';
import { contextualUrl } from '@/utils/organizationContext';
import PersonPreview from './PersonPreview';
import SaasLayout from './SaasLayout';
import type { PersonRow } from './Directory.types';
import './CommercialRelations.css';
import './Directory.css';

type Role = 'customer' | 'supplier';
type Company = { id: number; code: string; name: string; can_create: boolean };
type Relation = {
  id: number;
  person_id: number;
  name: string;
  email: string | null;
  tags: { id: number; name: string; color: string }[];
  document: string;
  active: boolean;
  company: { id: number; code: string; name: string; active: boolean };
  can_activate: boolean;
  can_edit: boolean;
};

const copy = {
  customer: { title: 'Clientes', singular: 'cliente', segment: 'clientes', empty: 'Nenhum cliente nas empresas desta área de trabalho.' },
  supplier: { title: 'Fornecedores', singular: 'fornecedor', segment: 'fornecedores', empty: 'Nenhum fornecedor nas empresas desta área de trabalho.' },
};

// Clientes e Fornecedores (E07): projeção das relações das empresas das unidades da URL.
export default function CommercialRelations({ role, relations, companies, filters }: {
  role: Role;
  relations: PageSummary & { data: Relation[] };
  companies: Company[];
  filters: { search: string; status: string; company: string };
}) {
  const { props, url } = useApiPage<SessionProps>();
  const text = copy[role];
  const writable = props.auth.inspection_mode !== 'read-only';
  const canViewPeople = props.auth.permissions.includes('account.people.view');
  const canRegister = companies.some((company) => company.can_create);
  const registrationUrl = contextualUrl(`~/${text.segment}/cadastrar`, url);
  const { query, setQuery, submit, apply } = useBusinessListFilters(`~/${text.segment}`, filters.search);
  const trigger = useRef<HTMLElement | null>(null);
  const [preview, setPreview] = useState<Relation>();
  const status = useApiForm({});
  const base = (relation: Relation) => `/conta/empresa/${relation.company.code}/${text.segment}/${relation.id}`;
  const close = (reset: () => void) => {
    reset();
    trigger.current?.focus();
  };

  return (
    <SaasLayout
      tenant
      title={text.title}
      breadcrumb={[{ label: text.title }]}
      actions={writable && canRegister && (
        <span data-tour="commercial-register">
          <Button href={registrationUrl} tone="primary" labelIconClass="fa fa-plus">
            Cadastrar
          </Button>
        </span>
      )}
    >
      <div className="commercial-relations__directory" data-tour="commercial-list">
        <BusinessList
          label={text.title}
          preferenceKey={`commercial-${role}`}
          page={relations}
          rows={relations.data}
          rowKey={(relation) => relation.id}
          empty={filters.search || filters.status || filters.company ? 'Nenhuma relação encontrada.' : text.empty}
          filters={<>
            <BusinessListSearch tour="commercial-search" label={`Buscar ${text.title.toLowerCase()}`}
              placeholder="Nome, e-mail, CPF ou CNPJ" value={query} onChange={setQuery} onSubmit={submit} />
            <div data-tour="commercial-status"><SelectControl id="commercial-status" label="Situação" value={filters.status}
              options={[{ value: '', label: 'Todas as situações' }, { value: 'active', label: 'Ativos' }, { value: 'inactive', label: 'Inativos' }]}
              onChange={(value) => apply({ status: value })} /></div>
            {companies.length > 1 && (
              <div data-tour="commercial-company"><SelectControl id="commercial-company" label="Empresa" value={filters.company}
                options={[{ value: '', label: 'Todas as empresas' }, ...companies.map((company) => ({ value: company.code, label: company.name }))]}
                onChange={(value) => apply({ empresa: value })} /></div>
            )}
          </>}
          columns={[
            {
              id: 'actions',
              label: 'Ações',
              width: 130,
              compactWidth: 116,
              locked: true,
              render: (relation) => (
                <div className="business-list__actions">
                  {canViewPeople && (
                    <Button iconOnly tone="primary" leftIconClass="fa fa-info" title="Visualizar cadastro"
                      aria-label={`Visualizar pessoa ${relation.name}`} aria-haspopup="dialog"
                      onClick={(event) => { trigger.current = event.currentTarget; setPreview(relation); }} />
                  )}
                  {writable && relation.company.active && relation.can_edit && (
                    <Button iconOnly tone="primary" leftIconClass="fa fa-pencil" title={`Editar ${text.singular}`}
                      aria-label={`Editar ${text.singular} ${relation.name} em ${relation.company.name}`}
                      href={contextualUrl(`~/${text.segment}/${relation.company.code}/${relation.id}/editar`, url)} />
                  )}
                  {writable && relation.company.active && !relation.active && relation.can_activate && (
                    <Button iconOnly tone="success" leftIconClass="fa fa-check" title={`Ativar ${text.singular}`}
                      aria-label={`Ativar ${text.singular} ${relation.name} em ${relation.company.name}`} disabled={status.processing}
                      onClick={() => status.submit('patch', `${base(relation)}/ativar`, { preserveScroll: true })} />
                  )}
                </div>
              ),
            },
            {
              id: 'name',
              label: 'Nome',
              width: 220,
              compact: true,
              render: (relation) => (
                <>
                  <strong title={relation.name}>{relation.name}</strong>
                  {companies.length > 1 && (
                    <span className="commercial-relations__compact-company">{relation.company.name}</span>
                  )}
                </>
              ),
            },
            { id: 'document', label: 'CPF ou CNPJ', width: 170, render: (relation) => relation.document },
            ...(companies.length > 1 ? [{ id: 'company', label: 'Empresa', width: 180, render: (relation: Relation) => relation.company.name }] : []),
            { id: 'status', label: 'Situação', width: 110, compact: true, render: (relation) => <StatusLabel active={relation.active} masculine /> },
            {
              id: 'tags', label: 'Etiquetas', width: 200,
              render: (relation) => relation.tags.length > 0
                ? relation.tags.map((tag) => <TagLabel key={tag.id} name={tag.name} color={tag.color} />)
                : 'Não informado',
            },
            { id: 'email', label: 'E-mail', width: 220, render: (relation) => relation.email ?? 'Não informado' },
          ]}
        />
      </div>
      {status.errors._form && <p className="formField__error" role="alert">{status.errors._form}</p>}
      {preview && (
        <PersonPreview person={{ id: preview.person_id, name: preview.name } as PersonRow}
          onClose={() => close(() => setPreview(undefined))} />
      )}
    </SaasLayout>
  );
}
