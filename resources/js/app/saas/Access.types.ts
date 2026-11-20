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
  permissions?: PermissionRow[];
  modules?: Record<string, string>;
};
