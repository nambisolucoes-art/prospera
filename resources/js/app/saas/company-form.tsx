import { useState } from 'react';
import BusinessForm, {
  BusinessFormActions,
  BusinessFormSection,
} from '@/components/BusinessForm/BusinessForm';
import { Field, Select } from '@/components/BusinessForm/BusinessFormFields';
import CardBox from '@/components/CardBox/CardBox';
import { useApiPage } from '@/hooks/useApiPage';
import type { SessionProps } from '@/types/Session.types';
import { formatTaxDocument } from '@/utils/formatTaxDocument';
import { AddressFields } from './ClientFormFields';
import ContactFields from './ContactFields';
import SaasLayout from './SaasLayout';
import { organizationFormNavigation } from './organizationNavigation';
import type { CompanyRow } from './Organization.types';
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
                    onChange={(value) => { setType(value); setDocument(value === company?.person_type ? company.document ?? '' : ''); }} />
                  <Field label={legalEntity ? 'Razão social' : 'Nome'} name="name" defaultValue={company?.name} error={errors.name} required />
                  <Field label="Nome fantasia" name="trade_name" defaultValue={company?.trade_name ?? ''} error={errors.trade_name} />
                  <Field label={legalEntity ? 'CNPJ' : 'CPF'} name="document" compact required maxLength={18}
                    inputMode={legalEntity ? 'text' : 'numeric'}
                    placeholder={legalEntity ? 'CNPJ completo ou raiz' : '000.000.000-00'}
                    value={document ? formatTaxDocument(document, type) : ''}
                    onChange={(event) => setDocument(event.target.value)} error={errors.document} />
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
                        <Select label="Classificação" name="primary[classification]" defaultValue="headquarters"
                          error={errors['primary.classification']}
                          options={[{ value: 'headquarters', label: 'Matriz' }, { value: 'branch', label: 'Filial' }]} />
                      ) : (
                        <p className="business-form__control-start">
                          {legalEntity
                            ? 'Com o CNPJ completo, ele fica registrado como matriz ou filial. Com a raiz, a unidade fica sem CNPJ próprio.'
                            : 'Empresas identificadas por CPF não têm matriz nem filial.'}
                        </p>
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
