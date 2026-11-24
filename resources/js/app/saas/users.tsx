import { useRef, useState } from 'react';
import { useContextUrl } from '@/hooks/useContextUrl';
import { useApiPage as usePage } from '@/hooks/useApiPage';
import BusinessList, { type BusinessColumn, type PageSummary } from '@/components/BusinessList/BusinessList';
import BusinessListSearch from '@/components/BusinessList/BusinessListSearch';
import { useBusinessListFilters } from '@/components/BusinessList/useBusinessListFilters';
import Modal from '@/components/Modal/Modal';
import MembershipAssignmentsModal, { type AssignmentSummary } from './MembershipAssignmentsModal';
import { useApiForm } from '@/hooks/useApiForm';
import Button from '@/components/Button/Button';
import TagLabel from '@/components/TagLabel/TagLabel';
import ProfileAvatar from '@/components/ProfileAvatar/ProfileAvatar';
import type { SessionProps } from '@/types/Session.types';
import PreviewSection from './PreviewSection';
import SaasLayout from './SaasLayout';
import './AccountPreview.css';
import './UserDirectory.css';

type UserRow = {
  id: number;
  name: string;
  email: string;
  photo_url: string | null;
  status?: string;
  assignments?: AssignmentSummary[];
  can_view?: boolean;
  can_assign?: boolean;
};

type InvitationRow = { id: number; email: string; role: string | null; expired: boolean; can_revoke: boolean };

export default function Users({ users, tenant, search = '', invitations = [] }: {
  invitations?: InvitationRow[];
  users: PageSummary & { data: UserRow[] };
  tenant: boolean;
  search?: string;
}) {
  const { auth } = usePage<SessionProps>().props;
  const readOnly = auth.inspection_mode === 'read-only';
  const can = (permission: string) => auth.permissions.includes(permission);
  const to = useContextUrl();
  const base = tenant ? to('@/usuarios') : '/saas/usuarios';
  const [assignments, setAssignments] = useState<UserRow>();
  const status = useApiForm({});
  const [preview, setPreview] = useState<UserRow>();
  const trigger = useRef<HTMLElement | null>(null);
  const { query, setQuery, submit } = useBusinessListFilters(base, search);

  function close() {
    setPreview(undefined);
    setAssignments(undefined);
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
          {!readOnly && user.can_assign && (
            <Button iconOnly tone="primary" leftIconClass="fa fa-sitemap" title="Editar atribuições"
              aria-label={`Editar atribuições de ${user.name}`} aria-haspopup="dialog"
              onClick={(event) => { trigger.current = event.currentTarget; setAssignments(user); }} />
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
      {tenant && invitations.length > 0 && (
        <section aria-label="Convites pendentes" data-tour="user-pending-invitations">
          <h2>Convites pendentes</h2>
          {invitations.map((invitation) => (
            <PreviewSection key={invitation.id} title={invitation.email}>
              <dt>Cargo</dt><dd>{invitation.role || 'Não informado'}</dd>
              <dt>Situação</dt><dd>{invitation.expired ? 'Expirado' : 'Aguardando aceite'}</dd>
              {!readOnly && invitation.can_revoke && (
                <Button tone="danger" disabled={status.processing}
                  onClick={() => status.submit('patch', `${base}/convites/${invitation.id}/revogar`)}>
                  Revogar convite
                </Button>
              )}
            </PreviewSection>
          ))}
          {Object.values(status.errors).map((message) => <p key={message} role="alert">{message}</p>)}
        </section>
      )}
      <BusinessList key={base} preferenceKey={base} label="Listagem de usuários" page={users} rows={users.data}
        filters={tenant ? <BusinessListSearch label="Pesquisar usuários" placeholder="Nome ou e-mail de acesso"
          value={query} onChange={setQuery} onSubmit={submit} /> : undefined}
        columns={columns} rowKey={(user) => user.id} empty="Nenhum usuário cadastrado." />
      {assignments && <MembershipAssignmentsModal membership={assignments.id} name={assignments.name} onClose={close} />}
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
          {preview.status === 'active' && <PreviewSection title="Etiquetas">
            <dt>Etiqueta automática</dt>
            <dd><TagLabel name="Usuário" color="#3563e9" /></dd>
          </PreviewSection>}
          {!readOnly && can(preview.status === 'active' ? 'account.users.revoke' : 'account.users.reactivate') && (
            <Button
              tone={preview.status === 'active' ? 'danger' : 'success'}
              leftIconClass={preview.status === 'active' ? 'fa fa-ban' : 'fa fa-check'}
              disabled={status.processing}
              onClick={() => status.submit('patch', `${base}/${preview.id}/${preview.status === 'active' ? 'suspender' : 'reativar'}`, {
                onSuccess: close,
              })}
            >
              {preview.status === 'active' ? 'Suspender acesso' : 'Reativar acesso'}
            </Button>
          )}
          {Object.values(status.errors).map((message) => <p key={message} role="alert">{message}</p>)}
        </Modal>
      )}
    </SaasLayout>
  );
}
