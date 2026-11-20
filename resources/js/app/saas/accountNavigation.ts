import type { SidebarEntry } from '@/components/SidebarPrimary/SidebarPrimary.types';

const platformEntries: SidebarEntry[] = [
  { key: 'saas', title: 'Plataforma SaaS' },
  {
    key: 'saas-clients',
    label: 'Contas',
    href: '/dashboard',
    iconClass: 'ti-briefcase',
  },
  {
    key: 'users',
    label: 'Usuários',
    href: '/saas/usuarios',
    iconClass: 'ti-user',
  },
];

const accountEntries: SidebarEntry[] = [
  {
    key: 'tenant',
    label: 'Minha conta',
    href: '/conta',
    iconClass: 'ti-home',
  },
];

export function accountNavigation(tenant: boolean, permissions: string[] = []) {
  const shared: SidebarEntry[] = [
    ...(permissions.includes((tenant ? 'account' : 'platform') + '.audit.list')
      ? [
          {
            key: 'audit',
            label: 'Auditoria',
            href: tenant ? '/conta/auditoria' : '/saas/auditoria',
            iconClass: 'ti-time',
          },
        ]
      : []),
    ...(permissions.includes('self.help.list')
      ? [
          {
            key: 'help',
            label: 'Central de Ajuda',
            href: '/ajuda',
            iconClass: 'ti-help-alt',
          },
        ]
      : []),
  ];
  if (!tenant) return [...platformEntries, ...shared];
  return [
    ...(permissions.includes('account.dashboard.view') ? accountEntries : []),
    ...(permissions.includes('account.people.list') ||
    permissions.includes('account.people.create')
      ? [
          {
            key: 'people',
            label: 'Pessoas',
            href: permissions.includes('account.people.list')
              ? '/conta/pessoas'
              : '/conta/pessoas/cadastrar',
            iconClass: 'ti-id-badge',
          },
        ]
      : []),
    ...(permissions.includes('account.tags.list') ||
    permissions.includes('account.tags.create')
      ? [
          {
            key: 'tags',
            label: 'Etiquetas',
            href: permissions.includes('account.tags.list')
              ? '/conta/etiquetas'
              : '/conta/etiquetas/cadastrar',
            iconClass: 'ti-tag',
          },
        ]
      : []),
    ...(permissions.includes('account.roles.list') ||
    permissions.includes('account.roles.create')
      ? [
          {
            key: 'roles',
            label: 'Cargos',
            href: permissions.includes('account.roles.list')
              ? '/conta/cargos'
              : '/conta/cargos/cadastrar',
            iconClass: 'ti-id-badge',
          },
        ]
      : []),
    ...shared,
  ];
}
