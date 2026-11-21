import { useEffect, useState, type ReactNode } from 'react';
import { BusinessFormSection } from '@/components/BusinessForm/BusinessForm';
import { Field, Select, type Errors } from '@/components/BusinessForm/BusinessFormFields';
import TaxDocumentField from '@/components/BusinessForm/TaxDocumentField';
import type { PersonDocumentType } from '@/utils/formatTaxDocument';
import type { PersonRow } from './Directory.types';

export default function PersonIdentityFields({
  person, errors, documentAction, documentAside, onDocumentChange, documentLocked = false, identificationDisabled = false,
  documentLookupError,
}: {
  person: PersonRow | null;
  documentLocked?: boolean;
  identificationDisabled?: boolean;
  errors: Errors;
  onDocumentChange?: (document: string, type: PersonDocumentType) => void;
  documentAction?: (document: string, type: PersonDocumentType) => ReactNode;
  documentAside?: ReactNode;
  documentLookupError?: string;
}) {
  const [type, setType] = useState(person?.person_type ?? 'individual');
  const [document, setDocument] = useState(person?.document ?? '');
  const [documentError, setDocumentError] = useState<string | undefined>(errors.document);
  useEffect(() => setDocumentError(errors.document), [errors]);
  const company = type === 'company';
  return <BusinessFormSection title="Dados da pessoa" tourTarget="person-identity">
    {documentLocked ? (
      <Field label="Tipo de pessoa" value={company ? 'Pessoa jurídica' : 'Pessoa física'} readOnly />
    ) : (
      <Select label="Tipo de pessoa" name="person_type" value={type} required error={errors.person_type}
      options={[{ value: 'individual', label: 'Pessoa física' }, { value: 'company', label: 'Pessoa jurídica' }]}
      onChange={(value) => {
        const nextType = value === 'company' ? 'company' : 'individual';
        const nextDocument = nextType === person?.person_type ? person.document ?? '' : '';
        setType(nextType);
        setDocument(nextDocument);
        setDocumentError(undefined);
        onDocumentChange?.(nextDocument, nextType);
      }} />
    )}
    <Field label={company ? 'Razão social' : 'Nome'} name="name" defaultValue={person?.name}
      error={errors.name} required disabled={identificationDisabled} />
    <TaxDocumentField personType={type} name={documentLocked ? undefined : 'document'} readOnly={documentLocked}
      info={documentLocked ? 'Documento usado para localizar o cadastro.' : 'Documento opcional. Quando informado, deve ser válido e único nesta conta. CNPJ numérico ou alfanumérico é aceito.'}
      action={documentAction?.(document, type)} controlAside={documentAside} value={document}
      onChange={(event) => {
        setDocument(event.target.value);
        setDocumentError(undefined);
        onDocumentChange?.(event.target.value, type);
      }}
      error={documentLookupError || documentError} />
    {company && <>
      <Field label="Nome fantasia" name="trade_name" defaultValue={person?.trade_name ?? ''}
        error={errors.trade_name} disabled={identificationDisabled} />
    </>}
    <Field label="Inscrição estadual" name="state_registration" defaultValue={person?.state_registration ?? ''}
      maxLength={30} error={errors.state_registration} disabled={identificationDisabled} />
    <Field label="Inscrição municipal" name="municipal_registration" defaultValue={person?.municipal_registration ?? ''}
      maxLength={30} error={errors.municipal_registration} disabled={identificationDisabled} />
  </BusinessFormSection>;
}
