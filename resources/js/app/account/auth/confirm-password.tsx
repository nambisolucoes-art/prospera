import { AccountForm, AccountScreen } from '@/app/account/AccountForm';

export default function ConfirmPassword() {
  return (
    <AccountScreen title="Confirmar acesso">
      <AccountForm action="/user/confirm-password" submit="Confirmar senha" fields={[
        { name: 'password', label: 'Senha', type: 'password', autoComplete: 'current-password' },
      ]} />
    </AccountScreen>
  );
}
