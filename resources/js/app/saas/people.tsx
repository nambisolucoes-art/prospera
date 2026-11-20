import { useEffect, useRef, useState } from 'react';
import BusinessList, {
  type PageSummary,
} from '@/components/BusinessList/BusinessList';
import Button from '@/components/Button/Button';
import TagLabel from '@/components/TagLabel/TagLabel';
import InputGroup from '@/components/InputGroup/InputGroup';
import SelectControl from '@/components/SelectControl/SelectControl';
import TextInput from '@/components/TextInput/TextInput';
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
  const { navigate, props } = useApiPage<SessionProps>();
  const readOnly = props.auth.inspection_mode === 'read-only';
  const [query, setQuery] = useState(search);
  const [preview, setPreview] = useState<PersonRow>();
  const trigger = useRef<HTMLElement | null>(null);
  const navigateRef = useRef(navigate);
  navigateRef.current = navigate;
  const submittedSearch = useRef(search);
  const searchTimer = useRef<number | undefined>(undefined);
  useEffect(() => {
    if (search !== submittedSearch.current) {
      submittedSearch.current = search;
      setQuery(search);
    }
  }, [search]);
  useEffect(() => {
    if (query === search) return;
    const timer = window.setTimeout(() => {
      const params = new URLSearchParams();
      if (suppliersOnly) params.set('suppliers', '1');
    if (customersOnly) params.set('customers', '1');
      if (query) params.set('search', query);
      if (people.per_page !== 25)
        params.set('per_page', String(people.per_page));
      submittedSearch.current = query;
      void navigateRef.current(
        '/conta/pessoas' + (params.size ? '?' + params : ''),
        { preserveScroll: true },
      );
    }, 350);
    searchTimer.current = timer;
    return () => window.clearTimeout(timer);
  }, [query, search, people.per_page, suppliersOnly, customersOnly]);
  function searchNow() {
    window.clearTimeout(searchTimer.current);
    const params = new URLSearchParams();
    if (suppliersOnly) params.set('suppliers', '1');
    if (customersOnly) params.set('customers', '1');
    if (query) params.set('search', query);
    if (people.per_page !== 25) params.set('per_page', String(people.per_page));
    submittedSearch.current = query;
    void navigate('/conta/pessoas' + (params.size ? '?' + params : ''), {
      preserveScroll: true,
    });
  }
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
            leftIconClass="fa fa-plus"
          >
            Cadastrar pessoa
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
            <form
              className="saas-search"
              role="search"
              data-tour="people-search"
              onSubmit={(event) => {
                event.preventDefault();
                searchNow();
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
                  aria-label="Buscar pessoas"
                  placeholder="Buscar por nome ou e-mail"
                  value={query}
                  maxLength={255}
                  onChange={(event) => setQuery(event.target.value)}
                />
              </InputGroup>
            </form>
            {(props.auth.permissions.includes('account.suppliers.list') || props.auth.permissions.includes('account.customers.list')) && (
              <div data-tour="people-suppliers"><SelectControl id="people-suppliers" label="Tipo de cadastro" value={customersOnly ? 'customers' : suppliersOnly ? 'suppliers' : ''}
                options={[{ value: '', label: 'Todas as pessoas' },
                  ...(props.auth.permissions.includes('account.customers.list') ? [{ value: 'customers', label: 'Clientes' }] : []),
                  ...(props.auth.permissions.includes('account.suppliers.list') ? [{ value: 'suppliers', label: 'Fornecedores' }] : []),
                ]}
                onChange={(value) => {
                  const params = new URLSearchParams();
                  if (query) params.set('search', query);
                  if (value === 'customers') params.set('customers', '1');
                  if (value === 'suppliers') params.set('suppliers', '1');
                  window.clearTimeout(searchTimer.current);
                  void navigate('/conta/pessoas' + (params.size ? '?' + params : ''), { preserveScroll: true });
                }} /></div>
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
