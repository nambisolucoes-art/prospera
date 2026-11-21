import { useRef, useState } from 'react';
import { useApiPage as usePage } from '@/hooks/useApiPage';
import BusinessList, { type BusinessColumn, type PageSummary } from '@/components/BusinessList/BusinessList';
import Link from '@/components/ApiLink/ApiLink';
import BusinessListSearch from '@/components/BusinessList/BusinessListSearch';
import BusinessForm, { BusinessFormActions } from '@/components/BusinessForm/BusinessForm';
import { useBusinessListFilters } from '@/components/BusinessList/useBusinessListFilters';
import Modal from '@/components/Modal/Modal';
import MembershipAssignmentsModal, { type AssignmentSummary } from './MembershipAssignmentsModal';
import Button from '@/components/Button/Button';
import ProfileAvatar from '@/components/ProfileAvatar/ProfileAvatar';
import type { SessionProps } from '@/types/Session.types';
import SaasLayout from './SaasLayout';
import './UserDirectory.css';

type UserRow = {
  id: number;
  person_id?: number;
  name: string;
  email: string;
  photo_url: string | null;
  status?: string;
  assignments?: AssignmentSummary[];
  can_manage?: boolean;
  password_setup_pending?: boolean;
};
type MembershipAction = 'suspender' | 'reativar' | 'recuperacao' | 'convite';

const labels: Record<MembershipAction, string> = {
  suspender: 'Suspender acesso',
  reativar: 'Reativar acesso',
  recuperacao: 'Enviar link de redefinição de senha',
  convite: 'Reenviar convite',
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
  const [editing, setEditing] = useState<UserRow>();
  const [confirmation, setConfirmation] = useState<{ user: UserRow; action: MembershipAction }>();
  const trigger = useRef<HTMLElement | null>(null);
  const { query, setQuery, submit } = useBusinessListFilters(base, search);

  function close() {
    setEditing(undefined);
    setConfirmation(undefined);
    requestAnimationFrame(() => trigger.current?.focus());
  }

  function actions(user: UserRow): MembershipAction[] {
    const available: MembershipAction[] = [];
    if (user.status === 'active' && can('account.users.revoke')) available.push('suspender');
    if (user.status === 'suspended' && can('account.users.reactivate')) available.push('reativar');
    if (can('account.users.send-password-reset')) available.push('recuperacao');
    if (user.password_setup_pending && can('account.users.invite')) available.push('convite');
    return available;
  }

  const columns: BusinessColumn<UserRow>[] = [
    ...(tenant ? [{
      id: 'actions', label: 'Ações', width: 170, locked: true, render: (user: UserRow) => (
        <div className="business-list__actions" data-tour="user-access-actions">
          {!readOnly && user.can_manage && (
            <Button iconOnly tone="primary" leftIconClass="fa fa-sitemap" aria-label={'Editar atribuições de ' + user.name}
              title="Editar atribuições" aria-haspopup="dialog"
              onClick={(event) => { trigger.current = event.currentTarget; setEditing(user); }} />
          )}
          {!readOnly && actions(user).map((action) => (
            <Button key={action} iconOnly tone="primary" leftIconClass={action === 'suspender' ? 'fa fa-ban'
              : action === 'reativar' ? 'fa fa-check' : action === 'convite' ? 'fa fa-envelope-o' : 'fa fa-key'}
              title={labels[action]} aria-label={labels[action] + ' de ' + user.name} aria-haspopup="dialog"
              onClick={(event) => { trigger.current = event.currentTarget; setConfirmation({ user, action }); }} />
          ))}
        </div>
      ),
    }] : []),
    { id: 'name', label: 'Nome', width: 220, compact: true, render: (user) => (
      <span className="user-directory__identity">
        <ProfileAvatar name={user.name} photoUrl={user.photo_url} small lazy />
        <span className="user-directory__name" title={user.name}>{tenant && can('account.people.update') ? <Link href={`/conta/pessoas/${user.person_id}/editar`}>{user.name}</Link> : user.name}</span>
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
      {editing && <MembershipAssignmentsModal membership={editing.id} name={editing.name} onClose={close} />}
      {confirmation && (
        <Modal open title={labels[confirmation.action]} onClose={close} bootstrap manageFocus>
          <BusinessForm action={base + '/' + confirmation.user.id + '/' + confirmation.action}
            method={confirmation.action === 'suspender' || confirmation.action === 'reativar' ? 'patch' : 'post'}
            onSuccess={close}>
            {({ processing }) => (
              <>
                <p>Confirme a operação para {confirmation.user.name}.</p>
                <BusinessFormActions processing={processing} onCancel={close} />
              </>
            )}
          </BusinessForm>
        </Modal>
      )}
    </SaasLayout>
  );
}
