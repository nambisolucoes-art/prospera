import Link from '@/components/ApiLink/ApiLink';
import './PageBreadcrumb.css';

export interface PageBreadcrumbItem {
  label: string;
  href?: string;
}

interface PageBreadcrumbProps {
  items: PageBreadcrumbItem[];
  navigate?: boolean;
}

export default function PageBreadcrumb({ items, navigate = false }: PageBreadcrumbProps): JSX.Element {
  return (
    <ol className="pageBreadcrumb breadcrumb">
      {items.map((item: PageBreadcrumbItem, index: number) => {
        const isActive = index === items.length - 1;

        return (
          <li key={`${item.label}-${index}`} className={isActive ? 'active' : undefined} aria-current={isActive ? 'page' : undefined}>
            {isActive || !item.href ? (
              item.label
            ) : navigate ? <Link href={item.href}>{item.label}</Link> : (
              <a
                href={item.href}
                onClick={(event: React.MouseEvent<HTMLAnchorElement>) => event.preventDefault()}
              >
                {item.label}
              </a>
            )}
          </li>
        );
      })}
    </ol>
  );
}
