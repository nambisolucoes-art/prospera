import type { HTMLAttributes, ReactNode } from 'react';
import CardBox from '@/components/CardBox/CardBox';
import './FormSection.css';

interface FormSectionProps { title: string; description?: ReactNode; children: ReactNode; }
export default function FormSection({ title, description, children }: FormSectionProps): JSX.Element {
  return <CardBox className="formSection">
    <h4 className="formSection__title"><b>{title}</b></h4>
    {description ? <FormSectionDescription>{description}</FormSectionDescription> : null}
    {children}
  </CardBox>;
}

export function FormDemoDescription(): JSX.Element {
  return <>Use as classes de botão em um elemento <code>&lt;a&gt;</code>, <code>&lt;button&gt;</code> ou <code>&lt;input&gt;</code>.</>;
}

export function FormSectionDescription({ className = '', ...props }: HTMLAttributes<HTMLParagraphElement>) {
  return <p {...props} className={`formSection__description ${className}`} />;
}
