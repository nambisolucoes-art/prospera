import { AccountForm } from '@/app/account/AccountForm';
import PasskeySettings, { type Passkey } from '@/app/account/PasskeySettings';
import TwoFactorSettings from '@/app/account/TwoFactorSettings';
import { BusinessFormSection } from '@/components/BusinessForm/BusinessForm';

type SecurityProps = {
  canManageTwoFactor?: boolean;
  twoFactorEnabled?: boolean;
  twoFactorPending?: boolean;
  canManagePasskeys?: boolean;
  passkeys: Passkey[];
};

export default function Security({ canManageTwoFactor, twoFactorEnabled = false, twoFactorPending = false, canManagePasskeys, passkeys }: SecurityProps) {
  return (
    <>
      <BusinessFormSection as="section" title="Alterar senha" tourTarget="security-password">
        <AccountForm business wideLabels inlineActions action="/settings/password" method="put" submit="Atualizar senha" fields={[
          { name: 'current_password', label: 'Senha atual', type: 'password', autoComplete: 'current-password' },
          { name: 'password', label: 'Nova senha', type: 'password', autoComplete: 'new-password' },
          { name: 'password_confirmation', label: 'Confirme a nova senha', type: 'password', autoComplete: 'new-password' },
        ]} />
      </BusinessFormSection>
      {canManageTwoFactor && <TwoFactorSettings enabled={twoFactorEnabled} pending={twoFactorPending} />}
      {canManagePasskeys && <PasskeySettings passkeys={passkeys} />}
    </>
  );
}
