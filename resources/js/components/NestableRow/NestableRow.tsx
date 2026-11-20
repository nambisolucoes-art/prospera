import type { ComponentPropsWithoutRef } from 'react';
import { joinClassNames } from '@/utils/joinClassNames';
import './NestableRow.css';

export default function NestableRow({ className, ...props }: ComponentPropsWithoutRef<'div'>) {
  return <div {...props} className={joinClassNames('nestable-row', className)} />;
}
