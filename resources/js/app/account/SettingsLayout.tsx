import Head from '@/components/PageTitle/PageTitle';
import { useApiPage as usePage } from '@/hooks/useApiPage';
import { createContext, type ReactNode } from 'react';
import Theme from '@/app/App';
import { accountNavigation } from '@/app/saas/accountNavigation';
import type { SidebarState } from '@/app/useSidebarState';
import CardBox from '@/components/CardBox/CardBox';
import PageHeader from '@/components/PageHeader/PageHeader';
import PageTabs from '@/components/PageTabs/PageTabs';
import type { SessionProps } from '@/types/Session.types';
import ProfileIdentity from './ProfileIdentity';
import './SettingsLayout.css';

const tabs = [
  { href: '/settings/profile', component: 'settings/profile', label: 'Perfil' },
  { href: '/settings/security', component: 'settings/security', label: 'Segurança' },
  { href: '/settings/appearance', component: 'settings/appearance', label: 'Aparência' },
] as const;

export const SettingsSidebarContext = createContext<SidebarState | null>(null);

export default function SettingsLayout({ children }: { children: ReactNode }) {
  const { component, props: { auth } } = usePage<SessionProps>();
  const category = component === 'settings/confirm-security' ? 'settings/security' : component;
  const current = tabs.find((tab) => tab.component === category) ?? tabs[0];
  const tenant = !auth.user?.is_saas_admin;
  // Configurações pessoais pertencem à identidade e ficam fora de qualquer conta (E09).
  const entries = accountNavigation(tenant, auth.permissions, undefined, false);
  const root = tenant ? { label: 'Início', href: '/contas' } : { label: 'Início', href: '/dashboard' };

  return (
    <Theme navigation={{ entries }}>{(sidebar) => (
      <SettingsSidebarContext.Provider value={sidebar}>
        <Head title={current.label} />
        <PageHeader title={current.label} breadcrumbPosition="right"
          breadcrumbItems={[root, { label: 'Minha conta' }, { label: current.label }]} />
        <ProfileIdentity editable={component === 'settings/profile' && !auth.inspection_mode} />
        <PageTabs items={auth.inspection_mode ? tabs.filter((tab) => tab.component !== 'settings/security') : tabs}
          activeHref={current.href} label="Configurações" tourTarget="settings-tabs" />
        <CardBox className="settings-card">{children}</CardBox>
      </SettingsSidebarContext.Provider>
    )}</Theme>
  );
}
