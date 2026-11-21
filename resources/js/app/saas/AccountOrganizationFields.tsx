import { useId, useRef, useState } from 'react';
import { BusinessFormSection } from '@/components/BusinessForm/BusinessForm';
import { Field, Select, type Errors } from '@/components/BusinessForm/BusinessFormFields';
import FormField from '@/components/FormField/FormField';
import YesNoControl from '@/components/YesNoControl/YesNoControl';
import { AddressFields } from './ClientFormFields';
import type { Address } from './ClientForm.types';
import CompanyDocumentField from './CompanyDocumentField';
import ContactFields from './ContactFields';
import { useCnpjLookup } from './useCnpjLookup';
import './AccountOrganizationFields.css';

const company = 'first_company';
const primary = `${company}[primary]`;
const addressFields = ['postal_code', 'street', 'number', 'complement', 'neighborhood', 'city', 'state'] as const;

function readDefaults(form: HTMLFormElement) {
  const values = new FormData(form);
  const value = (key: string) => String(values.get(key) ?? '');
  const address: Address = Object.fromEntries(
    addressFields.map((key) => [key, value(`details[address][${key}]`)]),
  );

  return {
    name: value('name'),
    tradeName: value('details[trade_name]'),
    type: value('person_type'),
    document: value('document'),
    address,
    contact: {
      phone: value('details[phone]'),
      whatsapp: value('details[whatsapp]'),
      email: value('email'),
      additional_phones: values.getAll('details[additional_phones][]').map(String).filter(Boolean),
      additional_emails: values.getAll('details[additional_emails][]').map(String).filter(Boolean),
    },
  };
}

type Defaults = ReturnType<typeof readDefaults>;

export default function AccountOrganizationFields({ errors }: { errors: Errors }) {
  const id = useId();
  const section = useRef<HTMLDivElement>(null);
  const [automatic, setAutomatic] = useState(true);
  const [defaults, setDefaults] = useState<Defaults>();

  function changeMode(value: boolean) {
    const form = section.current?.closest('form');
    if (!value && !defaults && form) setDefaults(readDefaults(form));
    setAutomatic(value);
  }

  return (
    <div ref={section}>
      <BusinessFormSection title="Primeira empresa" tourTarget="account-first-company">
        <FormField id={id} label="Cadastrar automaticamente?" layout="horizontal" passiveLabel
          className="account-organization-mode"
          info="A conta nasce com a empresa do contratante e seu estabelecimento principal."
          error={errors.organization_setup || errors.first_company || errors['first_company.primary']}>
          <YesNoControl name="organization_setup" value={automatic} onChange={changeMode}
            labelledBy={`${id}-label`} yesValue="automatic" noValue="manual" />
        </FormField>
      </BusinessFormSection>
      {defaults && <ManualOrganizationFields defaults={defaults} errors={errors} disabled={automatic} />}
    </div>
  );
}

function ManualOrganizationFields({ defaults, errors, disabled }: {
  defaults: Defaults;
  errors: Errors;
  disabled: boolean;
}) {
  const [type, setType] = useState(defaults.type);
  const [document, setDocument] = useState(defaults.document);
  const [name, setName] = useState(defaults.name);
  const [tradeName, setTradeName] = useState(defaults.tradeName);
  const cnpjLookup = useCnpjLookup(setName, setTradeName);
  const legalEntity = type === 'company';
  const fullCnpj = legalEntity && document.replace(/[^a-z0-9]/gi, '').length === 14;

  return (
    <fieldset hidden={disabled} disabled={disabled} className="account-organization-fields">
      <BusinessFormSection title="Dados da empresa">
        <Select label="Tipo de pessoa" name={`${company}[person_type]`} value={type}
          options={[{ value: 'company', label: 'Pessoa jurídica' }, { value: 'individual', label: 'Pessoa física' }]}
          onChange={(value) => {
            setType(value);
            setDocument(value === defaults.type ? defaults.document : '');
            cnpjLookup.reset();
          }} error={errors['first_company.person_type']} required />
        <CompanyDocumentField name={`${company}[document]`} type={type} document={document}
          onChange={setDocument} lookup={cnpjLookup} error={errors['first_company.document']} />
        <Field label={legalEntity ? 'Razão social' : 'Nome'} name={`${company}[name]`}
          value={name} onChange={(event) => setName(event.target.value)} error={errors['first_company.name']} required />
        <Field label="Nome fantasia" name={`${company}[trade_name]`}
          value={tradeName} onChange={(event) => setTradeName(event.target.value)}
          error={errors['first_company.trade_name']} />
      </BusinessFormSection>
      <BusinessFormSection title="Estabelecimento principal">
        <Field label="Nome" name={`${primary}[name]`} required
          defaultValue={defaults.address.city ? `Unidade principal (${defaults.address.city})` : 'Unidade principal'}
          error={errors['first_company.primary.name']} />
        {fullCnpj && <Select label="Classificação jurídica" name={`${primary}[classification]`} required
          defaultValue="headquarters" error={errors['first_company.primary.classification']}
          options={[{ value: 'headquarters', label: 'Matriz' }, { value: 'branch', label: 'Filial' }]} />}
        <AddressFields initial={defaults.address} errors={errors} namePrefix={`${primary}[address]`}
          errorPrefix="first_company.primary.address" embedded />
        <ContactFields values={defaults.contact} errors={errors} required={false} namePrefix={primary}
          errorPrefix="first_company.primary" embedded />
      </BusinessFormSection>
    </fieldset>
  );
}
