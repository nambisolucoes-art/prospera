import { useRef, useState } from 'react';
import { useApiPage as usePage } from '@/hooks/useApiPage';
import BusinessList, { type BusinessColumn, type PageSummary } from '@/components/BusinessList/BusinessList';
import BusinessListSearch from '@/components/BusinessList/BusinessListSearch';
import { useBusinessListFilters } from '@/components/BusinessList/useBusinessListFilters';
import Modal from '@/components/Modal/Modal';
import type { AssignmentSummary } from './MembershipAssignmentsModal';
import Button from '@/components/Button/Button';
import ProfileAvatar from '@/components/ProfileAvatar/ProfileAvatar';
import type { SessionProps } from '@/types/Session.types';
import PreviewSection from './PreviewSection';
import SaasLayout from './SaasLayout';
import './AccountPreview.css';
import './UserDirectory.css';

type UserRow = {
  id: number;
  person_id?: number;
  name: string;
  email: string;
  photo_url: string | null;
  status?: string;
  assignments?: AssignmentSummary[];
  can_view?: boolean;
};

export default function Users({ users, tenant, search = '' }: {
  users: PageSummary & { data: UserRow[] };
  tenant: boolean;
  search?: string;
}) {
  const { auth } = usePage<SessionProps>().props;
  const readOnly = auth.inspection_mode === 'read-only';
  const can = (permission: string) => auth.permissions.includes(permission);
  const base = tenant ? '/conta/usuarios' : '/saas/usuarios';
  const [preview, setPreview] = useState<UserRow>();
  const trigger = useRef<HTMLElement | null>(null);
  const { query, setQuery, submit } = useBusinessListFilters(base, search);

  function close() {
    setPreview(undefined);
    requestAnimationFrame(() => trigger.current?.focus());
  }

  const columns: BusinessColumn<UserRow>[] = [
    ...(tenant ? [{
      id: 'actions', label: 'Ações', width: 92, compactWidth: 78, locked: true, render: (user: UserRow) => (
        <div className="business-list__actions" data-tour="user-access-actions">
          {user.can_view && (
            <Button iconOnly tone="primary" leftIconClass="fa fa-info" title="Visualizar usuário"
              aria-label={`Visualizar usuário ${user.name}`} aria-haspopup="dialog"
              onClick={(event) => { trigger.current = event.currentTarget; setPreview(user); }} />
          )}
          {!readOnly && user.person_id && can('account.people.update') && (
            <Button href={`/conta/pessoas/${user.person_id}/editar`} iconOnly tone="primary" leftIconClass="fa fa-pencil"
              title="Editar pessoa" aria-label={`Editar pessoa ${user.name}`} />
          )}
        </div>
      ),
    }] : []),
    { id: 'name', label: 'Nome', width: 220, compact: true, render: (user) => (
      <span className="user-directory__identity">
        <ProfileAvatar name={user.name} photoUrl={user.photo_url} small lazy />
        <span className="user-directory__name" title={user.name}>{user.name}</span>
      </span>
    ) },
    { id: 'email', label: 'E-mail de acesso', width: 255, compact: true, render: (user) => user.email },
    { id: 'role', label: tenant ? 'Atribuições' : 'Cargo', width: 260,
      render: (user) => tenant ? (user.assignments?.length ? user.assignments.map((item) => (
        <div key={item.id} title={item.coverage}>{item.role} ({item.location})</div>
      )) : 'Sem atribuição') : 'Administrador da plataforma' },
    ...(tenant ? [{ id: 'status', label: 'Acesso à conta', width: 140,
      render: (user: UserRow) => user.status === 'active' ? 'Ativo' : 'Suspenso' }] : []),
  ];

  return (
    <SaasLayout title={tenant ? 'Usuários da conta' : 'Usuários da plataforma'} tenant={tenant} userPage
      actions={!readOnly && can(tenant ? 'account.users.create' : 'platform.users.create') && <Button href={`${base}/cadastrar`} tone="primary" labelIconClass="fa fa-plus" aria-label="Cadastrar usuário">Cadastrar</Button>}>
      <BusinessList key={base} preferenceKey={base} label="Listagem de usuários" page={users} rows={users.data}
        filters={tenant ? <BusinessListSearch label="Pesquisar usuários" placeholder="Nome ou e-mail de acesso"
          value={query} onChange={setQuery} onSubmit={submit} /> : undefined}
        columns={columns} rowKey={(user) => user.id} empty="Nenhum usuário cadastrado." />
      {preview && (
        <Modal open title={preview.name} onClose={close} size="default" contentClassName="account-preview"
          bootstrap manageFocus>
          <PreviewSection title="Dados do usuário">
            <dt>Nome</dt>
            <dd>{preview.name}</dd>
            <dt>E-mail de acesso</dt>
            <dd>{preview.email}</dd>
            <dt>Acesso à conta</dt>
            <dd>{preview.status === 'active' ? 'Ativo' : 'Suspenso'}</dd>
            <dt>Atribuições</dt>
            <dd>
              {preview.assignments?.length
                ? preview.assignments.map((assignment) => (
                  <div key={assignment.id}>{assignment.role} ({assignment.location})</div>
                ))
                : 'Sem atribuição'}
            </dd>
          </PreviewSection>
        </Modal>
      )}
    </SaasLayout>
  );
}
