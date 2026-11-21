import { useRef, useState } from 'react';
import { BusinessFormRow, BusinessFormSection } from '@/components/BusinessForm/BusinessForm';
import { Field, type Errors } from '@/components/BusinessForm/BusinessFormFields';
import { MaskedField } from './ClientFormFields';
import { contactNumber, type ClientData } from './ClientForm.types';
import './ContactFields.css';

export function AdditionalContacts({ kind, initial = [], errors, namePrefix = 'details', errorPrefix = 'details' }: { kind: 'phone' | 'email'; initial?: string[]; errors: Errors; namePrefix?: string; errorPrefix?: string }) {
  const [rows, setRows] = useState(() => initial.map((value, id) => ({ id, value })));
  const nextId = useRef(initial.length);
  const phone = kind === 'phone';
  const label = phone ? 'Telefone' : 'E-mail';
  const field = phone ? 'additional_phones' : 'additional_emails';
  const name = namePrefix ? `${namePrefix}[${field}]` : field;
  const errorName = errorPrefix ? `${errorPrefix}.${field}` : field;

  function add() {
    const id = nextId.current++;
    setRows((current) => [...current, { id, value: '' }]);
  }

  return (
    <>
      {rows.length === 0 && <input type="hidden" name={`${name}[]`} value="" />}
      {rows.map(({ id, value }, index) => {
        const description = `${label} ${index + 2}`;
        const props = {
          label: description,
          name: `${name}[]`,
          defaultValue: value,
          error: errors[`${errorName}.${index}`],
          action: (
            <button type="button" className="inputGroup__button contact-fields__remove waves-effect waves-light" aria-label={`Remover ${description.toLowerCase()}`}
              onClick={() => setRows((current) => current.filter((row) => row.id !== id))}>
              <i className="fa fa-times" aria-hidden="true" />
            </button>
          ),
        };

        return phone
          ? <MaskedField key={id} {...props} mask="phone" type="tel" leadingIcon="fa fa-phone" />
          : <Field key={id} {...props} type="email" leadingIcon="fa fa-envelope-o" />;
      })}
      <div className="contact-fields__actions">
        <button type="button" className="contact-fields__add waves-effect waves-light" aria-label={`Adicionar ${label.toLowerCase()}`}
          disabled={rows.length >= 20} onClick={add}>
          <span aria-hidden="true"><i className="fa fa-plus" /></span>{label}
        </button>
        {errors[errorName] && <span className="formField__error" role="alert">{errors[errorName]}</span>}
      </div>
    </>
  );
}

type ContactValues = {
  phone?: string | null;
  whatsapp?: string | null;
  email?: string | null;
  additional_phones?: string[] | null;
  additional_emails?: string[] | null;
};

/**
 * Contatos no padrão do cadastro da conta. Sem `values`, usa os campos do contratante (`details[...]`);
 * com `values`, recebe os nomes do cadastro que o utiliza, como os estabelecimentos.
 */
export default function ContactFields({ client, values, errors, required, namePrefix, errorPrefix, tourTarget, embedded = false }: {
  client?: ClientData;
  values?: ContactValues;
  errors: Errors;
  required: boolean;
  namePrefix?: string;
  errorPrefix?: string;
  tourTarget?: string;
  embedded?: boolean;
}) {
  const own = values !== undefined;
  const prefix = namePrefix ?? (own ? '' : 'details');
  const errorRoot = errorPrefix ?? prefix;
  const name = (field: string) => prefix ? `${prefix}[${field}]` : field;
  const error = (field: string) => errors[errorRoot ? `${errorRoot}.${field}` : field];
  const phone = own ? values.phone ?? '' : contactNumber(client?.details, 'phone');
  const whatsapp = own ? values.whatsapp ?? '' : contactNumber(client?.details, 'whatsapp');
  const emailName = own ? name('email') : 'email';
  const emailError = own ? error('email') : errors.email;
  const fields = (
    <>
      <BusinessFormRow>
        <MaskedField mask="phone" label="Telefone" name={name('phone')} type="tel" leadingIcon="fa fa-phone"
          defaultValue={phone} autoComplete="tel" error={error('phone')} required={required} />
        <MaskedField mask="phone" label="WhatsApp" name={name('whatsapp')} type="tel" leadingIcon="fa fa-whatsapp"
          defaultValue={whatsapp} error={error('whatsapp')} />
      </BusinessFormRow>
      <AdditionalContacts kind="phone" initial={(own ? values.additional_phones : client?.details?.additional_phones) ?? []}
        errors={errors} namePrefix={prefix} errorPrefix={errorRoot} />
      <Field label="E-mail" name={emailName} type="email" trailingIcon="fa fa-envelope-o"
        defaultValue={(own ? values.email : client?.email) ?? ''} error={emailError} />
      <AdditionalContacts kind="email" initial={(own ? values.additional_emails : client?.details?.additional_emails) ?? []}
        errors={errors} namePrefix={prefix} errorPrefix={errorRoot} />
    </>
  );

  return embedded ? fields : (
    <BusinessFormSection title="Informações de contato" tourTarget={tourTarget}>{fields}</BusinessFormSection>
  );
}
