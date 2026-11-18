import type { SidebarEntry } from '@/components/SidebarPrimary/SidebarPrimary.types';

const platformEntries: SidebarEntry[] = [
  { key: 'saas', title: 'Plataforma SaaS' },
  { key: 'saas-clients', label: 'Contas', href: '/dashboard', iconClass: 'ti-briefcase' },
  { key: 'users', label: 'Usuários', href: '/saas/usuarios', iconClass: 'ti-user' },
];

const accountEntries: SidebarEntry[] = [
  { key: 'tenant', label: 'Minha conta', href: '/conta', iconClass: 'ti-home' },
];

const accountAdminEntries: SidebarEntry[] = [
  ...accountEntries,
  { key: 'users', label: 'Usuários', href: '/conta/usuarios', iconClass: 'ti-user' },
];

export function accountNavigation(tenant: boolean, mayManageUsers = false) {
  if (!tenant) return platformEntries;
  return mayManageUsers ? accountAdminEntries : accountEntries;
}
