import { forwardRef, useId, useState, type InputHTMLAttributes } from 'react';
import { joinClassNames } from '@/utils/joinClassNames';
import InputGroup from '@/components/InputGroup/InputGroup';
import './TextInput.css';

export interface TextInputProps extends InputHTMLAttributes<HTMLInputElement> {
  invalid?: boolean;
}

const TextInput = forwardRef<HTMLInputElement, TextInputProps>(function TextInput(
  { className, invalid = false, type = 'text', ...props }, ref
): JSX.Element {
  const generatedId = useId();
  const [visible, setVisible] = useState(false);
  const password = type === 'password';
  const id = props.id ?? generatedId;
  const input = (
    <input
      {...props}
      id={id}
      ref={ref}
      type={password && visible ? 'text' : type}
      className={joinClassNames('textInput', className)}
      aria-invalid={invalid || undefined}
    />
  );

  if (!password) return input;

  const label = visible ? 'Ocultar senha' : 'Mostrar senha';
  const toggle = (
    <button
      type="button"
      className="inputGroup__button waves-effect"
      aria-label={label}
      title={label}
      aria-controls={id}
      aria-pressed={visible}
      disabled={props.disabled}
      onClick={() => setVisible((current) => !current)}
    >
      <i className={visible ? 'fa fa-eye-slash' : 'fa fa-eye'} aria-hidden="true" />
    </button>
  );

  return (
    <div className="textInputPassword" data-tour="password-visibility">
      <InputGroup action={toggle}>{input}</InputGroup>
    </div>
  );
});

export default TextInput;
