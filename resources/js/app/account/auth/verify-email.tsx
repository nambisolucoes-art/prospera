import { AccountForm, AccountScreen } from '@/app/account/AccountForm';

export default function VerifyEmail({ status }: { status?: string }) {
  return (
    <AccountScreen title="Verificar e-mail">
      <p>Confirme seu e-mail pelo link enviado para continuar.</p>
      {status === 'verification-link-sent' && <p role="status">Enviamos um novo link de verificação.</p>}
      <AccountForm action="/email/verification-notification" submit="Reenviar link" />
      <div className="prospera-account-action"><AccountForm action="/logout" submit="Sair" /></div>
    </AccountScreen>
  );
}
