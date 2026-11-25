import type { PageSummary } from '@/components/BusinessList/BusinessList';
import CompanyCatalog from './CompanyCatalog';
import { jobTitlesConfig, type CatalogRow, type CompanyOption } from './CompanyCatalog.types';

export default function JobTitles({ job_titles, filters, companies, creatable }: {
  job_titles: PageSummary & { data: CatalogRow[] };
  filters: { search: string; status: string; company: string };
  companies: CompanyOption[];
  creatable: CompanyOption[];
}) {
  return <CompanyCatalog config={jobTitlesConfig} page={job_titles} filters={filters} companies={companies} creatable={creatable} />;
}
