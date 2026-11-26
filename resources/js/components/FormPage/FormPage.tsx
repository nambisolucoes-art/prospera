import type { ReactNode } from 'react';
import PageBreadcrumb from '@/components/PageBreadcrumb/PageBreadcrumb';
import './FormPage.css';

interface FormPageProps { title: string; children: ReactNode; }
export default function FormPage({ title, children }: FormPageProps): JSX.Element {
  return <section className="formPage" aria-label={title}>
    <h4 className="formPage__title">{title}</h4>
    <PageBreadcrumb items={[{ label: 'Início', href: '#' }, { label: 'Formulários', href: '#' }, { label: title }]} />
    {children}
  </section>;
}
