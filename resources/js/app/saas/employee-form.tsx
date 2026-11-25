import { useCallback, useEffect, useRef, useState } from 'react';
import BusinessForm, { BusinessFormActions, BusinessFormRow, BusinessFormSection } from '@/components/BusinessForm/BusinessForm';
import { Field, Select, type Errors } from '@/components/BusinessForm/BusinessFormFields';
import TaxDocumentField from '@/components/BusinessForm/TaxDocumentField';
import Button from '@/components/Button/Button';
import CardBox from '@/components/CardBox/CardBox';
import FormHelp from '@/components/FormHelp/FormHelp';
import Label from '@/components/Label/Label';
import SelectChecklist from '@/components/SelectControl/SelectChecklist';
import TagSelect from '@/components/TagSelect/TagSelect';
import { useApiPage } from '@/hooks/useApiPage';
import { useContextUrl } from '@/hooks/useContextUrl';
import type { SessionProps } from '@/types/Session.types';
import { ApiError, apiRequest } from '@/utils/apiRequest';
import { normalizeTaxDocument, type PersonDocumentType } from '@/utils/formatTaxDocument';
import { AddressFields, LookupButton, MaskedField } from './ClientFormFields';
import type { PersonRow } from './Directory.types';
import PersonContactFields from './PersonContactFields';
import PersonIdentityFields from './PersonIdentityFields';
import SaasLayout from './SaasLayout';
import './Directory.css';
import './Employees.css';

type CompanyOption = { code: string; name: string };
type Found = { id: number; name: string; person_type: string; links: { code: string; active: boolean }[] };
type Link = {
  id: number;
  company_code: string;
  company_name: string;
  company_active: boolean;
  active: boolean;
  work_email: string | null;
  work_phone: string | null;
  work_whatsapp: string | null;
  tag_ids: number[];
  can_update: boolean;
  can_change_status: boolean;
};
type Tag = { id: number; name: string; color: string };
type Props = {
  companies?: CompanyOption[];
  canCreatePeople?: boolean;
  registrationTags?: Tag[];
  person?: {
    id: number;
    name: string;
    central: PersonRow | null;
    can_edit_identification: boolean;
    can_edit_contacts: boolean;
    tags: Tag[];
  };
  links?: Link[];
  linkable?: CompanyOption[];
  access?: boolean | null;
  canSeeAccess?: boolean;
};

const EMPLOYEE_TAG_COLOR = '#1abc9c';

// Contatos profissionais no mesmo padrão dos contatos da Pessoa: ícones, telefone e WhatsApp lado a lado.
function WorkContactFields({ names, values, errors, disabled }: {
  names: { email: string; phone: string; whatsapp: string };
  values?: { email: string | null; phone: string | null; whatsapp: string | null };
  errors: { email?: string; phone?: string; whatsapp?: string };
  disabled: boolean;
}) {
  return (
    <>
      <BusinessFormRow>
        <MaskedField mask="phone" label="Telefone profissional" name={names.phone} type="tel" leadingIcon="fa fa-phone"
          defaultValue={values?.phone ?? ''} error={errors.phone} disabled={disabled} />
        <MaskedField mask="phone" label="WhatsApp profissional" name={names.whatsapp} type="tel"
          leadingIcon="fa fa-whatsapp" defaultValue={values?.whatsapp ?? ''} error={errors.whatsapp} disabled={disabled} />
      </BusinessFormRow>
      <Field label="E-mail profissional" name={names.email} type="email" trailingIcon="fa fa-envelope-o"
        defaultValue={values?.email ?? ''} error={errors.email} disabled={disabled} />
    </>
  );
}

// Etiquetas derivadas, fixas e somente leitura, como a etiqueta do papel em Clientes e Fornecedores.
function DerivedTags({ tags }: { tags: Tag[] }) {
  const options = tags.map((tag) => ({ ...tag, locked: true, displayOnly: true }));

  return (
    <BusinessFormSection title="Etiquetas" tourTarget="employee-tags">
      <TagSelect options={options} selected={options.map((tag) => tag.id)} onChange={() => undefined} />
    </BusinessFormSection>
  );
}

const DOCUMENT_LOOKUP_DELAY = 1200;

