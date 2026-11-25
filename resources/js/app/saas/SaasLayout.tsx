import Head from '@/components/PageTitle/PageTitle';
import { useApiPage as usePage } from '@/hooks/useApiPage';
import type { ReactNode } from 'react';
import Theme from '@/app/App';
import type { PageBreadcrumbItem } from '@/components/PageBreadcrumb/PageBreadcrumb';
import PageHeader from '@/components/PageHeader/PageHeader';
import type { SessionProps } from '@/types/Session.types';
import type { SidebarEntry } from '@/components/SidebarPrimary/SidebarPrimary.types';
import { accountBase, contextualUrl, readTabWorkspace, screenPath, workspaceModuleUrl, workspaceStorageKey } from '@/utils/organizationContext';
import { accountNavigation } from './accountNavigation';
import './SaasLayout.css';

export default function SaasLayout({ title, children, tenant = false, actions, userPage = false, breadcrumb, headerClassName }: {
  title: string; children: ReactNode; tenant?: boolean; actions?: ReactNode; userPage?: boolean; breadcrumb?: PageBreadcrumbItem[]; headerClassName?: string;
}) {
  const { props, url } = usePage<SessionProps>();
  const permissions = props.auth.permissions;
  const context = props.organization_context;
  // A conta vem da URL desta aba (E09); sem conta, o menu só oferece a escolha da conta e a ajuda.
  const inAccount = accountBase(url) !== null;
  const tabCodes = context ? readTabWorkspace(workspaceStorageKey(context.account_id, props.auth.user?.id, props.auth.inspection_mode)) : [];
  const navigation = resolveEntries(
    accountNavigation(tenant, permissions, (module) => workspaceModuleUrl(context, url, tabCodes, module), inAccount),
    url,
  );
  const root = !tenant ? { label: 'Início', href: '/' } : inAccount ? { label: 'Início', href: contextualUrl('@', url) } : { label: 'Início', href: '/contas' };
  const currentKey = sectionFor(screenPath(window.location.pathname), tenant, userPage);
  const path = (breadcrumb ?? (tenant && !userPage ? [] : [{ label: title }]))
    .map((item) => (item.href ? { ...item, href: contextualUrl(item.href, url) } : item));
  return (
    <Theme navigation={{ entries: navigation, currentKey }}>
      <Head title={title} />
      <div className={headerClassName}><PageHeader title={title} breadcrumbItems={[root, ...path]} breadcrumbPosition="right" actions={actions} /></div>
      <div className="saas-content">{children}</div>
    </Theme>
  );
}

/** Os itens do menu usam @/ e ~/; o endereço final segue a conta e a unidade desta aba. */
export function resolveEntries(entries: SidebarEntry[], url: string): SidebarEntry[] {
  return entries.map((entry) => ('href' in entry && entry.href ? { ...entry, href: contextualUrl(entry.href, url) } : entry));
}

const sections: Array<[string, string]> = [
  ['/conta/cargos', 'roles'],
  ['/conta/etiquetas', 'tags'],
  ['/conta/organizacao', 'organization'],
  ['/conta/empresas', 'organization'],
  ['/conta/estabelecimentos', 'organization'],
  ['/conta/editar', 'organization'],
];

function sectionFor(path: string, tenant: boolean, userPage: boolean): string {
  if (path.startsWith('/ajuda') || path.startsWith('/conta/ajuda')) return 'help';
  if (path === '/contas') return 'accounts';
  if (/^\/conta\/estabelecimento\/[^/]+\/clientes(?:\/cadastrar)?$/.test(path)) return 'customers';
  if (/^\/conta\/estabelecimento\/[^/]+\/fornecedores(?:\/cadastrar)?$/.test(path)) return 'suppliers';
  if (path === '/conta/departamentos') return 'departments';
  if (path === '/conta/funcoes') return 'job-titles';
  if (/^\/conta\/colaboradores(?:\/|$)/.test(path)) return 'employees';
  if (path.includes('/auditoria')) return 'audit';
  const section = sections.find(([prefix]) => path.startsWith(prefix));
  if (section) return section[1];
  if (userPage) return 'users';
  return tenant ? 'home' : 'saas-clients';
}
