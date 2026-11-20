import BusinessForm, {
  BusinessFormActions,
  BusinessFormSection,
} from '@/components/BusinessForm/BusinessForm';
import { Field } from '@/components/BusinessForm/BusinessFormFields';
import FormField from '@/components/FormField/FormField';
import ChoiceControl from '@/components/ChoiceControl/ChoiceControl';
import CardBox from '@/components/CardBox/CardBox';
import { useApiPage } from '@/hooks/useApiPage';
import type { SessionProps } from '@/types/Session.types';
import SaasLayout from './SaasLayout';
import RoleAvailabilityFields, { type AvailabilityOptions } from './RoleAvailabilityFields';
import type { RoleRow, PermissionRow } from './Access.types';
export default function RoleForm({
  role,
  permissions,
  modules,
  organization,
}: {
  role: RoleRow | null;
  permissions: PermissionRow[];
  modules: Record<string, string>;
  organization: AvailabilityOptions;
}) {
  const { auth } = useApiPage<SessionProps>().props;
  const locked =
    role?.system_key === 'administrator' ||
    auth.inspection_mode === 'read-only';
  const grouped = permissions.reduce<Record<string, PermissionRow[]>>(
    (groups, permission) => {
      (groups[permission.module] ??= []).push(permission);
      return groups;
    },
    {},
  );
  return (
    <SaasLayout
      tenant
      title={role ? 'Editar cargo' : 'Cadastrar cargo'}
      breadcrumb={[
        { label: 'Cargos', href: auth.permissions.includes('account.roles.list') ? '/conta/cargos' : undefined },
        { label: role ? 'Editar' : 'Cadastrar' },
      ]}
    >
      <CardBox>
        <BusinessForm
          className="business-form--wide-labels"
          action={role ? `/conta/cargos/${role.id}` : '/conta/cargos'}
          method={role ? 'patch' : 'post'}
        >
          {({ errors, processing }) => (
            <>
              <fieldset
                disabled={locked}
                style={{ border: 0, padding: 0, margin: 0, minWidth: 0 }}
              >
                <BusinessFormSection
                  title="Dados do cargo"
                  tourTarget="role-fields"
                >
                  <Field
                    label="Nome"
                    name="name"
                    defaultValue={role?.name}
                    error={errors.name}
                    required
                  />
                </BusinessFormSection>
                <RoleAvailabilityFields role={role} organization={organization} errors={errors} />
                <BusinessFormSection
                  title="Permissões por módulo"
                  tourTarget="role-permissions"
                >
                  {Object.entries(grouped).map(([module, items]) => (
                    <FormField
                      key={module}
                      id={`role-module-${module}`}
                      label={modules[module] ?? module}
                      info="Cada ação exige sua própria permissão. Você só pode conceder as permissões que possui."
                      layout="horizontal"
                      passiveLabel
                    >
                      {items.map((permission) => (
                        <ChoiceControl
                          key={permission.id}
                          type="checkbox"
                          id={`role-permission-${permission.id}`}
                          name="permission_ids[]"
                          value={String(permission.id)}
                          checked={role ? role.permissions?.some((item) => item.id === permission.id) : permission.module === 'help' && auth.permissions.includes(permission.slug)}
                          disabled={!auth.permissions.includes(permission.slug)}
                          label={permission.name}
                        />
                      ))}
                    </FormField>
                  ))}
                  {Object.entries(errors)
                    .filter(([key]) => key.startsWith('permission_ids'))
                    .map(([key, value]) => (
                      <p role="alert" key={key}>
                        {value}
                      </p>
                    ))}
                </BusinessFormSection>
              </fieldset>
              {!locked && (
                <BusinessFormActions
                  processing={processing}
                  cancelHref={auth.permissions.includes('account.roles.list') ? '/conta/cargos' : undefined}
                />
              )}
            </>
          )}
        </BusinessForm>
      </CardBox>
    </SaasLayout>
  );
}
