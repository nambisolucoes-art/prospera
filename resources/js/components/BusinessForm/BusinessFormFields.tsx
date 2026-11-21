import { useId, type InputHTMLAttributes, type ReactNode } from 'react';
import FormField, { type FormFieldProps } from '@/components/FormField/FormField';
import InputGroup from '@/components/InputGroup/InputGroup';
import SelectControl, { type SelectControlProps, type SelectOption } from '@/components/SelectControl/SelectControl';
import TextInput from '@/components/TextInput/TextInput';

export type Errors = Record<string, string | undefined>;
export type FieldProps = InputHTMLAttributes<HTMLInputElement> & {
  label: string;
  info?: string;
  error?: string;
  action?: ReactNode;
  controlAside?: ReactNode;
  leadingIcon?: string;
  trailingIcon?: string;
  compact?: boolean;
};

export function Field({ label, info, error, action, controlAside, leadingIcon, trailingIcon, compact, ...props }: FieldProps) {
  const id = useId();
  const grouped = Boolean(action || leadingIcon || trailingIcon);
  const input = <TextInput {...props} id={id} aria-labelledby={`${id}-label`} invalid={Boolean(error)} aria-describedby={error ? `${id}-error` : undefined}
    className={compact && !grouped ? 'business-form__short-control' : props.className} />;
  const control = grouped
    ? <InputGroup leadingIcon={leadingIcon} trailingIcon={trailingIcon} action={action} compact={compact}>{input}</InputGroup>
    : input;

  return (
    <FormField id={id} label={label} info={info} error={error} layout="horizontal" requiredIndicator={props.required} passiveLabel>
      {controlAside ? <div className="business-form__control-row">{control}{controlAside}</div> : control}
    </FormField>
  );
}

export function Select<Option extends SelectOption>({ label, info, error, layout = 'horizontal', required, ...props }: SelectControlProps<Option> & {
  label: string;
  info?: string;
  error?: string;
  layout?: FormFieldProps['layout'];
}) {
  const id = useId();
  return (
    <FormField id={id} label={label} info={info} error={error} layout={layout} requiredIndicator={required} passiveLabel>
      <SelectControl id={id} label={label} labelledBy={`${id}-label`} invalid={Boolean(error)} required={required} {...props} />
    </FormField>
  );
}

