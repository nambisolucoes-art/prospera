import { useState } from 'react';
import { formatPersonDocumentInput, taxDocumentProblem, type PersonDocumentType } from '@/utils/formatTaxDocument';
import { Field, type Errors, type FieldProps } from './BusinessFormFields';

type Props = Omit<FieldProps, 'label' | 'value' | 'inputMode' | 'maxLength' | 'compact'> & {
  personType: PersonDocumentType;
  label?: string;
  value: string;
  errors?: Errors;
  errorKey?: string;
};

export default function TaxDocumentField({
  personType, value, error, errors, errorKey, name, onChange, placeholder, label, ...props
}: Props) {
  const [dismissedErrors, setDismissedErrors] = useState<Errors>();
  const company = personType === 'company';
  const validationError = errors !== dismissedErrors ? errors?.[errorKey ?? name ?? 'document'] : undefined;
  // Documento salvo e somente leitura não recebe aviso de digitação.
  const typingProblem = props.readOnly ? undefined : taxDocumentProblem(value, personType);

  return (
    <Field {...props}
      label={label ?? (company ? 'CNPJ' : 'CPF')}
      name={name}
      compact
      inputMode={company ? 'text' : 'numeric'}
      maxLength={18}
      placeholder={placeholder ?? (company ? 'Numérico ou alfanumérico' : '000.000.000-00')}
      value={formatPersonDocumentInput(value, personType)}
      error={error || typingProblem || validationError}
      onChange={(event) => {
        setDismissedErrors(errors);
        onChange?.(event);
      }}
    />
  );
}
