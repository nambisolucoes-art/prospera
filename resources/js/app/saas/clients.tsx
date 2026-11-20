import Form from '@/components/ApiForm/ApiForm';
import Link from '@/components/ApiLink/ApiLink';
import { useRef, useState } from 'react';
import Button from '@/components/Button/Button';
import BusinessList, { type BusinessColumn, type PageSummary } from '@/components/BusinessList/BusinessList';
import InputGroup from '@/components/InputGroup/InputGroup';
import StatusLabel from '@/components/StatusLabel/StatusLabel';
import TextInput from '@/components/TextInput/TextInput';
import AccountPreview from './AccountPreview';
import AccountInspection from './AccountInspection';
import { formatMasked } from './ClientFormFields';
import SaasLayout from './SaasLayout';

type AccountRow = {
  id: number; name: string; document: string | null; person_type: string | null;
  email: string | null; active: boolean; plan: string | null; users_count: number;
};

export default function Accounts({ accounts, search }: { accounts: PageSummary & { data: AccountRow[] }; search: string }) {
  const [preview, setPreview] = useState<{ id: number; name: string }>();
  const [inspection, setInspection] = useState<{ id: number; name: string }>();
  const trigger = useRef<HTMLElement | null>(null);
  const columns: BusinessColumn<AccountRow>[] = [
    { id: 'actions', label: 'Ações', width: 132, compactWidth: 108, locked: true, render: (account) => (
      <div className="business-list__actions account-directory__actions">
        <Button iconOnly tone="primary" leftIconClass="fa fa-info" title="Visualizar cadastro"
          aria-label={`Visualizar conta ${account.name}`} aria-haspopup="dialog"
          onClick={(event) => { trigger.current = event.currentTarget; setPreview({ id: account.id, name: account.name }); }} />
        <Button href={`/saas/contas/${account.id}/editar`} iconOnly tone="primary" leftIconClass="fa fa-pencil"
          title="Editar cadastro" aria-label={`Editar conta ${account.name}`} />
        <Button iconOnly tone="inverse" leftIconClass="fa fa-user-secret" title="Inspecionar conta"
          aria-label={`Inspecionar conta ${account.name}`} aria-haspopup="dialog"
          disabled={!account.active || account.users_count === 0}
          onClick={(event) => { trigger.current = event.currentTarget; setInspection({ id: account.id, name: account.name }); }} />
      </div>
    ) },
    { id: 'name', label: 'Conta', width: 210, compact: true, render: (account) => <strong title={account.name}>{account.name}</strong> },
    { id: 'email', label: 'E-mail', width: 220,
      render: (account) => account.email ? <span title={account.email}>{account.email}</span> : 'Não informado' },
    { id: 'document', label: 'CPF/CNPJ', width: 160,
      render: (account) => formatMasked(account.document ?? '', account.person_type === 'company' ? 'cnpj' : 'cpf') || 'Não informado' },
    { id: 'plan', label: 'Plano', width: 130, render: (account) => account.plan || 'Não informado' },
    { id: 'status', label: 'Status', width: 110, compact: true,
      render: (account) => <StatusLabel active={account.active} /> },
    { id: 'users', label: 'Usuários', width: 105, render: (account) => account.users_count },
  ];

  return (
    <SaasLayout title="Contas" actions={<Button href="/saas/contas/cadastrar" tone="primary" leftIconClass="fa fa-plus">Cadastrar conta</Button>}>
      <BusinessList label="Listagem de contas" preferenceKey="accounts" page={accounts} rows={accounts.data}
        columns={columns} rowKey={(account) => account.id} empty={search ? 'Nenhuma conta encontrada.' : 'Nenhuma conta cadastrada.'}
        filters={<Form key={search} action="/dashboard" method="get" role="search" className="saas-search" noValidate>
          {accounts.per_page !== 25 && <input type="hidden" name="per_page" value={accounts.per_page} />}
          <InputGroup action={<button className="inputGroup__button waves-effect" type="submit" aria-label="Buscar" title="Buscar">
            <i className="fa fa-search" aria-hidden="true" />
          </button>}>
            <TextInput name="q" type="search" aria-label="Buscar conta" placeholder="Buscar por nome, CPF ou CNPJ" defaultValue={search} maxLength={100} />
          </InputGroup>
          {search && <Link href={accounts.per_page === 25 ? '/dashboard' : `/dashboard?per_page=${accounts.per_page}`}>Limpar</Link>}
        </Form>}
      />
      {preview && <AccountPreview key={preview.id} {...preview} onClose={() => {
        setPreview(undefined);
        trigger.current?.focus();
      }} />}
      {inspection && <AccountInspection key={inspection.id} {...inspection} onClose={() => {
        setInspection(undefined);
        trigger.current?.focus();
      }} />}
    </SaasLayout>
  );
}
