import type { ReactNode } from 'react';
import { joinClassNames } from '@/utils/joinClassNames';
import './FormHelp.css';

export default function FormHelp({ children, className }: { children: ReactNode; className?: string }) {
  return <span className={joinClassNames('form-help', className)}><small>{children}</small></span>;
}
