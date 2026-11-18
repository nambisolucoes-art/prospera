import Form from '@/components/ApiForm/ApiForm';
import { AccountScreen } from '@/app/account/AccountForm';
import FormField from '@/components/FormField/FormField';
import InlineAlert from '@/components/InlineAlert/InlineAlert';
import InputAction from '@/components/InputAction/InputAction';

export default function ForgotPassword({ status }: { status?: string }) {
  return (
    <AccountScreen title="Redefinir senha" footer={<a href="/login">Voltar ao login</a>}>
      {status ? (
        <div role="status"><InlineAlert variant="success">Enviamos um link de recuperação para o seu e-mail.</InlineAlert></div>
      ) : (
        <InlineAlert variant="info" dismissible>Informe seu <strong>e-mail</strong> para receber as instruções de recuperação.</InlineAlert>
      )}
      <Form action="/forgot-password" method="post" noValidate className="prospera-account-form--compact">
        {({ errors, processing }) => (
          <FormField id="recovery-email" label="E-mail" error={errors.email} passiveLabel>
            <InputAction id="recovery-email" name="email" type="email" autoComplete="email" placeholder="E-mail" label="Enviar" tone="primary"
              invalid={Boolean(errors.email)} submitting={processing} aria-labelledby="recovery-email-label" aria-describedby={errors.email ? 'recovery-email-error' : undefined} required />
          </FormField>
        )}
      </Form>
    </AccountScreen>
  );
}
