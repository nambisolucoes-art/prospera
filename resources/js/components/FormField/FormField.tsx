import type { ReactNode } from 'react';
import { joinClassNames } from '@/utils/joinClassNames';
import './FormField.css';

export interface FormFieldProps {
  id: string;
  label: string;
  children: ReactNode;
  layout?: 'stacked' | 'horizontal';
  hint?: string;
  error?: string;
  className?: string;
  requiredIndicator?: boolean;
  passiveLabel?: boolean;
}

export default function FormField({
  id, label, children, layout = 'stacked', hint, error, className, requiredIndicator = false, passiveLabel = false,
}: FormFieldProps): JSX.Element {
  const labelText = <>
    {requiredIndicator && <span className="formField__required" aria-hidden="true">*</span>}
    {label}
  </>;

  return (
    <div className={joinClassNames('formField', `formField--${layout}`, className)}>
      {passiveLabel ? (
        <span id={`${id}-label`} className="formField__label" onPointerDown={(event) => event.preventDefault()}>
          {labelText}
        </span>
      ) : <label className="formField__label" htmlFor={id}>{labelText}</label>}
      <div className="formField__control">
        {children}
        {hint ? <span id={`${id}-hint`} className="formField__hint">{hint}</span> : null}
        {error ? <span id={`${id}-error`} className="formField__error" role="alert">{error}</span> : null}
      </div>
    </div>
  );
}
