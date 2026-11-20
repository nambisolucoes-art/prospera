import BusinessForm, { BusinessFormActions, BusinessFormSection } from '@/components/BusinessForm/BusinessForm';
import CardBox from '@/components/CardBox/CardBox';
import SaasLayout from './SaasLayout';
import UserFields from './UserFields';

export default function UserForm({ tenant }: { tenant: boolean }) {
  const action = tenant ? '/conta/usuarios' : '/saas/usuarios';
  return (
    <SaasLayout title={tenant ? 'Cadastrar usuário da conta' : 'Cadastrar administrador da plataforma'} tenant={tenant} userPage
      breadcrumb={[{ label: 'Usuários', href: action }, { label: 'Cadastrar' }]}>
      <CardBox>
        <BusinessForm className="business-form--wide-labels" action={action} method="post">
          {({ errors, processing }) => (
            <>
              <BusinessFormSection title="Dados do usuário">
                <UserFields errors={errors} accountRoles={tenant} />
              </BusinessFormSection>
              <BusinessFormActions processing={processing} cancelHref={action} />
            </>
          )}
        </BusinessForm>
      </CardBox>
    </SaasLayout>
  );
}
