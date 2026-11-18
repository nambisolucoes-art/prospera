import { pages } from '@/pages/Pages/Pages.data';
import { extras } from '@/pages/Extras/Extras.data';
import { applications } from '@/pages/Applications/Applications.data';
import { layouts } from '@/pages/Layouts/Layouts.data';
import { crmPages } from '@/pages/Crm/Crm.data';
import { ecommercePages } from '@/pages/Ecommerce/Ecommerce.data';
import { staticRoutes } from './routes.data';

export function getCurrentPageKey(pathname: string): string {
  const filename = pathname.split('/').pop() ?? '';
  const configuredPage = pages[filename] ?? extras[filename] ?? applications[filename]
    ?? layouts[filename] ?? crmPages[filename] ?? ecommercePages[filename];
  if (configuredPage) return configuredPage.key;

  const chartRoute = pathname.match(/chart-(flot|morris|chartjs|peity|chartist|c3|nvd3|sparkline|radial|other)\.html$/);
  if (chartRoute) return 'chart-' + chartRoute[1];

  const tableRoute = pathname.match(/tables-(basic|datatable|editable|responsive|foo-tables|bootstrap)\.html$/);
  if (tableRoute) return 'tables-' + tableRoute[1];

  return staticRoutes[filename] ?? 'dashboard-1';
}
