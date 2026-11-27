import { useId, useState, type InputHTMLAttributes, type ReactNode } from 'react';
import ColorPicker from '@/components/ColorPicker/ColorPicker';
import DatePicker from '@/components/DatePicker/DatePicker';
import ClockPicker from '@/components/ClockPicker/ClockPicker';
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

/**
 * Data no formulário de negócio: calendário do tema Bold, digitação com máscara DD/MM/AAAA e envio de `AAAA-MM-DD` em `name`.
 * Controlado (`value` e `onChange`) ou não (`defaultValue`). Nunca `type="date"` do navegador.
 */
export function DateField({ label, info, error, name, value, defaultValue = '', onChange, required, min, max, disabled }: {
  label: string; info?: string; error?: string; name: string; value?: string; defaultValue?: string;
  onChange?: (value: string) => void; required?: boolean; min?: string; max?: string; disabled?: boolean;
}) {
  const id = useId();
  const [own, setOwn] = useState(defaultValue);
  const current = value ?? own;

  return (
    <FormField id={id} label={label} info={info} error={error} layout="horizontal" requiredIndicator={required} passiveLabel>
      <div className="business-form__short-control">
        <DatePicker id={id} label={label} name={name} value={current} min={min} max={max} required={required} disabled={disabled}
          invalid={Boolean(error)} describedBy={error ? `${id}-error` : undefined}
          onChange={(next) => { setOwn(next); onChange?.(next); }} />
      </div>
    </FormField>
  );
}

/** Hora (24 h, `HH:MM`) no formulário de negócio, com o relógio circular do tema Bold. */
export function TimeField({ label, info, error, value, onChange, required, disabled }: {
  label: string; info?: string; error?: string; value: string; onChange: (value: string) => void; required?: boolean; disabled?: boolean;
}) {
  const id = useId();

  return (
    <FormField id={id} label={label} info={info} error={error} layout="horizontal" requiredIndicator={required} passiveLabel>
      <div className="business-form__short-control">
        <ClockPicker id={id} label={label} value={value} required={required} disabled={disabled} invalid={Boolean(error)} onChange={onChange} />
      </div>
    </FormField>
  );
}

/** Cor hexadecimal (`#rrggbb`) no formulário de negócio, com o seletor de cor do tema Bold. */
export function ColorField({ label, error, name, defaultValue, required = false }: {
  label: string; error?: string; name: string; defaultValue: string; required?: boolean;
}) {
  return <ColorPicker label={label} name={name} defaultValue={defaultValue} error={error} required={required} />;
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

