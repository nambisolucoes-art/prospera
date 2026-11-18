import Head from '@/components/PageTitle/PageTitle';
import { useApiPage as usePage } from '@/hooks/useApiPage';
import type { ReactNode } from 'react';
import Theme from '@/app/App';
import type { PageBreadcrumbItem } from '@/components/PageBreadcrumb/PageBreadcrumb';
import PageHeader from '@/components/PageHeader/PageHeader';
import type { SessionProps } from '@/types/Session.types';
import { accountNavigation } from './accountNavigation';
import './SaasLayout.css';

export default function SaasLayout({ title, children, tenant = false, actions, userPage = false, breadcrumb }: {
  title: string; children: ReactNode; tenant?: boolean; actions?: ReactNode; userPage?: boolean; breadcrumb?: PageBreadcrumbItem[];
}) {
  const mayManageUsers = usePage<SessionProps>().props.auth.user?.is_account_admin;
  const navigation = accountNavigation(tenant, mayManageUsers);
  const root = tenant ? { label: 'Minha conta', href: '/conta' } : { label: 'Início', href: '/' };
  const path = breadcrumb ?? (tenant && !userPage ? [] : [{ label: title }]);
  return (
    <Theme navigation={{ entries: navigation, currentKey: userPage ? 'users' : tenant ? 'tenant' : 'saas-clients' }}>
      <Head title={title} />
      <PageHeader title={title} breadcrumbItems={[root, ...path]} breadcrumbPosition="right" actions={actions} />
      <div className="saas-content">{children}</div>
    </Theme>
  );
}
