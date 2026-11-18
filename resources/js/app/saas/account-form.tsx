import CardBox from '@/components/CardBox/CardBox';
import ClientForm from './ClientForm';
import type { ClientData } from './ClientForm.types';
import SaasLayout from './SaasLayout';

export default function AccountFormPage({ client, tenant = false }: { client?: ClientData; tenant?: boolean }) {
  const title = tenant ? 'Editar estabelecimento' : client ? 'Editar conta' : 'Cadastrar conta';
  return (
    <SaasLayout title={title} tenant={tenant}
      breadcrumb={tenant ? [{ label: title }] : [{ label: 'Contas', href: '/dashboard' }, { label: title }]}>
      <CardBox><ClientForm key={client?.id ?? 'new'} client={client} tenant={tenant} /></CardBox>
    </SaasLayout>
  );
}
