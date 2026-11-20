import Head from '@/components/PageTitle/PageTitle';
import { useApiPage as usePage } from '@/hooks/useApiPage';
import type { ReactNode } from 'react';
import Theme from '@/app/App';
import type { PageBreadcrumbItem } from '@/components/PageBreadcrumb/PageBreadcrumb';
import PageHeader from '@/components/PageHeader/PageHeader';
import type { SessionProps } from '@/types/Session.types';
import { accountNavigation } from './accountNavigation';
import './SaasLayout.css';

export default function SaasLayout({ title, children, tenant = false, actions, userPage = false, breadcrumb, headerClassName }: {
  title: string; children: ReactNode; tenant?: boolean; actions?: ReactNode; userPage?: boolean; breadcrumb?: PageBreadcrumbItem[]; headerClassName?: string;
}) {
  const permissions = usePage<SessionProps>().props.auth.permissions;
  const navigation = accountNavigation(tenant, permissions);
  const root = tenant ? { label: 'Minha conta', href: '/conta' } : { label: 'Início', href: '/' };
  const path = breadcrumb ?? (tenant && !userPage ? [] : [{ label: title }]);
  return (
    <Theme navigation={{ entries: navigation, currentKey: window.location.pathname.startsWith('/ajuda') ? 'help' : window.location.pathname.includes('/auditoria') ? 'audit' : window.location.pathname.startsWith('/conta/cargos') ? 'roles' : window.location.pathname.startsWith('/conta/pessoas') ? 'people' : window.location.pathname.startsWith('/conta/etiquetas') ? 'tags' : userPage ? 'users' : tenant ? 'tenant' : 'saas-clients' }}>
      <Head title={title} />
      <div className={headerClassName}><PageHeader title={title} breadcrumbItems={[root, ...path]} breadcrumbPosition="right" actions={actions} /></div>
      <div className="saas-content">{children}</div>
    </Theme>
  );
}
