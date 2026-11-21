import { useState } from 'react';
import { Field, Select, type Errors } from '@/components/BusinessForm/BusinessFormFields';

export default function UserFields({ errors, prefix = '', person }: {
  errors: Errors;
  prefix?: string;
  accountRoles?: boolean;
  person?: { name: string; email: string | null };
}) {
  const [mode, setMode] = useState('manual');
  const name = (field: string) => prefix ? `${prefix}[${field}]` : field;
  const error = (field: string) => errors[prefix ? `${prefix}.${field}` : field];
  const isAccountAdministrator = prefix === 'administrator';

  return (
    <>
      <Field label="Nome" name={person ? undefined : name('name')} defaultValue={person?.name} readOnly={Boolean(person)}
        leadingIcon="fa fa-user"
        autoComplete={isAccountAdministrator ? 'off' : undefined} error={error('name')} required />
      <Field label={person ? 'E-mail de acesso' : 'E-mail'} name={name('email')} defaultValue={person?.email ?? ''}
        type="email"  trailingIcon="fa fa-envelope-o"
        autoComplete={isAccountAdministrator ? 'off' : undefined} error={error('email')} required />
      <Select label="Forma de acesso" info="Defina uma senha inicial ou envie um link por e-mail para a pessoa escolher sua senha." name={name('mode')} value={mode} onChange={setMode} error={error('mode')}
        options={[{ value: 'manual', label: 'Definir senha agora' }, { value: 'invite', label: 'Enviar link por e-mail' }]} />
      {mode === 'manual' && (
        <>
          <Field label="Senha inicial" name={name('password')} type="password" autoComplete="new-password" error={error('password')} required />
          <Field label="Confirmar senha" info="Repita exatamente a senha inicial. Os dois campos precisam coincidir para salvar." name={name('password_confirmation')} type="password" autoComplete="new-password" error={error('password_confirmation')} required />
        </>
      )}
    </>
  );
}
