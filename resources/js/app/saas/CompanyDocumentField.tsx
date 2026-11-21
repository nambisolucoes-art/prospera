import { Field } from '@/components/BusinessForm/BusinessFormFields';
import { formatTaxDocument } from '@/utils/formatTaxDocument';
import { LookupButton } from './ClientFormFields';
import type { useCnpjLookup } from './useCnpjLookup';

export default function CompanyDocumentField({ name, type, document, onChange, lookup, error }: {
  name: string;
  type: string;
  document: string;
  onChange: (document: string) => void;
  lookup: ReturnType<typeof useCnpjLookup>;
  error?: string;
}) {
  const legalEntity = type === 'company';

  return (
    <Field
      label={legalEntity ? 'CNPJ' : 'CPF'}
      name={name}
      info={legalEntity
        ? 'Informe a raiz de 8 posições ou o CNPJ completo de 14 posições. A consulta cadastral exige o CNPJ numérico completo e preenche os nomes que ainda estiverem vazios.'
        : 'Informe um CPF válido para identificar a empresa.'}
      compact
      required
      maxLength={18}
      inputMode={legalEntity ? 'text' : 'numeric'}
      placeholder={legalEntity ? 'CNPJ completo ou raiz' : '000.000.000-00'}
      value={document ? formatTaxDocument(document, type) : ''}
      onChange={(event) => {
        onChange(event.target.value);
        lookup.reset();
      }}
      action={legalEntity && (
        <LookupButton label="Buscar CNPJ" loading={lookup.loading} onClick={() => void lookup.lookup(document)} />
      )}
      error={lookup.error || error}
    />
  );
}
