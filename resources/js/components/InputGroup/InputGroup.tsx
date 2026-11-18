import type { ReactNode } from 'react';
import './InputGroup.css';

type InputGroupProps = {
  children: ReactNode;
  leadingIcon?: string;
  trailingIcon?: string;
  action?: ReactNode;
  compact?: boolean;
};

export default function InputGroup({ children, leadingIcon, trailingIcon, action, compact }: InputGroupProps) {
  return (
    <div className={compact ? 'inputGroup inputGroup--compact' : 'inputGroup'}>
      {leadingIcon && <span className="inputGroup__addon" aria-hidden="true"><i className={leadingIcon} /></span>}
      {children}
      {trailingIcon && <span className="inputGroup__addon" aria-hidden="true"><i className={trailingIcon} /></span>}
      {action}
    </div>
  );
}
