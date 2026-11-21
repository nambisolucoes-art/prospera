import type { Errors } from '@/components/BusinessForm/BusinessFormFields';
import TaxDocumentField from '@/components/BusinessForm/TaxDocumentField';
import { normalizeTaxDocument } from '@/utils/formatTaxDocument';
import { LookupButton } from './ClientFormFields';
import type { useCnpjLookup } from './useCnpjLookup';

export default function CompanyDocumentField({ name, type, document, onChange, lookup, errors, errorKey, confirmNames = false }: {
  name: string;
  type: string;
  document: string;
  onChange: (document: string) => void;
  lookup: ReturnType<typeof useCnpjLookup>;
  errors: Errors;
  errorKey?: string;
  confirmNames?: boolean;
}) {
  const legalEntity = type === 'company';

  return (
    <TaxDocumentField
      personType={legalEntity ? 'company' : 'individual'}
      name={name}
      label={confirmNames && legalEntity && normalizeTaxDocument(document).length === 8 ? 'Raiz do CNPJ' : undefined}
      info={legalEntity
        ? (confirmNames
          ? 'Informe a raiz de 8 posições ou o CNPJ completo de 14 posições. A lupa consulta os nomes oficiais para você decidir se quer usar.'
          : 'Informe a raiz de 8 posições ou o CNPJ completo de 14 posições. A consulta cadastral exige o CNPJ numérico completo e preenche os nomes que ainda estiverem vazios.')
        : 'Informe um CPF válido para identificar a empresa.'}
      required
      placeholder={legalEntity ? 'CNPJ completo ou raiz' : undefined}
      value={document}
      onChange={(event) => {
        onChange(event.target.value);
        lookup.reset();
      }}
      action={legalEntity && (
        <LookupButton label="Buscar CNPJ" loading={lookup.loading} onClick={() => void lookup.lookup(document)} />
      )}
      error={lookup.error}
      errors={errors}
      errorKey={errorKey}
    />
  );
}
