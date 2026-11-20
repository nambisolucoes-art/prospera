import { useApiPage } from '@/hooks/useApiPage';
import { BusinessFormActions, BusinessFormSection } from '@/components/BusinessForm/BusinessForm';
import { Field } from '@/components/BusinessForm/BusinessFormFields';

import { usePasskeyRegister } from '@laravel/passkeys/react';
import { useState } from 'react';
import { AccountForm } from './AccountForm';

export type Passkey = { id: number | string; name: string };

export default function PasskeySettings({ passkeys }: { passkeys: Passkey[] }) {
  const { navigate, url } = useApiPage();
  const [name, setName] = useState('');
  const [missingName, setMissingName] = useState(false);
  const { register, isLoading, isSupported, error } = usePasskeyRegister({
    onSuccess: () => {
      setName('');
      void navigate(url, { preserveScroll: true });
    },
  });

  const fieldError = missingName ? 'Informe o nome da chave.' : error ?? undefined;

  return (
    <BusinessFormSection as="section" title="Chaves de acesso" tourTarget="security-passkeys">
      {isSupported && (
        <form className="business-form business-form--wide-labels" noValidate onSubmit={(event) => {
          event.preventDefault();
          const keyName = name.trim();
          setMissingName(!keyName);
          if (keyName) void register(keyName);
        }}>
          <Field name="name" label="Nome da chave" value={name} error={fieldError} required
            onChange={(event) => { setName(event.target.value); setMissingName(false); }} />
          <BusinessFormActions inline processing={isLoading} />
        </form>
      )}
      {!isSupported && <p>Este navegador não permite cadastrar chaves de acesso.</p>}
      {passkeys.map((passkey) => (
        <div className="prospera-account-action" key={passkey.id}>
          <p>{passkey.name}</p>
          <AccountForm action={`/user/passkeys/${encodeURIComponent(passkey.id)}`} method="delete" />
        </div>
      ))}
    </BusinessFormSection>
  );
}
