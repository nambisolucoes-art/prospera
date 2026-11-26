import { useState } from 'react';
import BusinessForm, { BusinessFormActions, BusinessFormRow, BusinessFormSection } from '@/components/BusinessForm/BusinessForm';
import { Field, Select } from '@/components/BusinessForm/BusinessFormFields';
import Button from '@/components/Button/Button';
import ChoiceControl from '@/components/ChoiceControl/ChoiceControl';
import FormField from '@/components/FormField/FormField';
import FormHelp from '@/components/FormHelp/FormHelp';
import Label from '@/components/Label/Label';
import InfoTooltip from '@/components/InfoTooltip/InfoTooltip';
import Modal from '@/components/Modal/Modal';
import WorkContactFields from './WorkContactFields';
import './ContactFields.css';
import './Employees.css';

export type AssignmentRow = {
  id: number;
  establishment_code: string;
  establishment_name: string;
  department_id: number | null;
  department_name: string | null;
  job_title_id: number | null;
  job_title_name: string | null;
  starts_on: string;
  ends_on: string | null;
  is_primary: boolean;
  contact_mode: 'inherit' | 'override';
  contacts: { email: string | null; phone: string | null; whatsapp: string | null };
  effective_contacts: { email: string | null; phone: string | null; whatsapp: string | null; source: 'inherit' | 'override' | 'none' };
  status: 'current' | 'future' | 'ended';
  can_manage: boolean;
};

export type AssignmentOptions = {
  establishments: { code: string; name: string }[];
  departments: { id: number; name: string }[];
  job_titles: { id: number; name: string }[];
};

type Mode = 'create' | 'edit' | 'change' | 'end' | 'primary';
type Dialog = { mode: Mode; assignment?: AssignmentRow };

const statusLabel = { current: 'Vigente', future: 'Futura', ended: 'Encerrada' } as const;
const statusVariant = { current: 'success', future: 'info', ended: 'default' } as const;

export function formatDate(date: string) {
  const [year, month, day] = date.split('-');
  return `${day}/${month}/${year}`;
}

// Origem do contato profissional exibido: herdado do vínculo, personalizado na lotação ou sem contato local.
function contactSummary(assignment: AssignmentRow) {
  const { source, email, phone, whatsapp } = assignment.effective_contacts;
  const values = [email, phone, whatsapp].filter(Boolean).join(' · ');
  if (source === 'none') return 'Contatos: sem contato nesta unidade (intencional)';
  if (source === 'override') return `Contatos personalizados: ${values}`;
  return values ? `Contatos herdados do vínculo: ${values}` : 'Contatos herdados do vínculo: não informados';
}

const titles: Record<Mode, string> = {
  create: 'Adicionar lotação',
  edit: 'Corrigir lotação',
  change: 'Alterar função ou departamento',
  end: 'Encerrar lotação',
  primary: 'Tornar lotação principal',
};

