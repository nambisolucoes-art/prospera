import TagLabel from '@/components/TagLabel/TagLabel';
import { useEffect, useRef, useState } from 'react';
import BusinessList, {
  type PageSummary,
} from '@/components/BusinessList/BusinessList';
import BusinessListSearch from '@/components/BusinessList/BusinessListSearch';
import { useBusinessListFilters } from '@/components/BusinessList/useBusinessListFilters';
import SelectControl from '@/components/SelectControl/SelectControl';
import Button from '@/components/Button/Button';
import Modal, { type ModalSize } from '@/components/Modal/Modal';
import { useApiPage } from '@/hooks/useApiPage';
import type { SessionProps } from '@/types/Session.types';
import SaasLayout from './SaasLayout';
import type { PermissionRow, RoleRow } from './Access.types';
import type { TagRow } from './Directory.types';
import PreviewSection from './PreviewSection';
import './AccountPreview.css';
import './Access.css';
export default function AccessList({
  rows,
  filters,
}: {
  rows: PageSummary & { data: RoleRow[] };
  filters: { search: string; origin: string };
}) {
  const { auth } = useApiPage<SessionProps>().props;
  const base = '/conta/cargos';
  const title = 'Cargos';
  const [preview, setPreview] = useState<{ id: number; name: string }>();
  const trigger = useRef<HTMLElement | null>(null);
  const writable = auth.inspection_mode !== 'read-only';
  const can = (action: string) =>
    auth.permissions.includes(`account.roles.${action}`);
  const { query, setQuery, submit, apply } = useBusinessListFilters(base, filters.search);
  return (
    <SaasLayout
      tenant
      title={title}
      headerClassName="access-header"
      actions={
        writable &&
        can('create') && (
          <Button
            href={base + '/cadastrar'}
            tone="primary"
            labelIconClass="fa fa-plus"
            aria-label="Cadastrar cargo"
          >
            Cadastrar
          </Button>
        )
      }
    >
      <div data-tour="roles-list">
        <BusinessList
          label={title}
          preferenceKey="roles"
          page={rows}
          rows={rows.data}
          rowKey={(row) => row.id}
          empty={filters.search || filters.origin ? 'Nenhum cargo encontrado.' : 'Nenhum cargo cadastrado.'}
          filters={<>
            <BusinessListSearch tour="roles-search" label="Buscar cargos" placeholder="Buscar por nome"
              value={query} onChange={setQuery} onSubmit={submit} maxLength={100} />
            <div data-tour="roles-origin">
              <SelectControl id="roles-origin-filter" label="Origem" value={filters.origin}
                onChange={(value) => apply({ origin: value })}
                options={[{ value: '', label: 'Todas as origens' }, { value: 'system', label: 'Cargo inicial' }, { value: 'custom', label: 'Personalizado' }]} />
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
                      aria-label={`Visualizar ${row.name}`}
                      onClick={(event) => {
                        trigger.current = event.currentTarget;
                        setPreview(row);
                      }}
                    />
                  )}
                  {writable &&
                    can('update') &&
                    !(
                      'system_key' in row && row.system_key === 'administrator'
                    ) && (
                      <Button
                        href={`${base}/${row.id}/editar`}
                        iconOnly
                        tone="primary"
                        leftIconClass="fa fa-pencil"
                        title="Editar cadastro"
                        aria-label={`Editar ${row.name}`}
                      />
                    )}
                </div>
              ),
            },
            {
              id: 'name',
              label: 'Nome',
              width: 280,
              compact: true,
              render: (row) => <strong title={row.name}>{row.name}</strong>,
            },
            {
              id: 'detail',
              label: 'Permissões',
              width: 160,
              compact: true,
              render: (row) => row.permissions_count,
            },
            {
              id: 'availability',
              label: 'Disponibilidade',
              width: 145,
              render: (row) => row.availability === 'restricted' ? 'Restrita' : 'Toda a conta',
            },
            {
              id: 'assignments',
              label: 'Atribuições',
              width: 110,
              render: (row) => row.assignments_count ?? 0,
            },
            {
              id: 'origin',
              label: 'Origem',
              width: 150,
              render: (row) =>
                row.system_key ? 'Cargo inicial' : 'Personalizado',
            },
          ]}
        />
      </div>
      {preview && (
        <AccessPreview
          base={base}
          row={preview}
          onClose={() => {
            setPreview(undefined);
            requestAnimationFrame(() => trigger.current?.focus());
          }}
        />
      )}
    </SaasLayout>
  );
}
export function AccessPreview({
  base,
  row,
  size = 'large',
  onClose,
}: {
  base: string;
  size?: ModalSize;
  row: { id: number; name: string };
  onClose: () => void;
}) {
  const [record, setRecord] = useState<RoleRow | TagRow>();
  const [error, setError] = useState(false);
  useEffect(() => {
    const controller = new AbortController();
    fetch(`${base}/${row.id}/visualizar`, {
      signal: controller.signal,
      headers: { Accept: 'application/json' },
    })
      .then(async (response) => {
        if (!response.ok) throw new Error();
        setRecord((await response.json()) as RoleRow | TagRow);
      })
      .catch(() => {
        if (!controller.signal.aborted) setError(true);
      });
    return () => controller.abort();
  }, [base, row.id]);
  return (
    <Modal
      open
      title={record?.name ?? row.name}
      onClose={onClose}
      size={size}
      bootstrap
      manageFocus
      contentClassName="account-preview"
    >
      {!record ? (
        <p role={error ? 'alert' : 'status'}>
          {error ? 'Não foi possível abrir o cadastro.' : 'Carregando…'}
        </p>
      ) : (
        <>
          <PreviewSection
            title={
              'permissions' in record ? 'Dados do cargo' : 'Dados do cadastro'
            }
          >
            <dt>Nome</dt>
            <dd>
              {'color' in record ? (
                <TagLabel name={record.name} color={record.color} />
              ) : (
                record.name
              )}
            </dd>
            {'active' in record && <>
              <dt>Situação</dt>
              <dd>{record.active ? 'Ativa' : 'Inativa'}</dd>
            </>}
            {'permissions' in record && (
              <>
                <dt>Origem</dt>
                <dd>{record.system_key ? 'Cargo inicial' : 'Personalizado'}</dd>
                <dt>Permissões</dt>
                <dd>{record.permissions?.length ?? 0}</dd>
              </>
            )}
          </PreviewSection>
          {'permissions' in record && (
            <PreviewSection title="Permissões por módulo">
              {record.permissions?.length ? (
                Object.entries(
                  record.permissions.reduce<Record<string, PermissionRow[]>>(
                    (groups, permission) => {
                      (groups[permission.module] ??= []).push(permission);
                      return groups;
                    },
                    {},
                  ),
                ).map(([module, permissions]) => (
                  <PermissionGroup
                    key={module}
                    label={record.modules?.[module] ?? module}
                    permissions={permissions}
                  />
                ))
              ) : (
                <>
                  <dt>Permissões</dt>
                  <dd>Nenhuma permissão atribuída.</dd>
                </>
              )}
            </PreviewSection>
          )}
        </>
      )}
    </Modal>
  );
}

function PermissionGroup({
  label,
  permissions,
}: {
  label: string;
  permissions: PermissionRow[];
}) {
  return (
    <>
      <dt>{label}</dt>
      <dd>
        {permissions.map((permission) => (
          <div className="access-preview__permission" key={permission.id}>
            {permission.name}
          </div>
        ))}
      </dd>
    </>
  );
}
