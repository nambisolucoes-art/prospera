import ApiForm from '@/components/ApiForm/ApiForm';
import Button from '@/components/Button/Button';
import { useApiPage } from '@/hooks/useApiPage';
import SaasLayout from './SaasLayout';

type Invitation = { account: string; role: string | null; expires_at: string; enabled: boolean };

export default function InvitationPage({ invitation, message }: {
  invitation: Invitation | null;
  message: string | null;
}) {
  const { url } = useApiPage();

  return (
    <SaasLayout tenant title="Convite para conta">
      <div data-tour="account-invitation">
        {invitation ? (
          <>
            <p>Você recebeu um convite para {invitation.account}, com o cargo {invitation.role || 'Não informado'}.</p>
            <p>Validade: {new Date(invitation.expires_at).toLocaleString('pt-BR')}.</p>
            <p>O acesso será concedido após salvar o aceite com sua identidade autenticada.</p>
            {invitation.enabled ? (
              <ApiForm action={url}>
                {({ processing }) => <Button type="submit" tone="success" disabled={processing}>Salvar</Button>}
              </ApiForm>
            ) : <p>A entrada em outras contas por convite ainda não está liberada.</p>}
          </>
        ) : <p role="status">{message || 'Este convite não está disponível.'}</p>}
        <Button href="/contas" tone="white">Cancelar</Button>
      </div>
    </SaasLayout>
  );
}
