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
  const root = tenant ? { label: 'Início', href: '/conta' } : { label: 'Início', href: '/' };
  const currentKey = sectionFor(window.location.pathname, tenant, userPage);
  const path = breadcrumb ?? (tenant && !userPage ? [] : [{ label: title }]);
  return (
    <Theme navigation={{ entries: navigation, currentKey }}>
      <Head title={title} />
      <div className={headerClassName}><PageHeader title={title} breadcrumbItems={[root, ...path]} breadcrumbPosition="right" actions={actions} /></div>
      <div className="saas-content">{children}</div>
    </Theme>
  );
}

const sections: Array<[string, string]> = [
  ['/conta/cargos', 'roles'],
  ['/conta/pessoas', 'people'],
  ['/conta/etiquetas', 'tags'],
  ['/conta/empresas', 'companies'],
  ['/conta/estabelecimentos', 'establishments'],
  ['/conta/editar', 'account-data'],
];

function sectionFor(path: string, tenant: boolean, userPage: boolean): string {
  if (path.startsWith('/ajuda')) return 'help';
  if (path.includes('/auditoria')) return 'audit';
  const section = sections.find(([prefix]) => path.startsWith(prefix));
  if (section) return section[1];
  if (userPage) return 'users';
  return tenant ? 'home' : 'saas-clients';
}
