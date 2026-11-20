import { useRef, useState } from 'react';
import BusinessForm, { BusinessFormActions, BusinessFormSection } from '@/components/BusinessForm/BusinessForm';
import { Field, Select } from '@/components/BusinessForm/BusinessFormFields';
import { AddressFields, formatMasked, LookupButton } from './ClientFormFields';
import type { ClientData } from './ClientForm.types';
import ContactFields from './ContactFields';
import UserFields from './UserFields';

export default function ClientForm({ client, tenant = false }: { client?: ClientData; tenant?: boolean }) {
  const [type, setType] = useState(client?.person_type ?? 'company');
  const [document, setDocument] = useState(client?.document ?? '');
  const [name, setName] = useState(client?.name ?? '');
  const [tradeName, setTradeName] = useState(client?.details?.trade_name ?? '');
  const [loading, setLoading] = useState(false);
  const [lookupError, setLookupError] = useState('');
  const lookupVersion = useRef(0);
  const company = (tenant ? client?.person_type : type) === 'company';
  const required = !tenant && (!client || Boolean(client.document));
  const action = tenant ? '/conta' : client ? `/saas/contas/${client.id}` : '/saas/contas';

  function updateDocument(value: string) {
    lookupVersion.current++;
    setDocument(value);
    setLookupError('');
  }

  async function lookupCompany() {
    const cnpj = document.replace(/\D/g, '');
    if (cnpj.length !== 14) {
      setLookupError('Informe um CNPJ com 14 dígitos.');
      return;
    }

    const version = ++lookupVersion.current;
    setLoading(true);
    setLookupError('');
    try {
      const response = await fetch(`https://brasilapi.com.br/api/cnpj/v1/${cnpj}`, { signal: AbortSignal.timeout(8000) });
      if (version !== lookupVersion.current) return;
      if (response.status === 404) {
        setLookupError('CNPJ não encontrado.');
        return;
      }
      if (!response.ok) throw new Error();

      const companyDetails: { razao_social?: string; nome_fantasia?: string } = await response.json();
      if (version !== lookupVersion.current) return;
      setName((current) => current.trim() ? current : companyDetails.razao_social ?? '');
      setTradeName((current) => current.trim() ? current : companyDetails.nome_fantasia ?? '');
    } catch {
      if (version === lookupVersion.current) setLookupError('Consulta indisponível. Preencha os dados da empresa manualmente.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <BusinessForm className="business-form--wide-labels" action={action}
      method={client ? 'patch' : 'post'}>
      {({ errors, processing }) => (
        <>
          <BusinessFormSection title={tenant ? 'Informações do estabelecimento' : 'Informações da conta'} note={<><span className="formField__required" aria-hidden="true">*</span>Campos obrigatórios</>}>
            {!tenant && <Select label="Tipo de pessoa" name="person_type" value={type}
              options={[{ value: 'company', label: 'Pessoa jurídica' }, { value: 'individual', label: 'Pessoa física' }]} onChange={(value) => {
              const nextType = value === 'individual' ? 'individual' : 'company';
              setType(nextType);
              updateDocument(nextType === client?.person_type ? client.document ?? '' : '');
            }} error={errors.person_type} />}
            <Field label={company ? 'Razão social' : tenant ? 'Nome do estabelecimento' : 'Nome'} name="name" value={name} onChange={(event) => setName(event.target.value)} error={errors.name} required />
            {!tenant && <Field label={company ? 'CNPJ' : 'CPF'} name="document" compact inputMode="numeric"
              value={formatMasked(document, company ? 'cnpj' : 'cpf')} onChange={(event) => updateDocument(event.target.value)}
              action={company && <LookupButton label="Buscar CNPJ" loading={loading} onClick={() => void lookupCompany()} />}
              error={lookupError || errors.document} required={required} />}
            {company && <Field label="Nome fantasia" name="details[trade_name]" value={tradeName}
              onChange={(event) => setTradeName(event.target.value)} error={errors['details.trade_name']} />}
            {!tenant && <Field label="Plano" name="plan" defaultValue={client?.plan ?? ''} error={errors.plan} />}
            {!tenant && client && <Select label="Status" name="active" defaultValue={client.active ? '1' : '0'} error={errors.active}
              options={[{ value: '1', label: 'Ativa' }, { value: '0', label: 'Inativa' }]} />}
          </BusinessFormSection>
          <AddressFields initial={client?.details?.address} errors={errors} />
          <ContactFields client={client} errors={errors} required={required} />
          {!client && (
            <BusinessFormSection title="Administrador da conta">
              <UserFields errors={errors} prefix="administrator" />
            </BusinessFormSection>
          )}
          <BusinessFormActions
            processing={processing} cancelHref={tenant ? '/conta' : '/dashboard'} />
        </>
      )}
    </BusinessForm>
  );
}
