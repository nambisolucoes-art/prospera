import { useState } from 'react';
import BusinessForm, {
  BusinessFormActions,
  BusinessFormSection,
} from '@/components/BusinessForm/BusinessForm';
import Button from '@/components/Button/Button';
import FormHelp from '@/components/FormHelp/FormHelp';
import MembershipAssignmentsModal from './MembershipAssignmentsModal';
import { Field, Select } from '@/components/BusinessForm/BusinessFormFields';
import TagSelect from '@/components/TagSelect/TagSelect';
import PersonIdentityFields from './PersonIdentityFields';
import PersonContactFields from './PersonContactFields';
import { AddressFields } from './ClientFormFields';
import './Directory.css';
import CardBox from '@/components/CardBox/CardBox';
import { useApiPage } from '@/hooks/useApiPage';
import type { SessionProps } from '@/types/Session.types';
import SaasLayout from './SaasLayout';
import type { PersonRow, TagRow } from './Directory.types';
export default function PersonForm({
  person,
  tags,
  roles,
  canEditCentralData = true,
}: {
  person: PersonRow | null;
  tags: TagRow[];
  canEditCentralData?: boolean;
  roles: { id: number; name: string; system_key: string | null }[];
}) {
  const [selected, setSelected] = useState(
    person?.tags.map((tag) => tag.id) ?? [],
  );
  const access = tags.some(
    (tag) => tag.system_key === 'user' && selected.includes(tag.id),
  );
  const customerSelected = tags.some((tag) => tag.system_key === 'customer' && selected.includes(tag.id));
  const supplierSelected = tags.some((tag) => tag.system_key === 'supplier' && selected.includes(tag.id));
  const [mode, setMode] = useState('invite');
  const [editingAssignments, setEditingAssignments] = useState(false);
  const { auth } = useApiPage<SessionProps>().props;
  const readOnly = auth.inspection_mode === 'read-only';
  const canSelectCustomer = person?.has_customer ? auth.permissions.includes('account.customers.activate') : auth.permissions.includes('account.customers.create');
  const canSelectSupplier = person?.has_supplier ? auth.permissions.includes('account.suppliers.activate') : auth.permissions.includes('account.suppliers.create');
  const maySave = !readOnly && canEditCentralData;
  const mayAssign = auth.permissions.includes('account.users.assign-role');
  return (
    <SaasLayout
      tenant
      title={person ? 'Editar pessoa' : 'Cadastrar pessoa'}
      breadcrumb={[
        { label: 'Pessoas', href: auth.permissions.includes('account.people.list') ? '/conta/pessoas' : undefined },
        { label: person ? 'Editar' : 'Cadastrar' },
      ]}
    >
      <CardBox>
        <BusinessForm
          className="business-form--wide-labels"
          action={person ? `/conta/pessoas/${person.id}` : '/conta/pessoas'}
          method={person ? 'patch' : 'post'}
        >
          {({ errors, processing }) => (
            <>
              <fieldset
                disabled={!maySave}
                style={{ border: 0, padding: 0, margin: 0, minWidth: 0 }}
              >
                {!canEditCentralData && (
                  <FormHelp>Dados centrais editáveis por quem atende todas as empresas relacionadas ou pela administração da conta.</FormHelp>
                )}
                <PersonIdentityFields person={person} errors={errors} />
                <AddressFields initial={person?.address ?? undefined} errors={errors} namePrefix="address" errorPrefix="address" tourTarget="person-address" />
                <PersonContactFields person={person} errors={errors} access={access} />
                <BusinessFormSection title="Etiquetas" tourTarget="person-tags">
                  <TagSelect
                    options={tags.map((tag) => ({
                      ...tag,
                      locked: tag.system_key === 'customer'
                        ? (selected.includes(tag.id) && person?.has_customer
                          ? !auth.permissions.includes('account.customers.deactivate')
                          : !canSelectCustomer)
                        : tag.system_key === 'supplier'
                        ? (selected.includes(tag.id) && person?.has_supplier
                          ? !auth.permissions.includes('account.suppliers.deactivate')
                          : !canSelectSupplier)
                        : Boolean(
                          (person?.has_user && selected.includes(tag.id)
                            ? !auth.permissions.includes('account.users.revoke')
                            : person?.has_user
                              ? !auth.permissions.includes('account.users.reactivate')
                              : !auth.permissions.includes('account.users.create') || !mayAssign) && tag.system_key === 'user',
                        ),
                    }))}
                    selected={selected}
                    onChange={setSelected}
                    disabled={readOnly}
                    notice={(person?.tags.some((tag) => tag.system_key === 'customer') && !customerSelected) || (person?.has_user && !access) || (person?.has_supplier && !supplierSelected && person.supplier?.active) ? <>
                      {person?.tags.some((tag) => tag.system_key === 'customer') && !customerSelected && <p>Ao salvar, esta pessoa deixará de ser cliente. Os dados e o histórico serão preservados.</p>}
                      {person?.has_user && !access && <p>Ao salvar, o acesso desta pessoa à conta será suspenso. O cadastro e o histórico serão preservados.</p>}
                      {person?.has_supplier && !supplierSelected && person.supplier?.active && <p>Ao salvar, esta pessoa deixará de ser fornecedor. Os dados e o histórico serão preservados.</p>}
                    </> : undefined}
                    error={
                      Object.entries(errors).find(
                        ([key]) =>
                          key === 'tag_ids' || key.startsWith('tag_ids.'),
                      )?.[1]
                    }
                  />
                </BusinessFormSection>
                {access && (
                  <BusinessFormSection
                    title="Acesso de usuário"
                    tourTarget="person-access"
                  >
                    {person?.has_user && !person.membership && (
                      <p className="business-form__control-start">
                        Esta pessoa possui usuário cadastrado.
                      </p>
                    )}
                    {!person?.has_user && mayAssign && (
                      <Select label="Cargo inicial" required name="role_id"
                        defaultValue={String(roles.find((role) => role.system_key === 'member')?.id ?? '')}
                        error={errors.role_id}
                        options={roles.map((role) => ({ value: String(role.id), label: role.name }))} />
                    )}
                    {person?.membership && (
                      <>
                        <Field label="E-mail de acesso" readOnly value={person.membership.email ?? 'Não informado'} />
                        <ul className="business-form__control-start person-assignment-summary">
                          {person.membership.assignments.map((assignment) => (
                            <li key={assignment.id}>{assignment.role} ({assignment.location})</li>
                          ))}
                        </ul>
                        {!readOnly && person.membership.can_manage && (
                          <Button type="button" tone="white" className="business-form__control-start"
                            leftIconClass="fa fa-sitemap" aria-haspopup="dialog"
                            onClick={() => setEditingAssignments(true)}>Editar atribuições</Button>
                        )}
                      </>
                    )}
                    {!person?.has_user && (
                      <>
                        <Select
                          label="Forma de acesso"
                          name="mode"
                          value={mode}
                          onChange={setMode}
                          error={errors.mode}
                          options={[
                            {
                              value: 'invite',
                              label: 'Enviar link por e-mail',
                            },
                            { value: 'manual', label: 'Definir senha agora' },
                          ]}
                        />
                        {mode === 'manual' && (
                          <>
                            <Field
                              label="Senha inicial"
                              name="password"
                              type="password"
                              autoComplete="new-password"
                              error={errors.password}
                              required
                            />
                            <Field
                              label="Confirmar senha"
                              name="password_confirmation"
                              type="password"
                              autoComplete="new-password"
                              error={errors.password_confirmation}
                              required
                            />
                          </>
                        )}
                      </>
                    )}
                  </BusinessFormSection>
                )}
              </fieldset>
              {maySave && (
                <BusinessFormActions
                  processing={processing}
                  cancelHref={auth.permissions.includes('account.people.list') ? '/conta/pessoas' : undefined}
                />
              )}
            </>
          )}
        </BusinessForm>
      </CardBox>
      {editingAssignments && person?.membership && (
        <MembershipAssignmentsModal membership={person.membership.id} name={person.name}
          onClose={() => setEditingAssignments(false)} />
      )}
    </SaasLayout>
  );
}
