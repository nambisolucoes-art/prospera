import { useApiPage } from '@/hooks/useApiPage';
import Button from '@/components/Button/Button';
import ChoiceControl from '@/components/ChoiceControl/ChoiceControl';

import { usePasskeyVerify } from '@laravel/passkeys/react';
import { AccountForm, AccountScreen } from '@/app/account/AccountForm';

function PasskeyLogin() {
  const { navigate } = useApiPage();
  const { verify, isSupported, isLoading, error } = usePasskeyVerify({
    onSuccess: (response) => void navigate(response.redirect ?? '/dashboard'),
  });

  if (!isSupported) return null;

  return (
    <div className="prospera-account-action">
      <Button block type="button" disabled={isLoading} onClick={() => void verify()}>
        Entrar com chave de acesso
      </Button>
      {error && <p className="prospera-form-error" role="alert">{error}</p>}
    </div>
  );
}

export default function Login({ status, canResetPassword }: { status?: string; canResetPassword: boolean }) {
  return (
    <AccountScreen title="Entrar">
      {status && <p role="status">{status}</p>}
      <AccountForm action="/login" submit="Entrar" compact afterSubmit={canResetPassword && (
        <div className="prospera-forgot-link"><a href="/forgot-password"><i className="fa fa-lock" aria-hidden="true" /> Esqueceu a senha?</a></div>
      )} fields={[
        { name: 'email', label: 'E-mail', placeholder: 'E-mail', type: 'email', autoComplete: 'username' },
        { name: 'password', label: 'Senha', placeholder: 'Senha', type: 'password', autoComplete: 'current-password' },
      ]}>
        <ChoiceControl type="checkbox" id="login-remember" name="remember" value="1" tone="primary" label="Lembrar de mim" />
      </AccountForm>
      <PasskeyLogin />
    </AccountScreen>
  );
}
