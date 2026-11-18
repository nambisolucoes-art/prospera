import { useState } from 'react';
import { Field, Select, type Errors } from '@/components/BusinessForm/BusinessFormFields';

export default function UserFields({ errors, prefix = '', accountRoles = false }: { errors: Errors; prefix?: string; accountRoles?: boolean }) {
  const [mode, setMode] = useState('manual');
  const name = (field: string) => prefix ? `${prefix}[${field}]` : field;
  const error = (field: string) => errors[prefix ? `${prefix}.${field}` : field];
  const isAccountAdministrator = prefix === 'administrator';

  return (
    <>
      <Field label="Nome" name={name('name')} leadingIcon="fa fa-user"
        autoComplete={isAccountAdministrator ? 'off' : undefined} error={error('name')} required />
      <Field label="E-mail" name={name('email')} type="email" trailingIcon="fa fa-envelope-o"
        autoComplete={isAccountAdministrator ? 'off' : undefined} error={error('email')} required />
      {accountRoles && <Select label="Papel na conta" name="role" defaultValue="account-member" error={errors.role}
        options={[{ value: 'account-member', label: 'Usuário' }, { value: 'account-admin', label: 'Administrador da conta' }]} />}
      <Select label="Forma de acesso" name={name('mode')} value={mode} onChange={setMode} error={error('mode')}
        options={[{ value: 'manual', label: 'Definir senha agora' }, { value: 'invite', label: 'Enviar link por e-mail' }]} />
      {mode === 'manual' && (
        <>
          <Field label="Senha inicial" name={name('password')} type="password" autoComplete="new-password" error={error('password')} required />
          <Field label="Confirmar senha" name={name('password_confirmation')} type="password" autoComplete="new-password" error={error('password_confirmation')} required />
        </>
      )}
    </>
  );
}
