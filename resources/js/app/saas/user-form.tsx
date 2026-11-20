import BusinessForm, { BusinessFormActions, BusinessFormSection } from '@/components/BusinessForm/BusinessForm';
import CardBox from '@/components/CardBox/CardBox';
import SaasLayout from './SaasLayout';
import UserFields from './UserFields';
import AssignmentFields, { type AssignmentOption } from './AssignmentFields';

export default function UserForm({ tenant, assignmentOptions = [] }: {
  tenant: boolean;
  assignmentOptions?: AssignmentOption[];
}) {
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
              {tenant && (
                <BusinessFormSection title="Primeira atribuição" tourTarget="user-assignment">
                  <AssignmentFields options={assignmentOptions} errors={errors} required />
                </BusinessFormSection>
              )}
              <BusinessFormActions processing={processing} cancelHref={action} />
            </>
          )}
        </BusinessForm>
      </CardBox>
    </SaasLayout>
  );
}
