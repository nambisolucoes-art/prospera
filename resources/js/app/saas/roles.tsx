import AccessList from './AccessList';
import type { RoleRow } from './Access.types';
import type { PageSummary } from '@/components/BusinessList/BusinessList';
export default function Roles({
  roles,
  filters,
}: {
  roles: PageSummary & { data: RoleRow[] };
  filters: { search: string; origin: string };
}) {
  return <AccessList rows={roles} filters={filters} />;
}
