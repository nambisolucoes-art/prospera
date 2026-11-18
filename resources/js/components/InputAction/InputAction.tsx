import type { InputHTMLAttributes } from 'react';
import TextInput from '@/components/TextInput/TextInput';
import Button, { type ButtonTone } from '@/components/Button/Button';
import './InputAction.css';

export default function InputAction({ label, tone = "pink", submitting = false, invalid = false, ...inputProps }: InputHTMLAttributes<HTMLInputElement> & {
  label: string;
  tone?: ButtonTone;
  submitting?: boolean;
  invalid?: boolean;
}): JSX.Element {
  return <div className="inputAction">
    <TextInput {...inputProps} invalid={invalid} />
    <Button type="submit" tone={tone} disabled={submitting} width="sm" label={label} />
  </div>;
}
