import type { PageSummary } from '@/components/BusinessList/BusinessList';
import CompanyCatalog from './CompanyCatalog';
import { departmentsConfig, type CatalogRow, type CompanyOption } from './CompanyCatalog.types';

export default function Departments({ departments, filters, companies, creatable }: {
  departments: PageSummary & { data: CatalogRow[] };
  filters: { search: string; status: string; company: string };
  companies: CompanyOption[];
  creatable: CompanyOption[];
}) {
  return <CompanyCatalog config={departmentsConfig} page={departments} filters={filters} companies={companies} creatable={creatable} />;
}
