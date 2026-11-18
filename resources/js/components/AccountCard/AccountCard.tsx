import type { ReactNode } from 'react';
import CardBox from '@/components/CardBox/CardBox';
import './AccountCard.css';

export default function AccountCard({ title, brand, children }: {
  title?: ReactNode;
  brand?: ReactNode;
  children: ReactNode;
}): JSX.Element {
  return <CardBox className="accountCard">
    {brand || title ? <div className="accountCard__heading">{brand}{title && <h3>{title}</h3>}</div> : null}
    <div className="accountCard__body">{children}</div>
  </CardBox>;
}
