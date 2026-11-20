import Link from '@/components/ApiLink/ApiLink';
import './DashboardTwoStatCard.css';

interface DashboardTwoStatCardProps {
  iconClass: string;
  title: string;
  value: string;
  href?: string;
}

export default function DashboardTwoStatCard({
  iconClass,
  title,
  value,
  href,
}: DashboardTwoStatCardProps): JSX.Element {
  const content = (
    <>
      <div className="dashboardTwoStatCard__content">
        <div className="dashboardTwoStatCard__value">{value}</div>
        <div className="dashboardTwoStatCard__title">{title}</div>
      </div>
      <span className="dashboardTwoStatCard__icon" aria-hidden="true">
        <i className={iconClass} />
      </span>
    </>
  );

  return href
    ? <Link className="dashboardTwoStatCard" href={href}>{content}</Link>
    : <div className="dashboardTwoStatCard">{content}</div>;
}
