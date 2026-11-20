import Link from '@/components/ApiLink/ApiLink';
import Button from '@/components/Button/Button';
import DashboardTwoStatCard from '@/components/DashboardTwoStatCard/DashboardTwoStatCard';
import Label from '@/components/Label/Label';
import { useApiPage } from '@/hooks/useApiPage';
import type { SessionProps } from '@/types/Session.types';
import SaasLayout from './SaasLayout';
import './Organization.css';
type Organization = {
  companies: number | null;
  establishments: number | null;
  incomplete: { id: number; name: string }[] | null;
};
export default function Home({ account, organization }: { account: { name: string }; organization: Organization }) {
  const { auth } = useApiPage<SessionProps>().props;
  const writable = auth.inspection_mode !== 'read-only';
  const can = (permission: string) => auth.permissions.includes(permission);
  const editAccount = writable && can('account.account-data.update');
  const editCompanies = writable && can('account.companies.update');
  return (
    <SaasLayout
      tenant
      title="Início"
      actions={editAccount && (
        <Button href="/conta/organizacao?nivel=conta" tone="primary" leftIconClass="fa fa-sitemap">Organização</Button>
      )}
    >
      {!!organization.incomplete?.length && (
        <aside className="organization-home__pending" aria-label="Pendência fiscal" data-tour="home-pending">
          <span className="organization-home__pending-icon" aria-hidden="true">
            <i className="fa fa-exclamation-triangle" />
          </span>
          <div className="organization-home__pending-content">
            <Label variant="warning">Pendência fiscal</Label>
            <span>Informe o CNPJ ou CPF no cadastro.</span>
            <ul>
              {organization.incomplete.map((company) => (
                <li key={company.id}>
                  {editCompanies ? <Link href={`/conta/empresas/${company.id}/editar?origem=organizacao`}>{company.name}</Link> : company.name}
                </li>
              ))}
            </ul>
          </div>
        </aside>
      )}
      <div className="organization-home" data-tour="home-organization">
        <DashboardTwoStatCard
          value={account.name}
          title="Dados da conta"
          iconClass="md md-assignment text-custom"
          href={editAccount ? '/conta/organizacao?nivel=conta' : undefined}
        />
        {organization.companies !== null && (
          <DashboardTwoStatCard
            value={String(organization.companies)}
            title="Empresas"
            iconClass="md md-business text-primary"
            href="/conta/organizacao"
          />
        )}
        {organization.establishments !== null && (
          <DashboardTwoStatCard
            value={String(organization.establishments)}
            title="Estabelecimentos"
            iconClass="md md-store-mall-directory text-pink"
            href="/conta/organizacao?nivel=estabelecimentos"
          />
        )}
      </div>
    </SaasLayout>
  );
}
