import { useRef, useState } from 'react';
import BusinessList, {
  type PageSummary,
} from '@/components/BusinessList/BusinessList';
import BusinessListSearch from '@/components/BusinessList/BusinessListSearch';
import { useBusinessListFilters } from '@/components/BusinessList/useBusinessListFilters';
import Button from '@/components/Button/Button';
import TagLabel from '@/components/TagLabel/TagLabel';
import SelectControl from '@/components/SelectControl/SelectControl';
import { useApiPage } from '@/hooks/useApiPage';
import type { SessionProps } from '@/types/Session.types';
import SaasLayout from './SaasLayout';
import PersonPreview from './PersonPreview';
import type { PersonRow } from './Directory.types';
import './Directory.css';
export default function People({
  people,
  search,
  suppliersOnly = false,
  customersOnly = false,
}: {
  people: PageSummary & { data: PersonRow[] };
  search: string;
  suppliersOnly?: boolean;
  customersOnly?: boolean;
}) {
  const { props } = useApiPage<SessionProps>();
  const readOnly = props.auth.inspection_mode === 'read-only';
  const [preview, setPreview] = useState<PersonRow>();
  const trigger = useRef<HTMLElement | null>(null);
  const { query, setQuery, submit, apply } = useBusinessListFilters('/conta/pessoas', search);
  return (
    <SaasLayout
      tenant
      title="Pessoas"
      actions={
        !readOnly &&
        props.auth.permissions.includes('account.people.create') && (
          <Button
            href="/conta/pessoas/cadastrar"
            tone="primary"
            labelIconClass="fa fa-plus"
            aria-label="Cadastrar pessoa"
          >
            Cadastrar
          </Button>
        )
      }
    >
      <div data-tour="people-list">
        <BusinessList
          label="Pessoas"
          preferenceKey="people"
          page={people}
          rows={people.data}
          rowKey={(person) => person.id}
          empty={
            search ? 'Nenhuma pessoa encontrada.' : 'Nenhuma pessoa cadastrada.'
          }
          filters={<>
            <BusinessListSearch tour="people-search" label="Buscar pessoas" placeholder="Buscar por nome ou e-mail"
              value={query} onChange={setQuery} onSubmit={submit} />
            {(props.auth.permissions.includes('account.suppliers.list') || props.auth.permissions.includes('account.customers.list')) && (
              <div data-tour="people-suppliers"><SelectControl id="people-suppliers" label="Tipo de cadastro" value={customersOnly ? 'customers' : suppliersOnly ? 'suppliers' : ''}
                options={[{ value: '', label: 'Todas as pessoas' },
                  ...(props.auth.permissions.includes('account.customers.list') ? [{ value: 'customers', label: 'Clientes' }] : []),
                  ...(props.auth.permissions.includes('account.suppliers.list') ? [{ value: 'suppliers', label: 'Fornecedores' }] : []),
                ]}
                onChange={(value) => apply({ customers: value === 'customers' ? '1' : '', suppliers: value === 'suppliers' ? '1' : '' })} /></div>
            )}
          </>}

          columns={[
            {
              id: 'actions',
              label: 'Ações',
              width: 92,
              compactWidth: 78,
              locked: true,
              render: (person) => (
                <div className="business-list__actions">
                  {props.auth.permissions.includes('account.people.view') && (
                    <Button
                      iconOnly
                      tone="primary"
                      leftIconClass="fa fa-info"
                      title="Visualizar cadastro"
                      aria-label={`Visualizar pessoa ${person.name}`}
                      aria-haspopup="dialog"
                      onClick={(event) => {
                        trigger.current = event.currentTarget;
                        setPreview(person);
                      }}
                    />
                  )}
                  {!readOnly &&
                    props.auth.permissions.includes(
                      'account.people.update',
                    ) && (
                      <Button
                        href={`/conta/pessoas/${person.id}/editar`}
                        iconOnly
                        tone="primary"
                        leftIconClass="fa fa-pencil"
                        title="Editar cadastro"
                        aria-label={`Editar pessoa ${person.name}`}
                      />
                    )}
                </div>
              ),
            },
            {
              id: 'name',
              label: 'Nome',
              width: 220,
              compact: true,
              render: (person) => (
                <strong title={person.name}>{person.name}</strong>
              ),
            },
            {
              id: 'email',
              label: 'E-mail',
              width: 250,
              render: (person) => person.email ?? 'Não informado',
            },
            {
              id: 'tags',
              label: 'Etiquetas',
              width: 220,
              compact: true,
              render: (person) => (
                <span className="directory-tags">
                  {person.tags.map((tag) => (
                    <TagLabel key={tag.id} name={tag.name} color={tag.color} />
                  ))}
                </span>
              ),
            },
          ]}
        />
      </div>
      {preview && (
        <PersonPreview
          person={preview}
          onClose={() => {
            setPreview(undefined);
            trigger.current?.focus();
          }}
        />
      )}
    </SaasLayout>
  );
}
