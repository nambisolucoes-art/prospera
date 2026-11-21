import { useEffect, useState } from 'react';
import BusinessForm, { BusinessFormActions, BusinessFormSection } from '@/components/BusinessForm/BusinessForm';
import ChoiceControl from '@/components/ChoiceControl/ChoiceControl';
import Modal from '@/components/Modal/Modal';
import { Accordion } from '@/components/Tabs/Tabs';
import { apiRequest } from '@/utils/apiRequest';
import AssignmentFields, { type AssignmentOption } from './AssignmentFields';
import PermissionChips from './PermissionChips';
import './Access.css';

export type AssignmentSummary = { id: number; role: string; scope: string; location: string; coverage: string; effective_permissions?: string[]; can_remove?: boolean };
type Summary = { assignments: AssignmentSummary[]; options: AssignmentOption[] };

export default function MembershipAssignmentsModal({ membership, name, onClose }: {
  membership: number;
  name: string;
  onClose: () => void;
}) {
  const [data, setData] = useState<Summary>();
  const [error, setError] = useState(false);
  const action = '/conta/usuarios/' + membership + '/atribuicoes';

  useEffect(() => {
    const request = new AbortController();
    apiRequest<Summary>(action, { signal: request.signal }).then(setData).catch(() => {
      if (!request.signal.aborted) setError(true);
    });
    return () => request.abort();
  }, [action]);

  return (
    <Modal open title={'Atribuições de ' + name} onClose={onClose} size="large" contentClassName="membership-assignment-dialog" bootstrap manageFocus>
      {data ? (
        <BusinessForm action={action} method="patch" onSuccess={onClose}>
          {({ processing, errors }) => (
            <>
              <BusinessFormSection title="Cargos atribuídos">
                {data.assignments.length === 0 ? <p>Nenhuma atribuição neste escopo.</p> : data.assignments.map((assignment) => (
                  <div key={assignment.id}>
                    <ChoiceControl type="checkbox" tone="primary" id={'remove-assignment-' + assignment.id}
                      name="revocations[]" value={String(assignment.id)} disabled={!assignment.can_remove}
                      label={'Remover ' + assignment.role + ' (' + assignment.location + '). ' + assignment.coverage} />
                    <Accordion items={[{
                      id: `assignment-permissions-${assignment.id}`,
                      title: `Permissões efetivas da atribuição (${assignment.effective_permissions?.length ?? 0})`,
                      content: <EffectivePermissionGroups permissions={assignment.effective_permissions ?? []} />,
                    }]} />
                  </div>
                ))}
              </BusinessFormSection>
              {data.options.length > 0 && (
                <BusinessFormSection title="Nova atribuição">
                  <AssignmentFields options={data.options} errors={errors} prefix="grants[0]" />
                </BusinessFormSection>
              )}
              {Object.entries(errors).filter(([key]) => !key.startsWith('grants.0.')).map(([key, message]) => (
                <p key={key} className="formField__error" role="alert">{message}</p>
              ))}
              <BusinessFormActions processing={processing} onCancel={onClose} />
            </>
          )}
        </BusinessForm>
      ) : <p role={error ? 'alert' : 'status'}>{error ? 'Não foi possível abrir as atribuições.' : 'Carregando…'}</p>}
    </Modal>
  );
}

function EffectivePermissionGroups({ permissions }: { permissions: string[] }) {
  if (permissions.length === 0) return <p>Nenhuma permissão efetiva neste vínculo.</p>;

  const groups = new Map<string, string[]>();
  for (const permission of permissions) {
    const separator = permission.lastIndexOf(': ');
    const module = separator < 0 ? 'Outras permissões' : permission.slice(separator + 2);
    const action = separator < 0 ? permission : permission.slice(0, separator);
    const group = groups.get(module) ?? [];
    group.push(action);
    groups.set(module, group);
  }

  return (
    <div className="membership-assignment-dialog__permissions">
      {Array.from(groups, ([module, actions]) => (
        <div key={module}>
          <h5>{module}</h5>
          <PermissionChips permissions={actions} />
        </div>
      ))}
    </div>
  );
}
