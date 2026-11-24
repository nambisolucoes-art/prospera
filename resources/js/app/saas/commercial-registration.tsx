import { useCallback, useEffect, useRef, useState } from 'react';
import BusinessForm, { BusinessFormActions, BusinessFormSection } from '@/components/BusinessForm/BusinessForm';
import { Field, Select } from '@/components/BusinessForm/BusinessFormFields';
import SelectChecklist from '@/components/SelectControl/SelectChecklist';
import TaxDocumentField from '@/components/BusinessForm/TaxDocumentField';
import Button from '@/components/Button/Button';
import FormHelp from '@/components/FormHelp/FormHelp';
import InfoTooltip from '@/components/InfoTooltip/InfoTooltip';
import CardBox from '@/components/CardBox/CardBox';
import { useApiPage } from '@/hooks/useApiPage';
import { contextualUrl } from '@/utils/organizationContext';
import { normalizeTaxDocument, type PersonDocumentType } from '@/utils/formatTaxDocument';
import TagSelect from '@/components/TagSelect/TagSelect';
import { ApiError, apiRequest } from '@/utils/apiRequest';
import { AddressFields, LookupButton } from './ClientFormFields';
import PersonContactFields from './PersonContactFields';
import PersonIdentityFields from './PersonIdentityFields';
import SaasLayout from './SaasLayout';
import type { PersonRow } from './Directory.types';
import './Directory.css';

type FoundPerson = {
  id: number;
  name: string;
  relation: 'active' | 'inactive' | null;
  other_role?: string;
  central?: PersonRow;
  can_edit_identification?: boolean;
  can_edit_contacts?: boolean;
};
type Tag = { id: number; name: string; color: string };

const DOCUMENT_LOOKUP_DELAY = 1200;

function isCompleteDocument(document: string, type: PersonDocumentType) {
  return (type === 'company' ? /^[A-Z0-9]{12}\d{2}$/ : /^\d{11}$/).test(document);
}

type Props = {
  role: 'customer' | 'supplier';
  companies: { code: string; name: string }[];
  companyOptions?: { code: string; name: string; active: boolean; disabled: boolean }[];
  registrationTags: Tag[];
  roleTagColor: string;
  canCreatePeople: boolean;
  editing?: {
    id: number;
    name: string;
    central: PersonRow | null;
    can_edit_identification: boolean;
    can_edit_contacts: boolean;
    tag_ids: number[];
    active: boolean;
    can_change_status: boolean;
  };
};

export default function CommercialRegistration(props: Props) {
  return <RegistrationForm key={`${props.role}:${props.companies[0]?.code}:${props.editing?.id ?? 'new'}`} {...props} />;
}

