import type { ReactNode } from 'react';
import InfoTooltip from '@/components/InfoTooltip/InfoTooltip';
import { joinClassNames } from '@/utils/joinClassNames';
import './FormField.css';

export interface FormFieldProps {
  id: string;
  label: string;
  children: ReactNode;
  layout?: 'stacked' | 'horizontal';
  hint?: string;
  info?: string;
  error?: string;
  className?: string;
  requiredIndicator?: boolean;
  passiveLabel?: boolean;
}

export default function FormField({
  id, label, children, layout = 'stacked', hint, info, error, className, requiredIndicator = false, passiveLabel = false,
}: FormFieldProps): JSX.Element {
  const labelText = <>
    {requiredIndicator && <span className="formField__required" aria-hidden="true">*</span>}
    {label}
  </>;

  return (
    <div className={joinClassNames('formField', `formField--${layout}`, className)}>
      <div className="formField__labelRow">
        {passiveLabel ? (
          <span id={`${id}-label`} className="formField__label" onPointerDown={(event) => event.preventDefault()}>
            {labelText}
          </span>
        ) : <label className="formField__label" htmlFor={id}>{labelText}</label>}
        {info && <InfoTooltip text={info} label={label} />}
      </div>
      <div className="formField__control">
        {children}
        {hint ? <span id={`${id}-hint`} className="formField__hint">{hint}</span> : null}
        {error ? <span id={`${id}-error`} className="formField__error" role="alert">{error}</span> : null}
      </div>
    </div>
  );
}