// Lotações de um vínculo: períodos por estabelecimento, com função e departamento do período e a principal marcada.
export default function EmployeeAssignments({ personId, link, today, writable, onClose }: {
  personId: number;
  onClose: () => void;
  link: {
    id: number;
    company_name: string;
    active: boolean;
    company_active: boolean;
    scope: 'company' | 'unit';
    assignments: AssignmentRow[];
    assignment_options: AssignmentOptions | null;
  };
  today: string;
  writable: boolean;
}) {
  const [dialog, setDialog] = useState<Dialog>();
  const [history, setHistory] = useState(false);
  const options = link.assignment_options;
  const canAdd = writable && link.active && link.company_active && Boolean(options?.establishments.length);
  // Vigentes primeiro, depois as futuras (mais próximas antes) e, no histórico, as encerradas (mais recentes antes).
  const open = [
    ...link.assignments.filter((assignment) => assignment.status === 'current'),
    ...link.assignments.filter((assignment) => assignment.status === 'future').reverse(),
  ];
  const ended = link.assignments.filter((assignment) => assignment.status === 'ended');
  const shown = history ? [...open, ...ended] : open;
  const base = `@/colaboradores/${personId}/vinculos/${link.id}/lotacoes`;

  // Adicionar, corrigir, trocar, encerrar e tornar principal abrem um painel dentro do mesmo modal, sem janela aninhada.
  if (dialog && options) {
    return (
      <Modal open bootstrap manageFocus size="large" title={`${titles[dialog.mode]} em ${link.company_name}`} onClose={onClose}>
        <AssignmentDialog dialog={dialog} base={base} options={options} today={today} siblings={link.assignments}
          onClose={() => setDialog(undefined)} />
      </Modal>
    );
  }

  return (
    <Modal open bootstrap manageFocus size="large" onClose={onClose} title={<>
      Lotações em {link.company_name}{' '}
      <InfoTooltip label="lotações"
        text="Cada lotação é um período em um estabelecimento, com função e departamento do período." />
    </>}>
      <div className="business-form">
        <BusinessFormSection title="Períodos de lotação">
          {link.scope === 'unit' && (
            <p className="employees__note">Você vê somente as lotações das unidades que o seu acesso alcança.</p>
          )}
          {!link.active && link.assignments.length > 0 && (
            <p className="employees__note">
              O vínculo está inativo: as lotações vigentes foram encerradas e as futuras ficam sem efeito. Reative o
              vínculo e cadastre uma nova lotação.
            </p>
          )}
          {shown.length === 0 ? (
            <p className="employees__empty">
              {link.assignments.length === 0 ? 'Nenhuma lotação cadastrada.' : 'Nenhuma lotação vigente ou futura.'}
            </p>
          ) : (
            <ul className="employees__assignments">
              {shown.map((assignment) => (
                <li key={assignment.id} className="employees__assignment" data-status={assignment.status}>
                  <div className="employees__assignment-main">
                    <strong>{assignment.establishment_name}</strong>
                    {assignment.is_primary && <Label variant="primary">Principal</Label>}
                    <Label variant={statusVariant[assignment.status]}>{statusLabel[assignment.status]}</Label>
                  </div>
                  <div className="employees__assignment-meta">
                    {assignment.job_title_name ?? 'Sem função'}
                    {' · '}
                    {assignment.department_name ?? 'Sem departamento'}
                  </div>
                  <div className="employees__assignment-contacts" data-source={assignment.effective_contacts.source}>
                    {contactSummary(assignment)}
                  </div>
                  <div className="employees__assignment-period">
                    {formatDate(assignment.starts_on)} a {assignment.ends_on ? formatDate(assignment.ends_on) : 'sem data final'}
                  </div>
                  {writable && assignment.can_manage && link.company_active && (
                    <div className="employees__assignment-actions">
                      {assignment.status !== 'ended' && link.active && (
                        <>
                          <button type="button" className="employees__icon-button" title="Alterar função ou departamento"
                            aria-label={`Alterar função ou departamento em ${assignment.establishment_name}`}
                            onClick={() => setDialog({ mode: 'change', assignment })}>
                            <i className="fa fa-exchange" aria-hidden="true" />
                          </button>
                          {!assignment.is_primary && (
                            <button type="button" className="employees__icon-button" title="Tornar principal"
                            aria-label={`Tornar principal a lotação em ${assignment.establishment_name}`}
                            onClick={() => setDialog({ mode: 'primary', assignment })}>
                            <i className="fa fa-flag" aria-hidden="true" />
                          </button>
                          )}
                          <button type="button" className="employees__icon-button employees__icon-button--danger" title="Encerrar lotação"
                            aria-label={`Encerrar a lotação em ${assignment.establishment_name}`}
                            onClick={() => setDialog({ mode: 'end', assignment })}>
                            <i className="fa fa-times" aria-hidden="true" />
                          </button>
                        </>
                      )}
                      <button type="button" className="employees__icon-button" title="Corrigir lotação"
                            aria-label={`Corrigir a lotação em ${assignment.establishment_name}`}
                            onClick={() => setDialog({ mode: 'edit', assignment })}>
                            <i className="fa fa-pencil" aria-hidden="true" />
                          </button>
                    </div>
                  )}
                </li>
              ))}
            </ul>
          )}
          <div className="employees__assignment-footer">
            {canAdd && (
              <button type="button" className="contact-fields__add waves-effect waves-light" aria-haspopup="dialog"
                aria-label="Adicionar lotação" onClick={() => setDialog({ mode: 'create' })}>
                <span aria-hidden="true"><i className="fa fa-plus" /></span>Lotação
              </button>
            )}
            {ended.length > 0 && (
              <button type="button" className="contact-fields__add waves-effect waves-light" aria-pressed={history}
                onClick={() => setHistory((value) => !value)}>
                <span aria-hidden="true"><i className="fa fa-history" /></span>
                {history ? 'Ocultar histórico' : `Histórico (${ended.length})`}
              </button>
            )}
          </div>
        </BusinessFormSection>
        <div className="employees__assignment-footer">
          <Button tone="white" onClick={onClose}>Fechar</Button>
        </div>
      </div>
    </Modal>
  );
}

