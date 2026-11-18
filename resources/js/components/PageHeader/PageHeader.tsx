import './PageHeader.css';
import PageBreadcrumb, { type PageBreadcrumbItem } from '@/components/PageBreadcrumb/PageBreadcrumb';

interface PageHeaderProps {
  title: string;
  subtitle?: string;
  breadcrumbItems?: PageBreadcrumbItem[];
  breadcrumbPosition?: 'below' | 'right';
  actions?: React.ReactNode;
  variant?: 'banner';
}

export default function PageHeader({ title, subtitle, breadcrumbItems, breadcrumbPosition = 'below', actions, variant }: PageHeaderProps): JSX.Element {
  const right = breadcrumbPosition === 'right';
  return (
    <div className={`pageHeader ${variant === 'banner' ? 'pageHeader--banner' : ''} ${right ? 'pageHeader--right' : breadcrumbItems ? 'pageHeader--breadcrumb' : ''}`}>
      <h4 className="pageHeader__title">{title}</h4>
      {right && breadcrumbItems && (
        <nav className="pageHeader__breadcrumb" aria-label="Localização">
          <PageBreadcrumb items={breadcrumbItems} navigate />
        </nav>
      )}
      {subtitle !== undefined ? <p className="pageHeader__subtitle">{subtitle}</p> : null}
      {!right && breadcrumbItems ? <PageBreadcrumb items={breadcrumbItems} /> : null}
      {actions && <div className="pageHeader__actions">{actions}</div>}
    </div>
  );
}
