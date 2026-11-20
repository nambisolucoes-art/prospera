import Form from '@/components/ApiForm/ApiForm';
import { useApiForm as useForm } from '@/hooks/useApiForm';
import { useEffect, useState } from 'react';
import BusinessForm, { BusinessFormActions, BusinessFormSection } from '@/components/BusinessForm/BusinessForm';
import { Field } from '@/components/BusinessForm/BusinessFormFields';
import Button from '@/components/Button/Button';

const endpoint = '/user/two-factor-authentication';

async function readSecurityData<T>(url: string, signal?: AbortSignal): Promise<T> {
  const response = await fetch(url, { signal, headers: { Accept: 'application/json' } });
  if (!response.ok) throw new Error('Não foi possível consultar os dados de segurança.');
  return response.json() as Promise<T>;
}

export default function TwoFactorSettings({ enabled, pending }: { enabled: boolean; pending: boolean }) {
  const [qrCode, setQrCode] = useState<string>();
  const [qrError, setQrError] = useState(false);
  const [qrAttempt, setQrAttempt] = useState(0);
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
  const [recoveryError, setRecoveryError] = useState(false);
  const disable = useForm({});

  useEffect(() => {
    if (!pending) return;

    const request = new AbortController();
    setQrError(false);
    void readSecurityData<{ svg: string }>('/user/two-factor-qr-code', request.signal)
      .then(({ svg }) => setQrCode(svg))
      .catch(() => { if (!request.signal.aborted) setQrError(true); });
    return () => request.abort();
  }, [pending, qrAttempt]);

  function deactivate() {
    disable.delete(endpoint, {
      preserveScroll: true,
      onSuccess: () => {
        setQrCode(undefined);
        setRecoveryCodes([]);
        setRecoveryError(false);
      },
    });
  }

  async function showRecoveryCodes() {
    try {
      setRecoveryError(false);
      setRecoveryCodes(await readSecurityData<string[]>('/user/two-factor-recovery-codes'));
    } catch {
      setRecoveryError(true);
    }
  }

  const deactivateButton = (disabled = false) => (
    <Button type="button" tone="white" disabled={disabled || disable.processing} onClick={deactivate}>Desativar</Button>
  );

  let setup = null;
  if (pending && qrCode) {
    setup = (
      <BusinessForm className="business-form--wide-labels" action="/user/confirmed-two-factor-authentication"
        method="post" options={{ preserveScroll: true }}>
        {({ errors, processing }) => (
          <>
            <p className="settings-qr-instructions">Leia o código QR no seu aplicativo autenticador.</p>
            <div className="business-form__control-start settings-qr">
              <img alt="Código QR do autenticador" src={`data:image/svg+xml,${encodeURIComponent(qrCode)}`} />
            </div>
            <Field name="code" label="Código do autenticador" inputMode="numeric" autoComplete="one-time-code"
              maxLength={6} error={errors.code} required />
            <BusinessFormActions inline processing={processing || disable.processing}>
              {deactivateButton(processing)}
            </BusinessFormActions>
          </>
        )}
      </BusinessForm>
    );
  } else if (pending) {
    setup = (
      <div className="business-form business-form--wide-labels">
        <div className="business-form__control-start">
          {qrError ? (
            <>
              <p role="alert" className="prospera-form-error">Não foi possível carregar o código QR.</p>
              <Button type="button" tone="white" onClick={() => setQrAttempt((value) => value + 1)}>Tentar novamente</Button>
              {deactivateButton()}
            </>
          ) : <p role="status">Carregando código QR…</p>}
        </div>
      </div>
    );
  } else if (!enabled) {
    setup = (
      <div className="business-form business-form--wide-labels">
        <Form action={endpoint} method="post" className="business-form__control-start" options={{ preserveScroll: true }}>
          {({ processing }) => <Button type="submit" tone="primary" disabled={processing}>Ativar</Button>}
        </Form>
      </div>
    );
  }

  return (
    <BusinessFormSection as="section" title="Verificação em duas etapas" tourTarget="security-two-factor">
      {setup}
      {enabled && (
        <>
          {deactivateButton()}
          <Button className="prospera-account-action" type="button" onClick={() => void showRecoveryCodes()}>Exibir códigos de recuperação</Button>
          {recoveryCodes.length > 0 && <ul>{recoveryCodes.map((code) => <li key={code}><code>{code}</code></li>)}</ul>}
          <Form action="/user/two-factor-recovery-codes" method="post" className="prospera-account-action" onSuccess={() => void showRecoveryCodes()}>
            {({ processing }) => <Button type="submit" disabled={processing}>Gerar novos códigos</Button>}
          </Form>
          {recoveryError && <p className="prospera-form-error" role="alert">Não foi possível carregar os códigos de recuperação.</p>}
        </>
      )}
    </BusinessFormSection>
  );
}