function AssignmentDialog({ dialog, base, options, today, siblings, onClose }: {
  dialog: Dialog;
  base: string;
  options: AssignmentOptions;
  today: string;
  siblings: AssignmentRow[];
  onClose: () => void;
}) {
  const { mode, assignment } = dialog;
  const [establishment, setEstablishment] = useState(options.establishments[0]?.code ?? '');
  const [department, setDepartment] = useState(String(assignment?.department_id ?? ''));
  const [jobTitle, setJobTitle] = useState(String(assignment?.job_title_id ?? ''));
  const [heir, setHeir] = useState('');
  const initialContacts = !assignment || assignment.contact_mode === 'inherit'
    ? 'inherit'
    : (assignment.effective_contacts.source === 'none' ? 'none' : 'custom');
  const [contactChoice, setContactChoice] = useState(initialContacts);
  const action = mode === 'create'
    ? base
    : `${base}/${assignment?.id}${{ edit: '', change: '/trocar', end: '/encerrar', primary: '/principal' }[mode]}`;
  const method = mode === 'create' || mode === 'change' ? 'post' : 'patch';
  const candidates = siblings.filter((item) => item.id !== assignment?.id && item.status !== 'ended' && item.can_manage);
  const needsHeir = mode === 'end' && assignment?.is_primary && candidates.length > 0;
  // Contatos profissionais da lotação: herdar do vínculo, personalizar ou ausência intencional de contato local.
  const contactFields = (errors: Record<string, string | undefined>, processing: boolean) => (
    <>
      <input type="hidden" name="contact_mode" value={contactChoice === 'inherit' ? 'inherit' : 'override'} />
      <Select label="Contatos profissionais" value={contactChoice} onChange={setContactChoice}
        error={errors.contact_mode}
        info="Herdar acompanha os contatos do vínculo. Personalizar vale só para esta unidade, e sem contato não herda nada."
        options={[{ value: 'inherit', label: 'Herdar do vínculo' }, { value: 'custom', label: 'Personalizar nesta unidade' },
          { value: 'none', label: 'Sem contato nesta unidade' }]} />
      {contactChoice === 'custom' && (
        <WorkContactFields names={{ email: 'work_email', phone: 'work_phone', whatsapp: 'work_whatsapp' }}
          values={assignment?.contact_mode === 'override' ? assignment.contacts : undefined}
          errors={{ email: errors.work_email, phone: errors.work_phone, whatsapp: errors.work_whatsapp }} disabled={processing} />
      )}
    </>
  );
  const catalogs = (
    <>
      <Select label="Função" name="job_title_id" value={jobTitle} onChange={setJobTitle}
        options={[{ value: '', label: 'Sem função' }, ...options.job_titles.map((item) => ({ value: String(item.id), label: item.name }))]} />
      <Select label="Departamento" name="department_id" value={department} onChange={setDepartment}
        options={[{ value: '', label: 'Sem departamento' }, ...options.departments.map((item) => ({ value: String(item.id), label: item.name }))]} />
    </>
  );

  return (
    <div>
      <BusinessForm className="business-form--wide-labels employees__assignment-form" action={action} method={method}
        options={{ preserveScroll: true }} onSuccess={onClose}>
        {({ errors, processing }) => (
          <>
            <BusinessFormSection title={assignment ? assignment.establishment_name : 'Dados da lotação'}>
              {errors.employee && <p className="formField__error" role="alert">{errors.employee}</p>}
              {mode === 'create' && (
                <>
                  <Select label="Estabelecimento" name="establishment" value={establishment} onChange={setEstablishment}
                    required error={errors.establishment}
                    options={options.establishments.map((item) => ({ value: item.code, label: item.name }))} />
                  {catalogs}
                  <BusinessFormRow>
                    <Field label="Início" name="starts_on" type="date" defaultValue={today} required error={errors.starts_on} />
                    <Field label="Fim" name="ends_on" type="date" error={errors.ends_on}
                      info="Último dia de vigência, inclusive. Deixe em branco para sem data final." />
                  </BusinessFormRow>
                  <FormField id="assignment-primary" label="Principal" passiveLabel layout="horizontal" error={errors.is_primary}>
                    <ChoiceControl type="checkbox" tone="primary" id="assignment-primary" name="is_primary" value="1"
                      label="Lotação principal do colaborador" />
                  </FormField>
                  {contactFields(errors, processing)}
                </>
              )}
              {mode === 'edit' && assignment && (
                <>
                  <Field label="Estabelecimento" value={assignment.establishment_name} readOnly
                    info="A unidade de uma lotação não muda. Encerre a lotação e cadastre outra." />
                  {catalogs}
                  <BusinessFormRow>
                    <Field label="Início" name="starts_on" type="date" defaultValue={assignment.starts_on} required
                      error={errors.starts_on} />
                    <Field label="Fim" name="ends_on" type="date" defaultValue={assignment.ends_on ?? ''} error={errors.ends_on} />
                  </BusinessFormRow>
                  {contactFields(errors, processing)}
                  <FormHelp className="business-form__control-start">
                    Corrige o próprio período, sem criar histórico novo. Para registrar uma mudança ao longo do tempo,
                    use Alterar função ou departamento.
                  </FormHelp>
                </>
              )}
              {mode === 'change' && assignment && (
                <>
                  {catalogs}
                  <Field label="A partir de" name="effective_on" type="date" defaultValue={today > assignment.starts_on ? today : assignment.starts_on}
                    required error={errors.effective_on}
                    info="O período atual termina no dia anterior e o novo começa nesta data. Se o período atual começa nesta data, ele é corrigido." />
                </>
              )}
              {mode === 'end' && assignment && (
                <>
                  <Field label="Último dia" name="ends_on" type="date" defaultValue={today > assignment.starts_on ? today : assignment.starts_on}
                    required error={errors.ends_on} info="A lotação vale até o fim deste dia." />
                  {!needsHeir && errors.new_primary_id && (
                    <p className="formField__error" role="alert">{errors.new_primary_id}</p>
                  )}
                  {needsHeir && (
                    <Select label="Nova principal" name="new_primary_id" value={heir} onChange={setHeir} required
                      error={errors.new_primary_id}
                      options={[{ value: '', label: 'Escolha a nova lotação principal' },
                        ...candidates.map((item) => ({ value: String(item.id), label: `${item.establishment_name} (${formatDate(item.starts_on)})` }))]} />
                  )}
                </>
              )}
              {mode === 'primary' && errors.is_primary && (
                <p className="formField__error" role="alert">{errors.is_primary}</p>
              )}
              {mode === 'primary' && assignment && (
                <FormHelp className="business-form__control-start">
                  A lotação em {assignment.establishment_name} passa a ser a principal. A marca das principais que
                  coincidem com este período sai, e o histórico fica registrado na auditoria.
                </FormHelp>
              )}
            </BusinessFormSection>
            <BusinessFormActions inline processing={processing} onCancel={onClose}
              submitDisabled={needsHeir && heir === ''} />
          </>
        )}
      </BusinessForm>
    </div>
  );
}
