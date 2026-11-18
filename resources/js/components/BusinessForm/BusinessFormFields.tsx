import { useId, type InputHTMLAttributes, type ReactNode } from 'react';
import FormField, { type FormFieldProps } from '@/components/FormField/FormField';
import InputGroup from '@/components/InputGroup/InputGroup';
import SelectControl, { type SelectControlProps, type SelectOption } from '@/components/SelectControl/SelectControl';
import TextInput from '@/components/TextInput/TextInput';

export type Errors = Record<string, string | undefined>;
export type FieldProps = InputHTMLAttributes<HTMLInputElement> & {
  label: string;
  error?: string;
  action?: ReactNode;
  leadingIcon?: string;
  trailingIcon?: string;
  compact?: boolean;
};

export function Field({ label, error, action, leadingIcon, trailingIcon, compact, ...props }: FieldProps) {
  const id = useId();
  const grouped = Boolean(action || leadingIcon || trailingIcon);
  const input = <TextInput {...props} id={id} aria-labelledby={`${id}-label`} invalid={Boolean(error)} aria-describedby={error ? `${id}-error` : undefined}
    className={compact && !grouped ? 'business-form__short-control' : props.className} />;

  return (
    <FormField id={id} label={label} error={error} layout="horizontal" requiredIndicator={props.required} passiveLabel>
      {grouped ? <InputGroup leadingIcon={leadingIcon} trailingIcon={trailingIcon} action={action} compact={compact}>{input}</InputGroup> : input}
    </FormField>
  );
}

export function Select<Option extends SelectOption>({ label, error, layout = 'horizontal', required, ...props }: SelectControlProps<Option> & {
  label: string;
  error?: string;
  layout?: FormFieldProps['layout'];
}) {
  const id = useId();
  return (
    <FormField id={id} label={label} error={error} layout={layout} requiredIndicator={required} passiveLabel>
      <SelectControl id={id} label={label} labelledBy={`${id}-label`} invalid={Boolean(error)} required={required} {...props} />
    </FormField>
  );
}

