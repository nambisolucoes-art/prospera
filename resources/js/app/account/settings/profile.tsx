import { useApiPage as usePage } from '@/hooks/useApiPage';
import { AccountForm } from '@/app/account/AccountForm';
import { BusinessFormSection } from '@/components/BusinessForm/BusinessForm';
import { Field } from '@/components/BusinessForm/BusinessFormFields';
import type { SessionProps } from '@/types/Session.types';

type ProfileProps = { mustVerifyEmail: boolean; status?: string };

export default function Profile({ mustVerifyEmail, status }: ProfileProps) {
  const { auth } = usePage<SessionProps>().props;
  const user = auth.user;

  if (!user) return null;

  if (auth.inspection_mode) {
    return (
      <BusinessFormSection as="section" title="Informações pessoais" tourTarget="profile-details">
        <div className="business-form business-form--wide-labels">
          <Field name="name" label="Nome" value={user.name} readOnly />
          <Field name="email" label="E-mail" value={user.email} readOnly />
        </div>
      </BusinessFormSection>
    );
  }

  return (
    <>
      <BusinessFormSection as="section" title="Informações pessoais" tourTarget="profile-details">
        <AccountForm
          key={JSON.stringify([user.id, user.name, user.email])}
          business wideLabels inlineActions action="/settings/profile" method="patch" fields={[
            { name: 'name', label: 'Nome', autoComplete: 'name', value: user.name },
            { name: 'email', label: 'E-mail', type: 'email', autoComplete: 'email', value: user.email },
          ]}
        />
        {mustVerifyEmail && !user.email_verified_at && (
          <div data-tour="profile-verification">
            <p role="status">{status === 'verification-link-sent' ? 'Enviamos um novo link de verificação.' : 'Seu e-mail aguarda confirmação.'}</p>
            <AccountForm action="/email/verification-notification" submit="Reenviar link" submitTone="white" />
          </div>
        )}
      </BusinessFormSection>

      <BusinessFormSection as="section" title="Excluir conta" tourTarget="profile-delete">
        <p>Confirme sua senha para excluir sua conta de usuário.</p>
        <AccountForm business wideLabels inlineActions action="/settings/profile" method="delete" submitTone="danger" fields={[
          { name: 'password', label: 'Senha atual', type: 'password', autoComplete: 'current-password' },
        ]} />
      </BusinessFormSection>
    </>
  );
}