function isCompleteDocument(document: string, type: PersonDocumentType) {
  return (type === 'company' ? /^[A-Z0-9]{12}\d{2}$/ : /^\d{11}$/).test(document);
}

// Contatos profissionais próprios de cada empresa: um grupo de campos por empresa marcada.
function WorkContacts({ companies, errors, disabled }: { companies: CompanyOption[]; errors: Errors; disabled: boolean }) {
  if (companies.length === 0) return null;
  const several = companies.length > 1;

  return (
    <BusinessFormSection title="Contatos profissionais" tourTarget="employee-work-contacts">
      <FormHelp className="business-form__control-start">
        Opcionais e específicos de cada empresa. Não alteram os contatos centrais da pessoa.
      </FormHelp>
      {companies.map((company) => (
        <div key={company.code} className="employees__link">
          {several && <h3 className="employees__link-title">{company.name}</h3>}
          <WorkContactFields
            names={{
              email: `work[${company.code}][email]`,
              phone: `work[${company.code}][phone]`,
              whatsapp: `work[${company.code}][whatsapp]`,
            }}
            errors={{
              email: errors[`work.${company.code}.email`],
              phone: errors[`work.${company.code}.phone`],
              whatsapp: errors[`work.${company.code}.whatsapp`],
            }}
            disabled={disabled} />
        </div>
      ))}
    </BusinessFormSection>
  );
}

function CompanyChoice({ options, selected, onChange, disabledCodes = [], processing, errors }: {
  options: CompanyOption[];
  selected: string[];
  onChange: (code: string, checked: boolean) => void;
  disabledCodes?: string[];
  processing: boolean;
  errors: Errors;
}) {
  if (options.length < 2) {
    return (
      <>
        {selected.map((code) => <input key={code} type="hidden" name="companies[]" value={code} />)}
        {errors.companies && <p className="formField__error" role="alert">{errors.companies}</p>}
      </>
    );
  }

  return (
    <BusinessFormSection title="Empresas" tourTarget="employee-companies">
      <div className="business-form__control-start business-form__control-start--select">
        <SelectChecklist label="Empresas do vínculo" menuLabel="Selecionar empresas"
          options={options.map((option) => ({
            id: option.code,
            label: option.name,
            checked: selected.includes(option.code),
            disabled: processing || disabledCodes.includes(option.code),
          }))}
          onChange={onChange} />
      </div>
      <FormHelp className="business-form__control-start">
        {options.filter((option) => selected.includes(option.code)).map((option) => option.name).join(', ')
          || 'Nenhuma empresa selecionada'}
      </FormHelp>
      {selected.map((code) => <input key={code} type="hidden" name="companies[]" value={code} />)}
      {errors.companies && <p className="formField__error" role="alert">{errors.companies}</p>}
    </BusinessFormSection>
  );
}

export default function EmployeeForm(props: Props) {
  return props.person ? <Edit key={props.person.id} {...props} /> : <Register {...props} />;
}

