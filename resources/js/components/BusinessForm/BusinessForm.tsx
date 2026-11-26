import { useApiPage } from '@/hooks/useApiPage';
import Form from '@/components/ApiForm/ApiForm';
import Link from '@/components/ApiLink/ApiLink';
import type { ComponentProps, ReactNode } from 'react';
import Button, { type ButtonTone } from '@/components/Button/Button';
import { navigateAfterWave } from '@/utils/initWaves';
import './BusinessForm.css';

export default function BusinessForm({
  className,
  ...props
}: ComponentProps<typeof Form>) {
  return (
    <Form
      data-tour="business-form"
      {...props}
      noValidate
      className={className ? `business-form ${className}` : 'business-form'}
    />
  );
}

export function BusinessFormSection({
  title,
  note,
  children,
  as = 'fieldset',
  tourTarget,
}: {
  title: ReactNode;
  note?: ReactNode;
  children: ReactNode;
  as?: 'fieldset' | 'section';
  tourTarget?: string;
}) {
  const Section = as;
  const Heading = as === 'section' ? 'h2' : 'legend';
  return (
    <Section className="business-form__section" data-tour={tourTarget}>
      <Heading className="business-form__section-heading">{title}</Heading>
      {note && <p className="business-form__section-note">{note}</p>}
      <div className="business-form__section-body">{children}</div>
    </Section>
  );
}

export function BusinessFormRow({ children }: { children: ReactNode }) {
  return <div className="business-form__row">{children}</div>;
}

export function BusinessFormActions({
  submit = 'Salvar',
  processing,
  submitDisabled = false,
  cancelHref,
  onCancel,
  tone = 'success',
  inline = false,
  children,
}: {
  submit?: string;
  processing: boolean;
  submitDisabled?: boolean;
  cancelHref?: string;
  onCancel?: () => void;
  tone?: ButtonTone;
  inline?: boolean;
  children?: ReactNode;
}) {
  const { navigate } = useApiPage();
  const Container = inline ? 'div' : 'footer';
  return (
    <Container
      data-tour="form-actions"
      className={
        inline
          ? 'business-form__actions-inline business-form__control-start'
          : 'business-form__actions'
      }
    >
      {cancelHref && (
        <Link
          href={cancelHref}
          className="button button--white waves-effect"
          onClick={(event) =>
            navigateAfterWave(event, () => void navigate(cancelHref))
          }
        >
          Cancelar
        </Link>
      )}
      {!cancelHref && onCancel && (
        <Button tone="white" disabled={processing} onClick={onCancel}>
          Cancelar
        </Button>
      )}
      <Button type="submit" tone={tone} disabled={processing || submitDisabled}>
        {submit}
      </Button>
      {children}
    </Container>
  );
}
