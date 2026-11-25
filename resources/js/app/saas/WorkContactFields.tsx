import { BusinessFormRow } from '@/components/BusinessForm/BusinessForm';
import { Field } from '@/components/BusinessForm/BusinessFormFields';
import { MaskedField } from './ClientFormFields';

// Contatos profissionais no mesmo padrão dos contatos da Pessoa: ícones, telefone e WhatsApp lado a lado.
// Usado no vínculo e na lotação.
export default function WorkContactFields({ names, values, errors, disabled }: {
  names: { email: string; phone: string; whatsapp: string };
  values?: { email: string | null; phone: string | null; whatsapp: string | null };
  errors: { email?: string; phone?: string; whatsapp?: string };
  disabled: boolean;
}) {
  return (
    <>
      <BusinessFormRow>
        <MaskedField mask="phone" label="Telefone profissional" name={names.phone} type="tel" leadingIcon="fa fa-phone"
          defaultValue={values?.phone ?? ''} error={errors.phone} disabled={disabled} />
        <MaskedField mask="phone" label="WhatsApp profissional" name={names.whatsapp} type="tel"
          leadingIcon="fa fa-whatsapp" defaultValue={values?.whatsapp ?? ''} error={errors.whatsapp} disabled={disabled} />
      </BusinessFormRow>
      <Field label="E-mail profissional" name={names.email} type="email" trailingIcon="fa fa-envelope-o"
        defaultValue={values?.email ?? ''} error={errors.email} disabled={disabled} />
    </>
  );
}

