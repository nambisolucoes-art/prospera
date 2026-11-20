import type { SidebarEntry, SidebarItem } from '@/components/SidebarPrimary/SidebarPrimary.types';

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
    key: 'home',
    label: 'Início',
    href: '/conta',
    iconClass: 'ti-home',
  },
];

// Mostra o item quando há listagem; sem ela, leva ao cadastro permitido.
function entry(permissions: string[], module: string, item: SidebarItem, create?: string): SidebarEntry[] {
  if (permissions.includes(`account.${module}.list`)) return [item];
  if (create && permissions.includes(`account.${module}.create`)) return [{ ...item, href: create }];
  return [];
}

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
  const records: SidebarEntry[] = [
    ...entry(permissions, 'people', { key: 'people', label: 'Pessoas', href: '/conta/pessoas', iconClass: 'ti-id-badge' }, '/conta/pessoas/cadastrar'),
    ...entry(permissions, 'tags', { key: 'tags', label: 'Etiquetas', href: '/conta/etiquetas', iconClass: 'ti-tag' }, '/conta/etiquetas/cadastrar'),
    // Cargos passa ao grupo Controle de acesso na E05, junto com Usuários.
    ...entry(permissions, 'roles', { key: 'roles', label: 'Cargos', href: '/conta/cargos', iconClass: 'ti-id-badge' }, '/conta/cargos/cadastrar'),
  ];
  const organization: SidebarEntry[] = [
    'account.account-data.update',
    'account.companies.list',
    'account.companies.create',
    'account.establishments.list',
  ].some((permission) => permissions.includes(permission))
    ? [{ key: 'organization', label: 'Organização', href: '/conta/organizacao', iconClass: 'ti-layout-grid2' }]
    : [];
  return [
    ...(permissions.includes('account.dashboard.view') ? accountEntries : []),
    ...(records.length ? [{ key: 'records', title: 'Cadastros' }, ...records] : []),
    ...organization,
    ...entry(permissions, 'users', { key: 'users', label: 'Usuários', href: '/conta/usuarios', iconClass: 'ti-user' }, '/conta/usuarios/cadastrar'),
    ...shared,
  ];
}
