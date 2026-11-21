import { useState } from 'react';
import BusinessForm, {
  BusinessFormActions,
  BusinessFormSection,
} from '@/components/BusinessForm/BusinessForm';
import { Field, Select } from '@/components/BusinessForm/BusinessFormFields';
import CardBox from '@/components/CardBox/CardBox';
import FormHelp from '@/components/FormHelp/FormHelp';
import { useApiPage } from '@/hooks/useApiPage';
import type { SessionProps } from '@/types/Session.types';
import { AddressFields } from './ClientFormFields';
import CompanyDocumentField from './CompanyDocumentField';
import ContactFields from './ContactFields';
import SaasLayout from './SaasLayout';
import { organizationFormNavigation } from './organizationNavigation';
import type { CompanyRow } from './Organization.types';
import { useCnpjLookup } from './useCnpjLookup';
export default function CompanyForm({
  company,
  taxRegimes,
}: {
  company: CompanyRow | null;
  taxRegimes: Record<string, string>;
}) {
  const { props: { auth }, url } = useApiPage<SessionProps>();
  const navigation = organizationFormNavigation(url, company?.id);
  const readOnly = auth.inspection_mode === 'read-only';
  const can = (action: string) =>
    auth.permissions.includes(`account.companies.${action}`);
  const [type, setType] = useState<string>(company?.person_type ?? 'company');
  const [document, setDocument] = useState(company?.document ?? '');
  const [name, setName] = useState(company?.name ?? '');
  const [tradeName, setTradeName] = useState(company?.trade_name ?? '');
  const cnpjLookup = useCnpjLookup(setName, setTradeName);
  const legalEntity = type === 'company';
  const fullCnpj = legalEntity && document.replace(/[.\/\s-]/g, '').length === 14;
  const listHref = navigation.back ?? (can('list') ? '/conta/empresas' : undefined);
  const mayChangeStatus = company !== null && (company.active ? can('deactivate') : can('activate'));
  return (
    <SaasLayout
      tenant
      title={company ? 'Editar empresa' : 'Cadastrar empresa'}
      breadcrumb={[
        { label: navigation.back ? 'Organização' : 'Empresas', href: listHref },
        { label: company ? 'Editar' : 'Cadastrar' },
      ]}
    >
      <CardBox>
        <BusinessForm
          className="business-form--wide-labels"
          action={`${company ? `/conta/empresas/${company.id}` : '/conta/empresas'}${navigation.suffix}`}
          method={company ? 'patch' : 'post'}
        >
          {({ errors, processing }) => (
            <>
              <fieldset disabled={readOnly} style={{ border: 0, padding: 0, margin: 0, minWidth: 0 }}>
                <BusinessFormSection
                  title="Identificação"
                  tourTarget="company-identity"
                  note={<><span className="formField__required" aria-hidden="true">*</span>Campos obrigatórios</>}
                >
                  <Select label="Tipo de pessoa" name="person_type" value={type} required error={errors.person_type}
                    options={[{ value: 'company', label: 'Pessoa jurídica' }, { value: 'individual', label: 'Pessoa física' }]}
                    onChange={(value) => {
                      setType(value);
                      setDocument(value === company?.person_type ? company.document ?? '' : '');
                      cnpjLookup.reset();
                    }} />
                  <CompanyDocumentField name="document" type={type} document={document}
                    onChange={setDocument} lookup={cnpjLookup} error={errors.document} />
                  <Field label={legalEntity ? 'Razão social' : 'Nome'} name="name" value={name}
                    onChange={(event) => setName(event.target.value)} error={errors.name} required />
                  <Field label="Nome fantasia" name="trade_name" value={tradeName}
                    onChange={(event) => setTradeName(event.target.value)} error={errors.trade_name} />
                  {company ? (
                    <Field label="Código" value={company.code} readOnly compact
                      title="O código identifica a empresa nos endereços e não muda depois do cadastro." />
                  ) : (
                    <Field label="Código" name="code" compact maxLength={40} error={errors.code}
                      placeholder="Gerado pelo nome fantasia" />
                  )}
                </BusinessFormSection>
                <BusinessFormSection title="Dados fiscais" tourTarget="company-fiscal">
                  <Select label="Regime tributário" name="tax_regime" defaultValue={company?.tax_regime ?? ''} error={errors.tax_regime}
                    options={[{ value: '', label: 'Não informado' }, ...Object.entries(taxRegimes).map(([value, label]) => ({ value, label }))]} />
                  <Field label="CNAE principal" name="main_cnae" compact inputMode="numeric" maxLength={9} placeholder="0000-0/00"
                    defaultValue={company?.main_cnae?.replace(/^(\d{4})(\d)(\d{2})$/, '$1-$2/$3') ?? ''} error={errors.main_cnae} />
                  {company && mayChangeStatus && (
                    <Select label="Situação" name="active" defaultValue={company.active ? '1' : '0'} error={errors.active}
                      options={[{ value: '1', label: 'Ativa' }, { value: '0', label: 'Inativa' }]} />
                  )}
                </BusinessFormSection>
                {!company && (
                  <>
                    <BusinessFormSection
                      title="Estabelecimento principal"
                      tourTarget="company-primary"
                      note="Criado junto com a empresa. Você poderá ajustá-lo e cadastrar outros em Estabelecimentos."
                    >
                      <Field label="Nome" name="primary[name]" placeholder="Unidade principal" error={errors['primary.name']} />
                      {fullCnpj ? (
                        <Select label="A unidade deste CNPJ é a sede ou uma filial?" name="primary[classification]" required defaultValue=""
                          error={errors['primary.classification']}
                          options={[
                            { value: '', label: 'Selecione Matriz ou Filial' },
                            { value: 'headquarters', label: 'Matriz' },
                            { value: 'branch', label: 'Filial' },
                          ]} />
                      ) : (
                        <FormHelp className="business-form__control-start">
                          {legalEntity
                            ? 'Informe o CNPJ completo para escolher Matriz ou Filial. Só com a raiz, a unidade é cadastrada sem CNPJ próprio.'
                            : 'Empresas identificadas por CPF não têm matriz nem filial.'}
                        </FormHelp>
                      )}
                    </BusinessFormSection>
                    <AddressFields errors={errors} namePrefix="primary[address]" errorPrefix="primary.address"
                      title="Endereço do estabelecimento principal" tourTarget="company-primary-address" />
                    <ContactFields values={{}} errors={errors} required={false} namePrefix="primary" errorPrefix="primary" />
                  </>
                )}
              </fieldset>
              {!readOnly && (
                <BusinessFormActions processing={processing} cancelHref={listHref ?? '/conta'} />
              )}
            </>
          )}
        </BusinessForm>
      </CardBox>
    </SaasLayout>
  );
}