function RegistrationForm({ role, companies, companyOptions, registrationTags, roleTagColor, canCreatePeople, editing }: Props) {
  const { url } = useApiPage();
  const singular = role === 'customer' ? 'cliente' : 'fornecedor';
  const listTitle = role === 'customer' ? 'Clientes' : 'Fornecedores';
  const segment = listTitle.toLowerCase();
  const listUrl = contextualUrl(`~/${segment}`, url);
  const [draft, setDraft] = useState(0);
  const [selectedCompanies, setSelectedCompanies] = useState(() =>
    editing
      ? (companyOptions ?? []).filter((option) => option.active).map((option) => option.code)
      : companies.length === 1 ? [companies[0].code] : [],
  );
  const companyCode = editing ? companies[0].code : selectedCompanies[0];
  const options = companyOptions ?? companies.map((option) => ({ ...option, active: false, disabled: false }));
  const isActive = selectedCompanies.includes(companyCode);

  const [selectedTags, setSelectedTags] = useState(editing?.tag_ids ?? []);
  const [document, setDocument] = useState('');
  const [documentType, setDocumentType] = useState<PersonDocumentType>('individual');
  const [found, setFound] = useState<FoundPerson>();
  const blocksLink = Boolean(found?.relation) && selectedCompanies.length < 2;
  const [departingPerson, setDepartingPerson] = useState<FoundPerson>();
  const [lookupError, setLookupError] = useState('');
  const [searching, setSearching] = useState(false);
  const lookupTimer = useRef<number>(undefined);
  const lookupRequest = useRef<AbortController | null>(null);
  const company = companies.find((option) => option.code === companyCode);
  const action = company ? `@/empresa/${company.code}/${segment}${editing ? `/${editing.id}` : ''}` : '#';
  const roleName = role === 'customer' ? 'Cliente' : 'Fornecedor';
  const roleTag = { id: 0, name: roleName, color: roleTagColor, locked: true, displayOnly: true };
  const noticePerson = found ?? departingPerson;
  const noticeLeaving = !found;
  const existing = editing ?? found;
  const operation = editing ? 'Editar' : 'Cadastrar';

  const cancelLookup = useCallback(() => {
    window.clearTimeout(lookupTimer.current);
    lookupRequest.current?.abort();
    lookupRequest.current = null;
  }, []);

  function resetLookup() {
    cancelLookup();
    setSearching(false);
    if (found) setDepartingPerson(found);
    setFound(undefined);
    setLookupError('');
  }

  function selectCompany(code: string, checked: boolean) {
    const next = checked ? [...selectedCompanies, code] : selectedCompanies.filter((value) => value !== code);
    setSelectedCompanies(next);
    if (!editing && next[0] !== companyCode) resetLookup();
  }

  function changeDocument(value: string, type: PersonDocumentType) {
    const next = normalizeTaxDocument(value);
    if (next === document && type === documentType) {
      setLookupError('');
      return;
    }

    resetLookup();
    setDocument(next);
    setDocumentType(type);
  }

  function discardPerson() {
    resetLookup();
    setDocument('');
    setDraft((current) => current + 1);
  }

  const search = useCallback(async (query: string, type: PersonDocumentType) => {
    window.clearTimeout(lookupTimer.current);
    if (lookupRequest.current) return;

    if (!isCompleteDocument(query, type)) {
      setLookupError(type === 'company' ? 'Informe um CNPJ válido.' : 'Informe um CPF válido.');
      return;
    }

    if (action === '#') {
      setLookupError('Selecione a empresa responsável.');
      return;
    }

    const request = new AbortController();
    lookupRequest.current = request;
    setSearching(true);
    setLookupError('');
    try {
      const result = await apiRequest<{ person: FoundPerson | null }>(`${action}/pessoa`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ documento: query, person_type: type }),
        signal: request.signal,
      });
      if (!request.signal.aborted) {
        setDepartingPerson(undefined);
        setFound(result.person ?? undefined);
      }
    } catch (failure) {
      if (!request.signal.aborted) {
        setLookupError(failure instanceof ApiError
          ? failure.errors.documento ?? failure.errors.person_type ?? failure.message
          : 'Não foi possível buscar a pessoa.');
      }
    } finally {
      if (!request.signal.aborted) {
        lookupRequest.current = null;
        setSearching(false);
      }
    }
  }, [action]);

  useEffect(() => {
    if (found || action === '#' || !isCompleteDocument(document, documentType)) return;

    lookupTimer.current = window.setTimeout(() => void search(document, documentType), DOCUMENT_LOOKUP_DELAY);
    return () => window.clearTimeout(lookupTimer.current);
  }, [action, document, documentType, found, search]);

  useEffect(() => cancelLookup, [cancelLookup]);

  function searchButton(value: string, type: PersonDocumentType, disabled: boolean) {
    return (
      <LookupButton label={type === 'company' ? 'Buscar por CNPJ' : 'Buscar por CPF'} loading={searching}
        disabled={disabled} onClick={() => void search(normalizeTaxDocument(value), type)} />
    );
  }

  function documentNotice(processing: boolean) {
    if (!noticePerson) return null;

    return (
      <div className="commercial-registration__notice">
        <div
          className="commercial-registration__notice-content"
          data-leaving={noticeLeaving}
          inert={noticeLeaving}
          onAnimationEnd={(event) => {
            if (event.target === event.currentTarget && noticeLeaving) setDepartingPerson(undefined);
          }}
        >
          <div role="status">
            <FormHelp className={noticePerson.relation ? undefined : 'commercial-registration__notice-match'}>
              <span title={noticePerson.name}>
                <strong>{noticePerson.name}</strong> já tem cadastro
                {noticePerson.other_role ? ` como ${noticePerson.other_role.toLowerCase()}` : ''}.
              </span>
              {noticePerson.relation === 'active' && ` Já está cadastrada como ${singular} na primeira empresa selecionada.`}
              {noticePerson.relation === 'inactive' && ' O vínculo na primeira empresa selecionada está inativo.'}
            </FormHelp>
            <FormHelp>
              Deseja utilizar outro{' '}
              <span className="commercial-registration__notice-question-end">
                cadastro?
                {noticePerson.central && !noticePerson.relation
                  && (noticePerson.can_edit_identification || noticePerson.can_edit_contacts) && (
                  <InfoTooltip
                    label="o cadastro compartilhado"
                    text="As alterações valem para o cadastro compartilhado da conta."
                  />
                )}
              </span>
            </FormHelp>
          </div>
          <Button tone="warning" size="sm" labelIconClass="fa fa-undo" disabled={processing}
            onClick={discardPerson}>Descartar</Button>
        </div>
      </div>
    );
  }

  return (
    <SaasLayout tenant title={`${operation} ${singular}`}
      breadcrumb={[{ label: listTitle, href: listUrl }, { label: operation }]}>
      <CardBox>
        <BusinessForm action={action} method={editing ? 'patch' : 'post'} options={{ successHref: listUrl }}
          onSubmitCapture={(event) => {
            window.clearTimeout(lookupTimer.current);
            if (lookupRequest.current) {
              event.preventDefault();
              event.stopPropagation();
            }
          }}
          className="business-form--wide-labels" data-tour="commercial-registration-form">
          {({ errors, processing }) => (
            <>
              {(editing || companies.length > 1) && (
                <BusinessFormSection title="Empresas" tourTarget="commercial-registration-company">
                  {editing && <Field label="Empresa desta ficha" value={company?.name ?? ''} readOnly />}
                  {options.length > 1 && (
                    <>
                      <SelectChecklist label="Empresas do cadastro" menuLabel="Selecionar empresas"
                        options={options.map((option) => ({
                          id: option.code,
                          label: option.name,
                          checked: selectedCompanies.includes(option.code),
                          disabled: option.disabled || processing,
                        }))}
                        onChange={selectCompany} />
                      <FormHelp className="business-form__control-start">
                        {options.filter((option) => selectedCompanies.includes(option.code)).map((option) => option.name).join(', ')
                          || 'Nenhuma empresa selecionada'}
                      </FormHelp>
                      {editing && <FormHelp className="business-form__control-start">Desmarcar uma empresa inativa o vínculo nela.</FormHelp>}
                    </>
                  )}
                  {Object.entries(errors).filter(([key]) => /^(add|remove)_companies(?:\.|$)/.test(key)).map(([key, error]) =>
                    <p className="formField__error" role="alert" key={key}>{error}</p>)}
                  {editing && (editing.can_change_status ? (
                    <Select label="Situação" value={isActive ? '1' : '0'} error={errors.active}
                      onChange={(value) => selectCompany(companyCode, value === '1')}
                      options={[{ value: '1', label: 'Ativo' }, { value: '0', label: 'Inativo' }]} />
                  ) : <Field label="Situação" value={editing.active ? 'Ativo' : 'Inativo'} readOnly />)}
                  {editing && isActive !== editing.active && (
                    <input type="hidden" name="active" value={isActive ? '1' : '0'} />
                  )}
                  {editing && !editing.central && <Field label="Nome" value={editing.name} readOnly />}
                  {options.filter((option) => option.code !== companyCode
                    && selectedCompanies.includes(option.code) !== option.active).map((option) =>
                    <input key={option.code} type="hidden" value={option.code}
                      name={selectedCompanies.includes(option.code) ? 'add_companies[]' : 'remove_companies[]'} />)}
                </BusinessFormSection>
              )}
              {canCreatePeople && (
                <fieldset className="person-form__group" hidden={Boolean(found)} disabled={Boolean(found) || processing}>
                  <PersonIdentityFields key={draft} person={null} errors={errors}
                    documentAction={(value, type) => searchButton(value, type, processing)}
                    onDocumentChange={changeDocument} documentLookupError={lookupError}
                    documentAside={!found && documentNotice(processing)} />
                </fieldset>
              )}
              {!editing && ((!canCreatePeople && !found) || (found && !found.central)) && (
                <BusinessFormSection title="Dados da pessoa" tourTarget="person-identity">
                  {found ? (
                    <Field label="Tipo de pessoa" value={documentType === 'company' ? 'Pessoa jurídica' : 'Pessoa física'} readOnly />
                  ) : (
                    <Select label="Tipo de pessoa" value={documentType} required
                      options={[{ value: 'individual', label: 'Pessoa física' }, { value: 'company', label: 'Pessoa jurídica' }]}
                      onChange={(value) => changeDocument('', value === 'company' ? 'company' : 'individual')} />
                  )}
                  <TaxDocumentField personType={documentType} value={document} required={!found}
                    readOnly={Boolean(found)} disabled={processing}
                    error={lookupError || errors.person_id}
                    onChange={(event) => changeDocument(event.target.value, documentType)}
                    action={searchButton(document, documentType, Boolean(found) || processing)}
                    controlAside={documentNotice(processing)} />
                </BusinessFormSection>
              )}
              {existing?.central && (
                <div key={existing.id}>
                  <PersonIdentityFields person={existing.central} errors={errors} documentLocked={!editing}
                    identificationDisabled={!existing.can_edit_identification || blocksLink || processing}
                    documentAction={editing ? undefined : (value, type) => searchButton(value, type, true)}
                    documentLookupError={errors.person_id}
                    documentAside={documentNotice(processing)} />
                  <fieldset className="person-form__group"
                    disabled={!existing.can_edit_contacts || blocksLink || processing}>
                    <AddressFields initial={existing.central.address ?? undefined}
                      errors={errors} namePrefix="address" errorPrefix="address" tourTarget="person-address" />
                    <PersonContactFields person={existing.central} errors={errors} access={false} />
                  </fieldset>
                </div>
              )}
              {canCreatePeople && !found && <>
                <AddressFields errors={errors} namePrefix="address" errorPrefix="address" tourTarget="person-address" />
                <PersonContactFields person={null} errors={errors} access={false} />
              </>}
              {found && !blocksLink && <input type="hidden" name="person_id" value={found.id} />}
              <BusinessFormSection title="Etiquetas" tourTarget="person-tags">
                {editing && <input type="hidden" name="edit_tags" value="1" />}
                <TagSelect
                  options={[roleTag, ...registrationTags]}
                  selected={[roleTag.id, ...selectedTags]}
                  onChange={(ids) => setSelectedTags(ids.filter((id) => id !== roleTag.id))}
                  disabled={blocksLink || processing}
                  error={Object.entries(errors).find(([key]) => key === 'tag_ids' || key.startsWith('tag_ids.'))?.[1]}
                />
              </BusinessFormSection>
              <BusinessFormActions
                processing={processing}
                submitDisabled={!company || searching || blocksLink || (!editing && !found && !canCreatePeople)}
                cancelHref={listUrl}
              />
            </>
          )}
        </BusinessForm>
      </CardBox>
    </SaasLayout>
  );
}
