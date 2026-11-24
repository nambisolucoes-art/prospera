import { useRef, useState } from 'react';
import BusinessForm, {
  BusinessFormActions,
  BusinessFormSection,
} from '@/components/BusinessForm/BusinessForm';
import { Field, Select } from '@/components/BusinessForm/BusinessFormFields';
import TaxDocumentField from '@/components/BusinessForm/TaxDocumentField';
import Button from '@/components/Button/Button';
import CardBox from '@/components/CardBox/CardBox';
import FormHelp from '@/components/FormHelp/FormHelp';
import { useApiPage } from '@/hooks/useApiPage';
import type { SessionProps } from '@/types/Session.types';
import { AddressFields, LookupButton } from './ClientFormFields';
import ContactFields from './ContactFields';
import SaasLayout from './SaasLayout';
import { organizationFormNavigation } from './organizationNavigation';
import PromoteEstablishmentModal from './PromoteEstablishmentModal';
import {
  companyName,
  type CompanyLabel,
  type EstablishmentDetails,
  type HeadquartersLabel,
} from './Organization.types';
import { useCnpjLookup } from './useCnpjLookup';

export default function EstablishmentForm({
  establishment,
  companies,
  selectedCompany,
  headquarters = [],
}: {
  establishment: EstablishmentDetails | null;
  companies: CompanyLabel[];
  selectedCompany: number | null;
  headquarters?: HeadquartersLabel[];
}) {
  const { props: { auth }, url } = useApiPage<SessionProps>();
  const navigation = organizationFormNavigation(url, establishment?.company.id ?? selectedCompany ?? undefined);
  const readOnly = auth.inspection_mode === 'read-only';
  const can = (action: string) =>
    auth.permissions.includes(`account.establishments.${action}`);
  // Empresa inativa não recebe estabelecimentos novos.
  const selectable = companies.filter((company) => company.active);
  const initialCompany = establishment?.company.id
    ?? selectable.find((company) => company.id === selectedCompany)?.id
    ?? (selectable.length === 1 ? selectable[0].id : undefined);
  const [companyId, setCompanyId] = useState(initialCompany ? String(initialCompany) : '');
  const company = establishment?.company ?? companies.find((option) => String(option.id) === companyId);
  const fixedCompany = Boolean(navigation.back && selectedCompany && company?.id === selectedCompany);
  const legalEntity = company?.person_type === 'company' && company.document_label !== null;
  const [classification, setClassification] = useState<string>(establishment?.classification ?? 'unregistered');
  // A matriz não precisa estar cadastrada; quando estiver e for visível, aparece como referência.
  const registeredHeadquarters = headquarters.find((unit) =>
    unit.company_id === company?.id && unit.id !== establishment?.id);
  const headquartersLabel = registeredHeadquarters && (registeredHeadquarters.tax_document_label
    ? `${registeredHeadquarters.name}, CNPJ ${registeredHeadquarters.tax_document_label}`
    : registeredHeadquarters.name);
  const [document, setDocument] = useState(establishment?.tax_document ?? '');
  const [name, setName] = useState(establishment?.name ?? '');
  const [tradeName, setTradeName] = useState(establishment?.trade_name ?? '');
  const cnpjLookup = useCnpjLookup(setName, setTradeName);
  const [promoting, setPromoting] = useState(false);
  const trigger = useRef<HTMLElement | null>(null);
  const listHref = navigation.back ?? (can('list') ? '@/estabelecimentos' : undefined);
  const mayChangeStatus = establishment !== null
    && !establishment.is_primary
    && (establishment.active ? can('deactivate') : can('activate'));
  const mayPromote = establishment !== null && !readOnly && can('promote') && !establishment.is_primary
    && establishment.active && establishment.company.active;
  return (
    <SaasLayout
      tenant
      title={establishment ? 'Editar estabelecimento' : 'Cadastrar estabelecimento'}
      breadcrumb={[
        { label: navigation.back ? 'Organização' : 'Estabelecimentos', href: listHref },
        { label: establishment ? 'Editar' : 'Cadastrar' },
      ]}
      actions={mayPromote && (
        <Button tone="primary" leftIconClass="fa fa-flag" aria-haspopup="dialog"
          onClick={(event) => { trigger.current = event.currentTarget; setPromoting(true); }}>
          Tornar principal
        </Button>
      )}
    >
      <CardBox>
        <BusinessForm
          className="business-form--wide-labels"
          action={`${establishment ? `@/estabelecimentos/${establishment.id}` : '@/estabelecimentos'}${navigation.suffix}`}
          method={establishment ? 'patch' : 'post'}
        >
          {({ errors, processing }) => (
            <>
              <fieldset disabled={readOnly} style={{ border: 0, padding: 0, margin: 0, minWidth: 0 }}>
                <BusinessFormSection
                  title="Identificação"
                  tourTarget="establishment-identity"
                  note={<><span className="formField__required" aria-hidden="true">*</span>Campos obrigatórios</>}
                >
                  {company && (establishment || fixedCompany) ? (
                    <Field label="Empresa" value={companyName(company)} readOnly
                      title="A empresa proprietária não muda depois do cadastro." />
                  ) : selectable.length > 1 ? (
                    <Select label="Empresa" name="company_id" required searchable value={companyId} error={errors.company_id}
                      onChange={(value) => {
                        setCompanyId(value);
                        setClassification('unregistered');
                        setDocument('');
                        cnpjLookup.reset();
                      }}
                      options={[{ value: '', label: 'Selecione a empresa' },
                        ...selectable.map((option) => ({ value: String(option.id), label: companyName(option) }))]} />
                  ) : null}
                  {!establishment && (fixedCompany || selectable.length <= 1) && (
                    <input type="hidden" name="company_id" value={companyId} />
                  )}
                  {!establishment && (fixedCompany || selectable.length <= 1) && errors.company_id && (
                    <p className="formField__error business-form__control-start" role="alert">{errors.company_id}</p>
                  )}
                  <Field label="Nome" name="name" value={name}
                    onChange={(event) => setName(event.target.value)} error={errors.name} required />
                  <Field label="Nome fantasia" name="trade_name" value={tradeName}
                    onChange={(event) => setTradeName(event.target.value)} error={errors.trade_name} />
                  {establishment ? (
                    <Field label="Código" value={establishment.code} readOnly compact
                      info="O código identifica o estabelecimento nos endereços e não muda depois do cadastro." />
                  ) : (
                    <Field label="Código" name="code"
                      info="Identifica o estabelecimento nos endereços e não pode ser alterado depois. Se ficar vazio, será gerado pelo nome. Use de 2 a 40 letras minúsculas sem acento, números ou hífen, sem palavras reservadas."
                      compact maxLength={40} error={errors.code}
                      placeholder="Gerado pelo nome" />
                  )}
                  {establishment && (
                    <FormHelp className="business-form__control-start">
                      {establishment.is_primary
                        ? 'Este é o estabelecimento principal da empresa e fica sempre ativo.'
                        : 'Para torná-lo o estabelecimento principal, use Tornar principal no topo da tela.'}
                    </FormHelp>
                  )}
                </BusinessFormSection>
                <BusinessFormSection title="Identificação fiscal" tourTarget="establishment-fiscal">
                  {legalEntity ? (
                    <Select label="Classificação" name="classification" required value={classification}
                      info="Matriz e Filial são a classificação fiscal informada por você, sem dedução pela ordem do CNPJ. Filiais podem ser cadastradas sem a matriz, e qualquer unidade ativa pode ser a principal."
                      error={errors.classification} onChange={(value) => {
                        setClassification(value);
                        cnpjLookup.reset();
                      }}
                      options={[
                        { value: 'headquarters', label: 'Matriz' },
                        { value: 'branch', label: 'Filial' },
                        { value: 'unregistered', label: 'Sem CNPJ próprio' },
                      ]} />
                  ) : (
                    <>
                      <Field label="Classificação" value={company ? 'Sem CNPJ próprio' : 'Selecione a empresa'}
                        readOnly error={errors.classification} />
                      <input type="hidden" name="classification" value="unregistered" />
                      {company && (
                        <FormHelp className="business-form__control-start">
                          {company.person_type === 'individual'
                            ? 'Empresas identificadas por CPF têm unidades sem CNPJ próprio.'
                            : 'Informe o CNPJ no cadastro da empresa para poder classificar a unidade como Matriz ou Filial.'}
                        </FormHelp>
                      )}
                    </>
                  )}
                  {legalEntity && classification === 'branch' && headquartersLabel && (
                    <FormHelp className="business-form__control-start">
                      {`Matriz cadastrada nesta empresa: ${headquartersLabel}. Apenas referência; a filial não depende dela.`}
                    </FormHelp>
                  )}
                  {legalEntity && classification === 'headquarters' && headquartersLabel && (
                    <FormHelp className="business-form__control-start">
                      {`Esta empresa já tem a matriz ${headquartersLabel}. Altere a classificação dela antes de classificar outra unidade como Matriz.`}
                    </FormHelp>
                  )}
                  {legalEntity && classification !== 'unregistered' && (
                    <TaxDocumentField personType="company" name="tax_document" required errors={errors}
                      info="Informe um CNPJ completo e válido, com a mesma raiz da empresa proprietária. A lupa consulta CNPJs numéricos completos e preenche os nomes que ainda estiverem vazios."
                      placeholder={company?.document_label ? `${company.document_label}/0000-00` : undefined}
                      value={document} onChange={(event) => {
                        setDocument(event.target.value);
                        cnpjLookup.reset();
                      }}
                      action={<LookupButton label="Buscar CNPJ" loading={cnpjLookup.loading} disabled={processing}
                        onClick={() => void cnpjLookup.lookup(document)} />}
                      error={cnpjLookup.error} />
                  )}
                  <Field label="Inscrição estadual" name="state_registration" maxLength={30}
                    defaultValue={establishment?.state_registration ?? ''} error={errors.state_registration} />
                  <Field label="Inscrição municipal" name="municipal_registration" maxLength={30}
                    defaultValue={establishment?.municipal_registration ?? ''} error={errors.municipal_registration} />
                  {mayChangeStatus && (
                    <Select label="Situação" name="active" defaultValue={establishment.active ? '1' : '0'} error={errors.active}
                      options={[{ value: '1', label: 'Ativo' }, { value: '0', label: 'Inativo' }]} />
                  )}
                  {errors.active && !mayChangeStatus && (
                    <p className="formField__error business-form__control-start" role="alert">{errors.active}</p>
                  )}
                </BusinessFormSection>
                <AddressFields initial={establishment?.address ?? undefined} errors={errors} namePrefix="address"
                  errorPrefix="address" tourTarget="establishment-address" />
                <ContactFields values={establishment ?? {}} errors={errors} required={false} namePrefix="" errorPrefix=""
                  tourTarget="establishment-contact" />
              </fieldset>
              {!readOnly && (
                <BusinessFormActions processing={processing} cancelHref={listHref ?? '@'} />
              )}
            </>
          )}
        </BusinessForm>
      </CardBox>
      {promoting && establishment && (
        <PromoteEstablishmentModal
          establishment={establishment}
          actionSuffix={navigation.suffix}
          onClose={() => {
            setPromoting(false);
            requestAnimationFrame(() => trigger.current?.focus());
          }}
        />
      )}
    </SaasLayout>
  );
}
