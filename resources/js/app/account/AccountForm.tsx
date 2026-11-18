import Form from '@/components/ApiForm/ApiForm';
import Head from '@/components/PageTitle/PageTitle';
import { useId, type ComponentProps, type ReactNode } from 'react';
import AccountCard from '@/components/AccountCard/AccountCard';
import AccountLayout from '@/components/AccountLayout/AccountLayout';
import BusinessForm, { BusinessFormActions } from '@/components/BusinessForm/BusinessForm';
import { Field as BusinessField } from '@/components/BusinessForm/BusinessFormFields';
import Button, { type ButtonTone } from '@/components/Button/Button';
import FormField from '@/components/FormField/FormField';
import TextInput from '@/components/TextInput/TextInput';
import './AccountForm.css';

export type Field = {
  name: string;
  label: string;
  type?: string;
  autoComplete?: string;
  value?: string;
  placeholder?: string;
  required?: boolean;
};

type FormProps = {
  action: string;
  method?: ComponentProps<typeof Form>['method'];
  fields?: Field[];
  submit: string;
  submitTone?: ButtonTone;
  children?: ReactNode;
  afterSubmit?: ReactNode;
  compact?: boolean;
  business?: boolean;
  wideLabels?: boolean;
  inlineActions?: boolean;
};

export function AccountForm({ action, method = 'post', fields = [], submit, submitTone = 'primary', children, afterSubmit, compact = false, business = false, wideLabels = false, inlineActions = false }: FormProps) {
  const formId = useId();
  const FormComponent = business ? BusinessForm : Form;
  const formClass = [
    'prospera-account-form',
    compact && 'prospera-account-form--compact',
    business && wideLabels && 'business-form--wide-labels',
  ].filter(Boolean).join(' ');

  return (
    <FormComponent noValidate action={action} method={method} className={formClass} resetOnSuccess={fields.filter(({ type }) => type === 'password').map(({ name }) => name)}>
      {({ errors, processing, recentlySuccessful }) => (
        <>
          {fields.map(({ name, label, type = 'text', autoComplete, value, placeholder, required = true }) => business ? (
            <BusinessField key={name} name={name} label={label} type={type} autoComplete={autoComplete}
              defaultValue={value} placeholder={placeholder} required={required} error={errors[name]} />
          ) : (
            <FormField key={name} id={`${formId}-${name}`} label={label} error={errors[name]} passiveLabel>
              <TextInput
                id={`${formId}-${name}`}
                name={name}
                type={type}
                autoComplete={autoComplete}
                defaultValue={value}
                placeholder={placeholder}
                invalid={Boolean(errors[name])}
                aria-describedby={errors[name] ? `${formId}-${name}-error` : undefined}
                aria-labelledby={`${formId}-${name}-label`}
                required={required}
              />
            </FormField>
          ))}
          {children}
          {Object.entries(errors).filter(([name]) => name !== '_form' && !fields.some((field) => field.name === name))
            .map(([name, message]) => <p key={name} className="prospera-form-error" role="alert">{message}</p>)}
          {recentlySuccessful && <p role="status">Alterações enviadas.</p>}
          {business ? <BusinessFormActions submit={submit} processing={processing} tone={submitTone} inline={inlineActions} />
            : <div><Button type="submit" tone={submitTone} className="prospera-account-form__submit" disabled={processing}>{submit}</Button></div>}
          {afterSubmit}
        </>
      )}
    </FormComponent>
  );
}

export function AccountScreen({ title, children, footer }: { title: string; children: ReactNode; footer?: ReactNode }) {
  return (
    <AccountLayout>
      <Head title={title} />
      <AccountCard title={title} brand={<img className="prospera-account-logo" src="/assets/brand/prospera.svg" alt="Prospera" width="2172" height="724" />}>{children}</AccountCard>
      {footer && <nav className="prospera-account-links" aria-label="Conta">{footer}</nav>}
    </AccountLayout>
  );
}
