import { useApiPage as usePage } from '@/hooks/useApiPage';
import BusinessList, { type BusinessColumn, type PageSummary } from '@/components/BusinessList/BusinessList';
import Button from '@/components/Button/Button';
import ProfileAvatar from '@/components/ProfileAvatar/ProfileAvatar';
import type { SessionProps } from '@/types/Session.types';
import SaasLayout from './SaasLayout';
import './UserDirectory.css';

type UserRow = { id: number; name: string; email: string; is_account_admin: boolean; photo_url: string | null };

export default function Users({ users, tenant }: { users: PageSummary & { data: UserRow[] }; tenant: boolean }) {
  const readOnly = usePage<SessionProps>().props.auth.inspection_mode === 'read-only';
  const base = tenant ? '/conta/usuarios' : '/saas/usuarios';
  const columns: BusinessColumn<UserRow>[] = [
    { id: 'name', label: 'Nome', width: 220, compact: true, render: (user) => (
      <span className="user-directory__identity">
        <ProfileAvatar name={user.name} photoUrl={user.photo_url} small lazy />
        <span className="user-directory__name" title={user.name}>{user.name}</span>
      </span>
    ) },
    { id: 'email', label: 'E-mail', width: 255, compact: true, render: (user) => user.email },
    { id: 'role', label: 'Papel', width: 190,
      render: (user) => tenant ? (user.is_account_admin ? 'Administrador da conta' : 'Usuário') : 'Administrador da plataforma' },
  ];

  return (
    <SaasLayout title={tenant ? 'Usuários da conta' : 'Usuários da plataforma'} tenant={tenant} userPage
      actions={!readOnly && <Button href={`${base}/cadastrar`} tone="primary" leftIconClass="fa fa-plus">Cadastrar usuário</Button>}>
      <BusinessList key={base} preferenceKey={base} label="Listagem de usuários" page={users} rows={users.data}
        columns={columns} rowKey={(user) => user.id} empty="Nenhum usuário cadastrado." />
    </SaasLayout>
  );
}
