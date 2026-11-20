import { useState } from 'react';
import { Select, type Errors } from '@/components/BusinessForm/BusinessFormFields';

export type AssignmentOption = {
  key: string;
  label: string;
  scope: 'tenant' | 'company' | 'establishment';
  company_id: number | null;
  establishment_id: number | null;
  roles: { id: number; name: string }[];
};

export default function AssignmentFields({ options, errors, prefix = '', required = false }: {
  options: AssignmentOption[];
  errors: Errors;
  prefix?: string;
  required?: boolean;
}) {
  const [key, setKey] = useState(options.length === 1 && required ? options[0].key : '');
  const [role, setRole] = useState('');
  const selected = options.find((option) => option.key === key);
  const name = (field: string) => prefix ? prefix + '[' + field + ']' : field;
  const error = (field: string) => errors[prefix ? 'grants.0.' + field : field];

  return (
    <>
      <Select label="Onde" value={key} onChange={(value) => { setKey(value); setRole(''); }} required={required}
        error={error('scope') || (!selected ? error('role_id') : undefined)} options={[
          { value: '', label: 'Selecione o escopo' },
          ...options.map((option) => ({ value: option.key, label: option.label })),
        ]} />
      {selected && (
        <>
          <input type="hidden" name={name('scope')} value={selected.scope} />
          <input type="hidden" name={name('company_id')} value={selected.company_id ?? ''} />
          <input type="hidden" name={name('establishment_id')} value={selected.establishment_id ?? ''} />
        </>
      )}
      {selected && <Select label="Cargo" name={name('role_id')} value={role} onChange={setRole}
        required error={error('role_id')}
        options={[
          { value: '', label: 'Selecione o cargo' },
          ...selected.roles.map((option) => ({ value: String(option.id), label: option.name })),
        ]} />}
    </>
  );
}