function Register({ companies = [], canCreatePeople = false, registrationTags = [] }: Props) {
  const [tagIds, setTagIds] = useState<number[]>([]);
  const contextUrl = useContextUrl();
  const [selected, setSelected] = useState<string[]>(() => (companies.length === 1 ? [companies[0].code] : []));
  const [document, setDocument] = useState('');
  const [documentType, setDocumentType] = useState<PersonDocumentType>('individual');
  const [found, setFound] = useState<Found>();
  const [lookupError, setLookupError] = useState('');
  const [searching, setSearching] = useState(false);
  const [draft, setDraft] = useState(0);
  const timer = useRef<number>(undefined);
  const request = useRef<AbortController | null>(null);
  const taken = (found?.links ?? []).map((link) => link.code);
  const chosen = companies.filter((company) => selected.includes(company.code));

  const cancel = useCallback(() => {
    window.clearTimeout(timer.current);
    request.current?.abort();
    request.current = null;
  }, []);

  function reset() {
    cancel();
    setSearching(false);
    setFound(undefined);
    setLookupError('');
  }

  function changeDocument(value: string, type: PersonDocumentType) {
    const next = normalizeTaxDocument(value);
    if (next === document && type === documentType) return;
    reset();
    setDocument(next);
    setDocumentType(type);
  }

  const search = useCallback(async (query: string, type: PersonDocumentType) => {
    window.clearTimeout(timer.current);
    if (request.current) return;
    if (!isCompleteDocument(query, type)) {
      setLookupError(type === 'company' ? 'Informe um CNPJ válido.' : 'Informe um CPF válido.');
      return;
    }

    const pending = new AbortController();
    request.current = pending;
    setSearching(true);
    setLookupError('');
    try {
      const result = await apiRequest<{ person: Found | null }>(contextUrl('@/colaboradores/pessoa'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ documento: query, person_type: type }),
        signal: pending.signal,
      });
      if (!pending.signal.aborted) {
        setFound(result.person ?? undefined);
        if (result.person) setSelected((current) => current.filter((code) => !result.person?.links.some((link) => link.code === code)));
      }
    } catch (failure) {
      if (!pending.signal.aborted) {
        setLookupError(failure instanceof ApiError
          ? failure.errors.documento ?? failure.errors.person_type ?? failure.message
          : 'Não foi possível buscar a pessoa.');
      }
    } finally {
      if (!pending.signal.aborted) {
        request.current = null;
        setSearching(false);
      }
    }
  }, [contextUrl]);

  useEffect(() => {
    if (found || !isCompleteDocument(document, documentType)) return;
    timer.current = window.setTimeout(() => void search(document, documentType), DOCUMENT_LOOKUP_DELAY);
    return () => window.clearTimeout(timer.current);
  }, [document, documentType, found, search]);

  useEffect(() => cancel, [cancel]);

  const searchButton = (value: string, type: PersonDocumentType) => (
    <LookupButton label={type === 'company' ? 'Buscar por CNPJ' : 'Buscar por CPF'} loading={searching}
      onClick={() => void search(normalizeTaxDocument(value), type)} />
  );

  return (
    <SaasLayout tenant title="Cadastrar colaborador"
      breadcrumb={[{ label: 'Institucional' }, { label: 'Colaboradores', href: '@/colaboradores' }, { label: 'Cadastrar' }]}>
      <CardBox>
        <BusinessForm action="@/colaboradores" method="post" options={{ successHref: '@/colaboradores' }}
          className="business-form--wide-labels" data-tour="employees-registration-form"
          onSubmitCapture={(event) => {
            window.clearTimeout(timer.current);
            if (request.current) {
              event.preventDefault();
              event.stopPropagation();
            }
          }}>
          {({ errors, processing }) => (
            <>
              <CompanyChoice options={companies} selected={selected} processing={processing} errors={errors}
                disabledCodes={taken}
                onChange={(code, checked) => setSelected((current) =>
                  checked ? [...current, code] : current.filter((value) => value !== code))} />
              {found && (
                <BusinessFormSection title="Pessoa encontrada" tourTarget="employee-person">
                  <div role="status">
                    <FormHelp>
                      <strong>{found.name}</strong> já tem cadastro nesta conta e será reaproveitada, sem duplicar o documento.
                      {taken.length > 0 && ` Empresas com vínculo existente: ${companies
                        .filter((company) => taken.includes(company.code)).map((company) => company.name).join(', ') || 'fora da sua seleção'}.`}
                    </FormHelp>
                    <Button tone="warning" size="sm" labelIconClass="fa fa-undo" disabled={processing}
                      onClick={() => { reset(); setDocument(''); setDraft((current) => current + 1); }}>
                      Descartar
                    </Button>
                  </div>
                  <input type="hidden" name="person_id" value={found.id} />
                </BusinessFormSection>
              )}
              {!found && canCreatePeople && (
                <fieldset className="person-form__group" disabled={processing}>
                  <PersonIdentityFields key={draft} person={null} errors={errors}
                    documentAction={searchButton} onDocumentChange={changeDocument}
                    documentLookupError={lookupError || errors.person_id} />
                  <AddressFields errors={errors} namePrefix="address" errorPrefix="address" tourTarget="person-address" />
                  <PersonContactFields person={null} errors={errors} access={false} />
                </fieldset>
              )}
              {!found && !canCreatePeople && (
                <BusinessFormSection title="Dados da pessoa" tourTarget="person-identity"
                  note="Seu cargo não cadastra pessoas novas. Busque uma pessoa já cadastrada pelo documento completo.">
                  <Select label="Tipo de pessoa" value={documentType} required
                    options={[{ value: 'individual', label: 'Pessoa física' }, { value: 'company', label: 'Pessoa jurídica' }]}
                    onChange={(value) => changeDocument('', value === 'company' ? 'company' : 'individual')} />
                  <TaxDocumentField personType={documentType} value={document} required disabled={processing}
                    error={lookupError || errors.person_id}
                    onChange={(event) => changeDocument(event.target.value, documentType)}
                    action={searchButton(document, documentType)} />
                </BusinessFormSection>
              )}
              <BusinessFormSection title="Etiquetas" tourTarget="employee-tags">
                <TagSelect
                  options={[{ id: 0, name: 'Colaborador', color: EMPLOYEE_TAG_COLOR, locked: true, displayOnly: true },
                    ...registrationTags]}
                  selected={[0, ...tagIds]}
                  onChange={(ids) => setTagIds(ids.filter((id) => id !== 0))}
                  disabled={processing}
                  error={Object.entries(errors).find(([key]) => key === 'tag_ids' || key.startsWith('tag_ids.'))?.[1]} />
              </BusinessFormSection>
              <WorkContacts companies={chosen} errors={errors} disabled={processing} />
              <BusinessFormActions processing={processing} cancelHref="@/colaboradores"
                submitDisabled={searching || selected.length === 0 || (!found && !canCreatePeople)} />
            </>
          )}
        </BusinessForm>
      </CardBox>
    </SaasLayout>
  );
}

