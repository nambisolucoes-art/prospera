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

          ]}
        />
        {mustVerifyEmail && !user.email_verified_at && (
          <div data-tour="profile-verification">
            <p role="status">{status === 'verification-link-sent' ? 'Enviamos um novo link de verificação.' : 'Seu e-mail aguarda confirmação.'}</p>
            <AccountForm action="/email/verification-notification" submit="Reenviar link" submitTone="white" />
          </div>
        )}
      </BusinessFormSection>

      <BusinessFormSection as="section" title="E-mail de acesso" tourTarget="profile-access-email">
        <div className="business-form business-form--wide-labels">
          <Field label="E-mail atual" value={user.email} readOnly />
        </div>
        {status === 'email-change-unavailable' && (
          <p role="status">O link de confirmação está indisponível. Solicite uma nova troca de e-mail.</p>
        )}
        <AccountForm business wideLabels inlineActions action="/settings/profile/email" submit="Enviar link" fields={[
          { name: 'email', label: 'Novo e-mail', type: 'email', autoComplete: 'email' },
          { name: 'current_password', label: 'Senha atual', type: 'password', autoComplete: 'current-password' },
        ]} />
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
