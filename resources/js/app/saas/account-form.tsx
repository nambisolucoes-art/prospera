import { useApiPage } from '@/hooks/useApiPage';
import CardBox from '@/components/CardBox/CardBox';
import ClientForm from './ClientForm';
import type { ClientData } from './ClientForm.types';
import SaasLayout from './SaasLayout';
import { organizationFormNavigation } from './organizationNavigation';

export default function AccountFormPage({ client, tenant = false }: { client?: ClientData; tenant?: boolean }) {
  const { url } = useApiPage();
  const navigation = organizationFormNavigation(url);
  const title = tenant ? 'Dados da conta' : client ? 'Editar conta' : 'Cadastrar conta';
  return (
    <SaasLayout title={title} tenant={tenant}
      breadcrumb={tenant ? [...(navigation.back ? [{ label: 'Organização', href: navigation.back }] : []), { label: title }] : [{ label: 'Contas', href: '/dashboard' }, { label: title }]}>
      <CardBox><ClientForm key={client?.id ?? 'new'} client={client} tenant={tenant} organizationBack={navigation.back} /></CardBox>
    </SaasLayout>
  );
}