function LinkForm({ link, personId, writable, tags }: { link: Link; personId: number; writable: boolean; tags: Tag[] }) {
  const [tagIds, setTagIds] = useState(link.tag_ids);
  const [status, setStatus] = useState(link.active ? '1' : '0');
  const editable = writable && link.company_active;

  return (
    <CardBox>
      <BusinessForm action={`@/colaboradores/${personId}/vinculos/${link.id}`} method="patch"
        className="business-form--wide-labels">
        {({ errors, processing }) => {
          const readOnly = !editable || !link.can_update;
          const disabled = readOnly || processing;

          return (
            <>
              <BusinessFormSection title={`Vínculo em ${link.company_name}`}>
                <p className="employees__link-title">
                  <Label variant={link.active ? 'success' : 'danger'}>{link.active ? 'Ativo' : 'Inativo'}</Label>
                  {!link.company_active && <Label variant="danger">Empresa inativa</Label>}
                </p>
                {editable && link.can_change_status ? (
                  <Select label="Situação" name="active" value={status} error={errors.active}
                    onChange={setStatus}
                    options={[{ value: '1', label: 'Ativo' }, { value: '0', label: 'Inativo' }]} />
                ) : (
                  <Field label="Situação" value={link.active ? 'Ativo' : 'Inativo'} readOnly />
                )}
                {!readOnly && <input type="hidden" name="edit_tags" value="1" />}
                <TagSelect
                  options={readOnly ? tags.map((tag) => ({ ...tag, locked: true, displayOnly: true })) : tags}
                  selected={tagIds} onChange={setTagIds} disabled={processing}
                  error={Object.entries(errors).find(([key]) => key === 'tag_ids' || key.startsWith('tag_ids.'))?.[1]} />
                <WorkContactFields
                  names={{ email: 'work_email', phone: 'work_phone', whatsapp: 'work_whatsapp' }}
                  values={{ email: link.work_email, phone: link.work_phone, whatsapp: link.work_whatsapp }}
                  errors={{ email: errors.work_email, phone: errors.work_phone, whatsapp: errors.work_whatsapp }}
                  disabled={disabled} />
              </BusinessFormSection>
              {editable && (link.can_update || link.can_change_status) && (
                <BusinessFormActions processing={processing} cancelHref="@/colaboradores" />
              )}
            </>
          );
        }}
      </BusinessForm>
    </CardBox>
  );
}

