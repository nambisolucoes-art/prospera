import { BusinessFormRow, BusinessFormSection } from '@/components/BusinessForm/BusinessForm';
import { Field, type Errors } from '@/components/BusinessForm/BusinessFormFields';
import { AdditionalContacts } from './ContactFields';
import { MaskedField } from './ClientFormFields';
import type { PersonRow } from './Directory.types';

export default function PersonContactFields({ person, errors, access }: { person: PersonRow | null; errors: Errors; access: boolean }) {
  return <BusinessFormSection title="Informações de contato" tourTarget="person-contact">
    <BusinessFormRow>
      <MaskedField mask="phone" label="Telefone" name="phone" type="tel" leadingIcon="fa fa-phone" defaultValue={person?.phone ?? ''} autoComplete="tel" error={errors.phone} />
      <MaskedField mask="phone" label="WhatsApp" name="whatsapp" type="tel" leadingIcon="fa fa-whatsapp" defaultValue={person?.whatsapp ?? ''} error={errors.whatsapp} />
    </BusinessFormRow>
    <AdditionalContacts kind="phone" initial={person?.additional_phones ?? []} errors={errors} namePrefix="" errorPrefix="" />
    <Field label="E-mail de contato" name="email" type="email" trailingIcon="fa fa-envelope-o" defaultValue={person?.email ?? ''} required={access} error={errors.email} />
    <AdditionalContacts kind="email" initial={person?.additional_emails ?? []} errors={errors} namePrefix="" errorPrefix="" />
    <Field label="Pessoa de contato" name="contact_name" defaultValue={person?.contact_name ?? ''} error={errors.contact_name} />
    <Field label="Site" name="website" type="url" placeholder="https://" defaultValue={person?.website ?? ''} error={errors.website} />
  </BusinessFormSection>;
}
