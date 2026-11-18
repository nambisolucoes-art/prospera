import { AccountForm } from '@/app/account/AccountForm';
import { BusinessFormSection } from '@/components/BusinessForm/BusinessForm';

export default function ConfirmSecurity() {
  return (
    <BusinessFormSection as="section" title="Confirmar acesso" tourTarget="security-confirm">
      <AccountForm business wideLabels inlineActions action="/user/confirm-password" submit="Confirmar senha" fields={[
        { name: 'password', label: 'Senha', type: 'password', autoComplete: 'current-password' },
      ]} />
    </BusinessFormSection>
  );
}