function Edit({ person, links = [], linkable = [], access, canSeeAccess = false, registrationTags = [] }: Props) {
  const { auth } = useApiPage<SessionProps>().props;
  const writable = auth.inspection_mode !== 'read-only';
  const [selected, setSelected] = useState<string[]>([]);
  const central = person?.central ?? null;
  const canEditCentral = writable && Boolean(central) && Boolean(person?.can_edit_identification || person?.can_edit_contacts);
  const chosen = linkable.filter((company) => selected.includes(company.code));

  if (!person) return null;

  return (
    <SaasLayout tenant title={`Editar colaborador`}
      breadcrumb={[{ label: 'Institucional' }, { label: 'Colaboradores', href: '@/colaboradores' }, { label: person.name }]}>
      <CardBox>
        <BusinessForm action={`@/colaboradores/${person.id}`} method="patch" className="business-form--wide-labels"
          data-tour="employee-central-form">
          {({ errors, processing }) => (
            <>
              {central ? (
                <>
                  <PersonIdentityFields person={central} errors={errors}
                    identificationDisabled={!canEditCentral || !person.can_edit_identification || processing} />
                  <fieldset className="person-form__group"
                    disabled={!canEditCentral || !person.can_edit_contacts || processing}>
                    <AddressFields initial={central.address ?? undefined} errors={errors} namePrefix="address"
                      errorPrefix="address" tourTarget="person-address" />
                    <PersonContactFields person={central} errors={errors} access={false} />
                  </fieldset>
                </>
              ) : (
                <BusinessFormSection title="Dados da pessoa" tourTarget="person-identity"
                  note="Seu cargo não visualiza o cadastro central de pessoas.">
                  <Field label="Nome" value={person.name} readOnly />
                </BusinessFormSection>
              )}
              <DerivedTags tags={person.tags} />
              {canEditCentral && <BusinessFormActions processing={processing} cancelHref="@/colaboradores" />}
            </>
          )}
        </BusinessForm>
      </CardBox>

      <div data-tour="employee-links">
        {links.map((link) => <LinkForm key={link.id} link={link} personId={person.id} writable={writable} tags={registrationTags} />)}
      </div>

      {writable && linkable.length > 0 && (
        <CardBox>
          <BusinessForm action={`@/colaboradores/${person.id}/vinculos`} method="post"
            className="business-form--wide-labels" data-tour="employee-add-link"
            onSuccess={() => setSelected([])}>
            {({ errors, processing }) => (
              <>
                <BusinessFormSection title="Vincular a outra empresa">
                  <FormHelp className="business-form__control-start">
                    Cada empresa marcada recebe um vínculo independente, sem alterar os existentes.
                  </FormHelp>
                  <div className="business-form__control-start business-form__control-start--select">
                    <SelectChecklist label="Empresas disponíveis" menuLabel="Selecionar empresas"
                      options={linkable.map((option) => ({
                        id: option.code,
                        label: option.name,
                        checked: selected.includes(option.code),
                        disabled: processing,
                      }))}
                      onChange={(code, checked) => setSelected((current) =>
                        checked ? [...current, code] : current.filter((value) => value !== code))} />
                  </div>
                  {selected.map((code) => <input key={code} type="hidden" name="companies[]" value={code} />)}
                  {errors.companies && <p className="formField__error" role="alert">{errors.companies}</p>}
                </BusinessFormSection>
                <WorkContacts companies={chosen} errors={errors} disabled={processing} />
                <BusinessFormActions processing={processing} submitDisabled={selected.length === 0}
                  cancelHref="@/colaboradores" />
              </>
            )}
          </BusinessForm>
        </CardBox>
      )}

      {canSeeAccess && (
        <CardBox>
          <div className="business-form" data-tour="employee-access">
            <BusinessFormSection title="Acesso ao Prospera">
            <p>
              <Label variant={access ? 'success' : 'info'}>{access ? 'Possui acesso à conta' : 'Sem acesso à conta'}</Label>
            </p>
            <FormHelp>
              Somente leitura. Ser colaborador não concede acesso nem permissões, e inativar o vínculo não remove o
              login. O acesso é administrado em Usuários.
            </FormHelp>
            </BusinessFormSection>
          </div>
        </CardBox>
      )}
    </SaasLayout>
  );
}
