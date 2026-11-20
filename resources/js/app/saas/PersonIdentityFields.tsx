import { useEffect, useState } from 'react';
import { BusinessFormSection } from '@/components/BusinessForm/BusinessForm';
import { Field, Select, type Errors } from '@/components/BusinessForm/BusinessFormFields';
import { formatTaxDocument } from '@/utils/formatTaxDocument';
import type { PersonRow } from './Directory.types';

export default function PersonIdentityFields({ person, errors }: { person: PersonRow | null; errors: Errors }) {
  const [type, setType] = useState(person?.person_type ?? 'individual');
  const [document, setDocument] = useState(person?.document ?? '');
  const [documentError, setDocumentError] = useState<string | undefined>(errors.document);
  useEffect(() => setDocumentError(errors.document), [errors]);
  const company = type === 'company';
  return <BusinessFormSection title="Dados da pessoa" tourTarget="person-identity">
    <Select label="Tipo de pessoa" name="person_type" value={type} required error={errors.person_type}
      options={[{ value: 'individual', label: 'Pessoa física' }, { value: 'company', label: 'Pessoa jurídica' }]}
      onChange={(value) => { setType(value === 'company' ? 'company' : 'individual'); setDocumentError(undefined); setDocument(value === person?.person_type ? person.document ?? '' : ''); }} />
    <Field label={company ? 'Razão social' : 'Nome'} name="name" defaultValue={person?.name} error={errors.name} required />
    <Field label={company ? 'CNPJ' : 'CPF'} name="document" info="Documento opcional. Quando informado, deve ser válido e único nesta conta. CNPJ numérico ou alfanumérico é aceito." compact inputMode={company ? 'text' : 'numeric'} maxLength={18}
      value={document ? formatTaxDocument(document, type) : ''} onChange={(event) => { setDocument(event.target.value); setDocumentError(undefined); }} error={documentError}
      placeholder={company ? 'Numérico ou alfanumérico' : '000.000.000-00'} />
    {company && <>
      <Field label="Nome fantasia" name="trade_name" defaultValue={person?.trade_name ?? ''} error={errors.trade_name} />
    </>}
    <Field label="Inscrição estadual" name="state_registration" defaultValue={person?.state_registration ?? ''} maxLength={30} error={errors.state_registration} />
    <Field label="Inscrição municipal" name="municipal_registration" defaultValue={person?.municipal_registration ?? ''} maxLength={30} error={errors.municipal_registration} />
  </BusinessFormSection>;
}
