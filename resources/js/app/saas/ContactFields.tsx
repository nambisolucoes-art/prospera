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

export default function ContactFields({ client, errors, required }: { client?: ClientData; errors: Errors; required: boolean }) {
  return (
    <BusinessFormSection title="Informações de contato">
      <BusinessFormRow>
        <MaskedField mask="phone" label="Telefone" name="details[phone]" type="tel" leadingIcon="fa fa-phone"
          defaultValue={contactNumber(client?.details, 'phone')} autoComplete="tel" error={errors['details.phone']} required={required} />
        <MaskedField mask="phone" label="WhatsApp" name="details[whatsapp]" type="tel" leadingIcon="fa fa-whatsapp"
          defaultValue={contactNumber(client?.details, 'whatsapp')} error={errors['details.whatsapp']} />
      </BusinessFormRow>
      <AdditionalContacts kind="phone" initial={client?.details?.additional_phones} errors={errors} />
      <Field label="E-mail de contato" name="email" type="email" trailingIcon="fa fa-envelope-o" defaultValue={client?.email ?? ''} error={errors.email} />
      <AdditionalContacts kind="email" initial={client?.details?.additional_emails} errors={errors} />
    </BusinessFormSection>
  );
}
