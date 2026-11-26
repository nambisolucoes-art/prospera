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
    href: '@',
    iconClass: 'ti-home',
  },
];

// Mostra o item quando há listagem; sem ela, leva ao cadastro permitido.
function entry(permissions: string[], module: string, item: SidebarItem, create?: string): SidebarEntry[] {
  if (permissions.includes(`account.${module}.list`)) return [item];
  if (create && permissions.includes(`account.${module}.create`)) return [{ ...item, href: create }];
  return [];
}

// Fora de uma conta (perfil, segurança e ajuda da identidade), o menu leva só à escolha da conta e à ajuda.
const identityEntries: SidebarEntry[] = [
  { key: 'accounts', label: 'Contas', href: '/contas', iconClass: 'ti-briefcase' },
];

export function accountNavigation(
  tenant: boolean,
  permissions: string[] = [],
  workspaceHref: (module: string) => string = (module) => `@/estabelecimento?para=${module}`,
  inAccount = true,
) {
  const shared: SidebarEntry[] = [
    ...(permissions.includes((tenant ? 'account' : 'platform') + '.audit.list')
      ? [
          {
            key: 'audit',
            label: 'Auditoria',
            href: tenant ? '@/auditoria' : '/saas/auditoria',
            iconClass: 'ti-time',
          },
        ]
      : []),
    ...(permissions.includes('self.help.list')
      ? [
          {
            key: 'help',
            label: 'Central de Ajuda',
            href: tenant && inAccount ? '@/ajuda' : '/ajuda',
            iconClass: 'ti-help-alt',
          },
        ]
      : []),
  ];
  if (!tenant) return [...platformEntries, ...shared];
  if (!inAccount) return [...identityEntries, ...shared];
  const records: SidebarEntry[] = [
    ...entry(permissions, 'tags', { key: 'tags', label: 'Etiquetas', href: '@/etiquetas', iconClass: 'ti-tag' }, '@/etiquetas/cadastrar'),
    // Relações comerciais (E07): abrem na área de trabalho, com as empresas das unidades selecionadas.
    ...entry(permissions, 'customers', { key: 'customers', label: 'Clientes', href: workspaceHref('clientes'), iconClass: 'ti-face-smile' }),
    ...entry(permissions, 'suppliers', { key: 'suppliers', label: 'Fornecedores', href: workspaceHref('fornecedores'), iconClass: 'ti-truck' }),
    // Cargos passa ao grupo Controle de acesso na E05, junto com Usuários.
    ...entry(permissions, 'roles', { key: 'roles', label: 'Cargos', href: '@/cargos', iconClass: 'ti-id-badge' }, '@/cargos/cadastrar'),
  ];
  // Módulos de empresa (S03): uma só tela por módulo, com as empresas que o acesso alcança.
  const institutional: SidebarEntry[] = [
    ...(permissions.includes('account.institutional.view')
      ? [{ key: 'institutional', label: 'Perfil institucional', href: '@/institucional', iconClass: 'ti-brush-alt' }]
      : []),
    ...entry(permissions, 'employees', { key: 'employees', label: 'Colaboradores', href: '@/colaboradores', iconClass: 'ti-notepad' }, '@/colaboradores/cadastrar'),
    ...entry(permissions, 'departments', { key: 'departments', label: 'Departamentos', href: '@/departamentos', iconClass: 'ti-layout-accordion-list' }),
    ...entry(permissions, 'job-titles', { key: 'job-titles', label: 'Funções', href: '@/funcoes', iconClass: 'ti-bookmark-alt' }),
  ];
  const organization: SidebarEntry[] = [
    'account.account-data.update',
    'account.companies.list',
    'account.companies.create',
    'account.establishments.list',
  ].some((permission) => permissions.includes(permission))
    ? [{ key: 'organization', label: 'Organização', href: '@/organizacao', iconClass: 'ti-layout-grid2' }]
    : [];
  return [
    ...(permissions.includes('account.dashboard.view') ? accountEntries : []),
    ...(records.length ? [{ key: 'records', title: 'Cadastros' }, ...records] : []),
    ...organization,
    ...(institutional.length ? [{ key: 'institutional', title: 'Institucional' }, ...institutional] : []),
    ...entry(permissions, 'users', { key: 'users', label: 'Usuários', href: '@/usuarios', iconClass: 'ti-user' }, '@/usuarios/cadastrar'),
    ...shared,
  ];
}
