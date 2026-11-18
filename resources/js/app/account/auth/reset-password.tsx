import { AccountForm, AccountScreen } from '@/app/account/AccountForm';

export default function ResetPassword({ email, token }: { email: string; token: string }) {
  return (
    <AccountScreen title="Redefinir senha">
      <AccountForm action="/reset-password" submit="Redefinir senha" fields={[
        { name: 'email', label: 'E-mail', type: 'email', autoComplete: 'email', value: email },
        { name: 'password', label: 'Nova senha', type: 'password', autoComplete: 'new-password' },
        { name: 'password_confirmation', label: 'Confirme a nova senha', type: 'password', autoComplete: 'new-password' },
      ]}>
        <input type="hidden" name="token" value={token} />
      </AccountForm>
    </AccountScreen>
  );
}
