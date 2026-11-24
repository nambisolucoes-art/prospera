import BusinessList, { type BusinessColumn } from '@/components/BusinessList/BusinessList';
import Button from '@/components/Button/Button';
import SaasLayout from './SaasLayout';

type Account = {
  code: string;
  name: string;
  trade_name: string | null;
  roles: string[];
  status: string;
  url: string | null;
};

export default function AccountSelect({ accounts }: { accounts: Account[] }) {
  const columns: BusinessColumn<Account>[] = [
    { id: 'name', label: 'Conta', width: 260, compact: true, compactWidth: 105, render: (account) => account.trade_name || account.name },
    { id: 'roles', label: 'Cargos', width: 220, render: (account) => account.roles.join(', ') || 'Não informado' },
    { id: 'status', label: 'Situação', width: 140, render: (account) => (
      account.status === 'active' ? 'Ativa' : account.status === 'suspended' ? 'Vínculo suspenso' : 'Conta inativa'
    ) },
    { id: 'actions', label: 'Acesso', width: 150, locked: true, compactWidth: 115, render: (account) => (
      account.url ? <Button href={account.url} tone="primary" size="sm">Abrir conta</Button> : 'Indisponível'
    ) },
  ];

  return (
    <SaasLayout tenant title="Selecionar conta">
      <p>Escolha a conta para trabalhar nesta aba. Os acessos e as preferências são próprios de cada conta.</p>
      <div data-tour="account-selection">
        <BusinessList columns={columns} rows={accounts} rowKey={(account) => accounts.indexOf(account)}
          label="Contas disponíveis" preferenceKey="account-selection" empty="Nenhuma conta disponível."
          page={{ total: accounts.length, from: accounts.length ? 1 : null, to: accounts.length || null,
            current_page: 1, last_page: 1, per_page: accounts.length || 10,
            first_page_url: '/contas', last_page_url: '/contas', prev_page_url: null, next_page_url: null }} />
      </div>
    </SaasLayout>
  );
}
