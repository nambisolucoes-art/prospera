import Button from '@/components/Button/Button';
import { useState } from 'react';
import { AccountForm, AccountScreen } from '@/app/account/AccountForm';

export default function TwoFactorChallenge() {
  const [recovery, setRecovery] = useState(false);

  return (
    <AccountScreen title="Verificação em duas etapas">
      <AccountForm action="/two-factor-challenge" submit="Verificar" fields={[
        recovery
          ? { name: 'recovery_code', label: 'Código de recuperação' }
          : { name: 'code', label: 'Código do autenticador', autoComplete: 'one-time-code' },
      ]} />
      <Button className="prospera-account-action" type="button" onClick={() => setRecovery(!recovery)}>
        {recovery ? 'Usar o autenticador' : 'Usar código de recuperação'}
      </Button>
    </AccountScreen>
  );
}
