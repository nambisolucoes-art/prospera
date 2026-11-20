import { useState } from 'react';
import BusinessForm, { BusinessFormActions, BusinessFormSection } from '@/components/BusinessForm/BusinessForm';
import { Field, Select } from '@/components/BusinessForm/BusinessFormFields';
import ChoiceControl from '@/components/ChoiceControl/ChoiceControl';
import { formatTaxDocument } from '@/utils/formatTaxDocument';
import { AddressFields, LookupButton } from './ClientFormFields';
import type { ClientData } from './ClientForm.types';
import ContactFields from './ContactFields';
import UserFields from './UserFields';
import { useCnpjLookup } from './useCnpjLookup';

export default function ClientForm({
  client,
  tenant = false,
  organizationBack,
}: {
  client?: ClientData;
  tenant?: boolean;
  organizationBack?: string;
}) {
  const [type, setType] = useState(client?.person_type ?? 'company');
  const [document, setDocument] = useState(client?.document ?? '');
  const [name, setName] = useState(client?.name ?? '');
  const [tradeName, setTradeName] = useState(client?.details?.trade_name ?? '');
  const [contractorIsCompany, setContractorIsCompany] = useState(false);
  const cnpjLookup = useCnpjLookup(setName, setTradeName);
  const company = (tenant ? client?.person_type : type) === 'company';
  const required = !tenant && (!client || Boolean(client.document));
  const tenantAction = organizationBack ? '/conta?origem=organizacao' : '/conta';
  const action = tenant ? tenantAction : client ? `/saas/contas/${client.id}` : '/saas/contas';

  function updateDocument(value: string) {
    setDocument(value);
    cnpjLookup.reset();
  }

  return (
    <BusinessForm className="business-form--wide-labels" action={action}
      method={client ? 'patch' : 'post'}>
      {({ errors, processing }) => (
        <>
          <BusinessFormSection title={tenant ? 'Dados do contratante' : 'Informações da conta'} note={<><span className="formField__required" aria-hidden="true">*</span>Campos obrigatórios</>}>
            {!tenant && <Select label="Tipo de pessoa" name="person_type" value={type}
              options={[{ value: 'company', label: 'Pessoa jurídica' }, { value: 'individual', label: 'Pessoa física' }]} onChange={(value) => {
              const nextType = value === 'individual' ? 'individual' : 'company';
              setType(nextType);
              updateDocument(nextType === client?.person_type ? client.document ?? '' : '');
            }} error={errors.person_type} />}
            <Field label={company ? 'Razão social' : 'Nome'} name="name" value={name} onChange={(event) => setName(event.target.value)} error={errors.name} required />
            {!tenant && <Field label={company ? 'CNPJ' : 'CPF'} name="document" compact inputMode={company ? 'text' : 'numeric'} maxLength={18}
              value={document ? formatTaxDocument(document, company ? 'company' : 'individual') : ''} onChange={(event) => updateDocument(event.target.value)}
              action={company && <LookupButton label="Buscar CNPJ" loading={cnpjLookup.loading} onClick={() => void cnpjLookup.lookup(document)} />}
              error={cnpjLookup.error || errors.document} required={required} />}
            {company && <Field label="Nome fantasia" name="details[trade_name]" value={tradeName}
              onChange={(event) => setTradeName(event.target.value)} error={errors['details.trade_name']} />}
            {!tenant && <Field label="Plano" name="plan" defaultValue={client?.plan ?? ''} error={errors.plan} />}
            {tenant && client?.document && (
              <Field label={client.person_type === 'company' ? 'CNPJ' : 'CPF'} readOnly compact
                value={formatTaxDocument(client.document, client.person_type ?? 'company')}
                title="O documento do contratante é mantido pela plataforma." />
            )}
            {!tenant && client && <Select label="Status" name="active" defaultValue={client.active ? '1' : '0'} error={errors.active}
              options={[{ value: '1', label: 'Ativa' }, { value: '0', label: 'Inativa' }]} />}
          </BusinessFormSection>
          <AddressFields initial={client?.details?.address} errors={errors} />
          <ContactFields client={client} errors={errors} required={required} />
          {!client && (
            <BusinessFormSection title="Primeira empresa" note="A conta nasce com uma empresa e o estabelecimento principal dela.">
              <ChoiceControl type="checkbox" id="contractor-is-company" name="contractor_is_company" value="1"
                checked={contractorIsCompany} onChange={setContractorIsCompany} className="business-form__control-start"
                label="O contratante é a primeira empresa administrada (copia documento, endereço e contatos)" />
              {!contractorIsCompany && (
                <p className="business-form__control-start">A empresa usa o nome da conta, sem documento. A conta completa a identificação fiscal depois.</p>
              )}
            </BusinessFormSection>
          )}
          {!client && (
            <BusinessFormSection title="Administrador da conta">
              <UserFields errors={errors} prefix="administrator" />
            </BusinessFormSection>
          )}
          <BusinessFormActions
            processing={processing} cancelHref={tenant ? (organizationBack ?? '/conta') : '/dashboard'} />
        </>
      )}
    </BusinessForm>
  );
}
