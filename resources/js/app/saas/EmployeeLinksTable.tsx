import { useState } from 'react';
import BusinessForm, { BusinessFormActions, BusinessFormSection } from '@/components/BusinessForm/BusinessForm';
import { Field, Select } from '@/components/BusinessForm/BusinessFormFields';
import CardBox from '@/components/CardBox/CardBox';
import Label from '@/components/Label/Label';
import Modal from '@/components/Modal/Modal';
import InfoTooltip from '@/components/InfoTooltip/InfoTooltip';
import Table from '@/components/Table/Table';
import TagLabel from '@/components/TagLabel/TagLabel';
import TagSelect from '@/components/TagSelect/TagSelect';
import EmployeeAssignments, { type AssignmentOptions, type AssignmentRow } from './EmployeeAssignments';
import WorkContactFields from './WorkContactFields';
import './Employees.css';

export type Tag = { id: number; name: string; color: string };
export type Link = {
  id: number;
  company_code: string;
  company_name: string;
  company_active: boolean;
  active: boolean;
  work_email: string | null;
  work_phone: string | null;
  work_whatsapp: string | null;
  tag_ids: number[];
  scope: 'company' | 'unit';
  assignments: AssignmentRow[];
  assignment_options: AssignmentOptions | null;
  can_update: boolean;
  can_change_status: boolean;
};

// Vínculos empresariais da pessoa: uma linha por empresa, editada num modal dentro da ficha (tabela do tema).
export default function EmployeeLinksTable({ personId, links, tags, writable }: {
  personId: number;
  links: Link[];
  tags: Tag[];
  writable: boolean;
}) {
  const [editing, setEditing] = useState<Link>();
  const [assigning, setAssigning] = useState<number>();
  const assigningLink = links.find((link) => link.id === assigning);
  const tagById = new Map(tags.map((tag) => [tag.id, tag]));

  return (
    <CardBox>
      <div className="business-form" data-tour="employee-links">
        <BusinessFormSection title={<>
          Vínculos empresariais{' '}
          <InfoTooltip label="vínculos empresariais"
            text="Cada empresa tem o próprio vínculo, com situação, etiquetas e contatos profissionais independentes." />
        </>}>
          <div className="table-responsive">
            <Table hover className="employees__links-table">
              <thead>
                <tr>
                  <th>Empresa</th>
                  <th>Situação</th>
                  <th>Etiquetas</th>
                  <th>Contatos profissionais</th>
                  <th data-tour="employee-assignments">Lotações</th>
                  <th className="employees__links-actions">Ações</th>
                </tr>
              </thead>
              <tbody>
                {links.map((link) => {
                  const canEdit = writable && link.scope === 'company' && link.company_active
                    && (link.can_update || link.can_change_status);
                  const current = link.assignments.filter((item) => item.status === 'current').length;
                  const future = link.assignments.filter((item) => item.status === 'future').length;
                  const contacts = [link.work_email, link.work_phone, link.work_whatsapp].filter(Boolean);

                  return (
                    <tr key={link.id}>
                      <td><strong>{link.company_name}</strong>{!link.company_active && <> <Label variant="danger">Inativa</Label></>}</td>
                      <td><Label variant={link.active ? 'success' : 'danger'}>{link.active ? 'Ativo' : 'Inativo'}</Label></td>
                      <td>
                        {link.tag_ids.length > 0
                          ? link.tag_ids.map((id) => tagById.get(id)).filter((tag): tag is Tag => Boolean(tag))
                            .map((tag) => <TagLabel key={tag.id} name={tag.name} color={tag.color} />)
                          : 'Não informado'}
                      </td>
                      <td>{link.scope === 'unit' ? 'Restrito à empresa' : (contacts.length ? contacts.join(' · ') : 'Não informado')}</td>
                      <td>
                        {link.assignments.length === 0
                          ? 'Sem lotação'
                          : `${current} vigente${current === 1 ? '' : 's'}${future ? `, ${future} futura${future === 1 ? '' : 's'}` : ''}`}
                      </td>
                      <td className="employees__links-actions">
                        <button type="button" className="employees__icon-button" title="Lotações"
                          aria-label={`Lotações em ${link.company_name}`} aria-haspopup="dialog"
                          onClick={() => setAssigning(link.id)}>
                          <i className="fa fa-building-o" aria-hidden="true" />
                        </button>
                        {canEdit && (
                          <button type="button" className="employees__icon-button" title="Editar vínculo"
                            aria-label={`Editar o vínculo em ${link.company_name}`} aria-haspopup="dialog"
                            onClick={() => setEditing(link)}>
                            <i className="fa fa-pencil" aria-hidden="true" />
                          </button>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </Table>
          </div>
        </BusinessFormSection>
      </div>
      {assigningLink && (
        <EmployeeAssignments personId={personId} link={assigningLink} writable={writable}
          onClose={() => setAssigning(undefined)} />
      )}
      {editing && <LinkModal personId={personId} link={editing} tags={tags} onClose={() => setEditing(undefined)} />}
    </CardBox>
  );
}

function LinkModal({ personId, link, tags, onClose }: { personId: number; link: Link; tags: Tag[]; onClose: () => void }) {
  const [tagIds, setTagIds] = useState(link.tag_ids);
  const [status, setStatus] = useState(link.active ? '1' : '0');

  return (
    <Modal open bootstrap manageFocus size="large" title={`Editar vínculo em ${link.company_name}`} onClose={onClose}>
      <BusinessForm className="business-form--wide-labels" action={`@/colaboradores/${personId}/vinculos/${link.id}`}
        method="patch" options={{ preserveScroll: true }} onSuccess={onClose}>
        {({ errors, processing }) => {
          const disabled = !link.can_update || processing;

          return (
            <>
              <BusinessFormSection title="Dados do vínculo">
                {link.can_change_status ? (
                  <Select label="Situação" name="active" value={status} error={errors.active} onChange={setStatus}
                    options={[{ value: '1', label: 'Ativo' }, { value: '0', label: 'Inativo' }]} />
                ) : (
                  <Field label="Situação" value={link.active ? 'Ativo' : 'Inativo'} readOnly />
                )}
                {link.can_update && <input type="hidden" name="edit_tags" value="1" />}
                <TagSelect
                  options={link.can_update ? tags : tags.map((tag) => ({ ...tag, locked: true, displayOnly: true }))}
                  selected={tagIds} onChange={setTagIds} disabled={processing}
                  error={Object.entries(errors).find(([key]) => key === 'tag_ids' || key.startsWith('tag_ids.'))?.[1]} />
                <WorkContactFields
                  names={{ email: 'work_email', phone: 'work_phone', whatsapp: 'work_whatsapp' }}
                  values={{ email: link.work_email, phone: link.work_phone, whatsapp: link.work_whatsapp }}
                  errors={{ email: errors.work_email, phone: errors.work_phone, whatsapp: errors.work_whatsapp }}
                  disabled={disabled} />
              </BusinessFormSection>
              <BusinessFormActions inline processing={processing} onCancel={onClose} />
            </>
          );
        }}
      </BusinessForm>
    </Modal>
  );
}
