import type { ElementType, ReactNode } from 'react';
import { joinClassNames } from '@/utils/joinClassNames';

interface CardBoxProps {
  as?: ElementType;
  children: ReactNode;
  className?: string;
  'data-tour'?: string;
}

export default function CardBox({
  as: Component = 'div',
  children,
  className,
  'data-tour': tour
}: CardBoxProps): JSX.Element {
  return <Component data-tour={tour} className={joinClassNames('card-box', className)}>{children}</Component>;
}
