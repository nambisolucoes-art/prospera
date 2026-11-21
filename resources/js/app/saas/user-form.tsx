import { useState } from 'react';
import { Select } from '@/components/BusinessForm/BusinessFormFields';
import UserPersonPicker, { type PersonCandidate } from './UserPersonPicker';
import BusinessForm, { BusinessFormActions, BusinessFormSection } from '@/components/BusinessForm/BusinessForm';
import TagSelect from '@/components/TagSelect/TagSelect';
import CardBox from '@/components/CardBox/CardBox';
import SaasLayout from './SaasLayout';
import UserFields from './UserFields';
import AssignmentFields, { type AssignmentOption } from './AssignmentFields';

export default function UserForm({ tenant, assignmentOptions = [], canReusePerson = false }: {
  tenant: boolean;
  canReusePerson?: boolean;
  assignmentOptions?: AssignmentOption[];
}) {
  const [mode, setMode] = useState('new');
  const [person, setPerson] = useState<PersonCandidate>();
  const existing = tenant && mode === 'existing';
  const action = tenant ? '/conta/usuarios' : '/saas/usuarios';
  return (
    <SaasLayout title={tenant ? 'Cadastrar usuário da conta' : 'Cadastrar administrador da plataforma'} tenant={tenant} userPage
      breadcrumb={[{ label: 'Usuários', href: action }, { label: 'Cadastrar' }]}>
      <CardBox>
        <BusinessForm className="business-form--wide-labels" action={action} method="post">
          {({ errors, processing }) => (
            <>
              <BusinessFormSection title="Dados do usuário" tourTarget="user-registration">
                {tenant && canReusePerson && (
                  <Select label="Tipo de cadastro" name="person_registration" value={mode}
                    options={[
                      { value: 'new', label: 'Pessoa ainda não cadastrada' },
                      { value: 'existing', label: 'Pessoa já cadastrada' },
                    ]}
                    onChange={(value) => { setMode(value); setPerson(undefined); }} />
                )}
                {existing && <UserPersonPicker value={person} onChange={setPerson} error={errors.person_id} />}
                {(!existing || person) && (
                  <UserFields key={person?.id ?? mode} errors={errors} accountRoles={tenant} person={person} />
                )}
              </BusinessFormSection>
              {tenant && (
                <BusinessFormSection title="Primeira atribuição" tourTarget="user-assignment">
                  <AssignmentFields options={assignmentOptions} errors={errors} required />
                </BusinessFormSection>
              )}
              {tenant && (
                <BusinessFormSection title="Etiquetas" tourTarget="user-tags">
                  <TagSelect
                    options={[{ id: 0, name: 'Usuário', color: '#3563e9', locked: true, displayOnly: true }]}
                    selected={[0]}
                    onChange={() => undefined}
                    disabled
                  />
                </BusinessFormSection>
              )}
              <BusinessFormActions processing={processing || (existing && !person)} cancelHref={action} />
            </>
          )}
        </BusinessForm>
      </CardBox>
    </SaasLayout>
  );
}
