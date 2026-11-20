import AccessList from './AccessList';
import type { RoleRow } from './Access.types';
import type { PageSummary } from '@/components/BusinessList/BusinessList';
export default function Roles({
  roles,
}: {
  roles: PageSummary & { data: RoleRow[] };
}) {
  return <AccessList rows={roles} />;
}
