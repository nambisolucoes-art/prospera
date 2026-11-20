export type PermissionRow = {
  id: number;
  name: string;
  slug: string;
  scope: string;
  module: string;
  is_system: boolean;
};
export type RoleRow = {
  id: number;
  name: string;
  system_key: string | null;
  permissions_count: number;
  assignments_count?: number;
  availability?: 'tenant' | 'restricted';
  availability_nodes?: { company_id: number; establishment_id: number | null }[];
  permissions?: PermissionRow[];
  modules?: Record<string, string>;
};
