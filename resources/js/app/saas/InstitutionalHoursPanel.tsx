import Button from '@/components/Button/Button';
import CompactButton from '@/components/CompactButton/CompactButton';
import { useState } from 'react';
import ApiForm from '@/components/ApiForm/ApiForm';
import { Select } from '@/components/BusinessForm/BusinessFormFields';
import CardBox from '@/components/CardBox/CardBox';
import InfoTooltip from '@/components/InfoTooltip/InfoTooltip';
import Label, { type LabelVariant } from '@/components/Label/Label';
import Modal from '@/components/Modal/Modal';
import InstitutionalExceptionModal from './InstitutionalExceptionModal';
import {
  exceptionKinds, formatPeriod, ownerBase, shiftsText, type EffectiveNow, type HourException, type HoursOverview, type OpenStatus,
  type ProfileOwner,
} from './InstitutionalProfile.types';
import './Institutional.css';

const statusVariant: Record<OpenStatus, LabelVariant> = {
  open: 'success', closed: 'danger', by_appointment: 'info', not_informed: 'warning', disabled: 'inverse', inactive: 'inverse',
};

const reasonText: Record<string, string> = {
  closed_day: 'A unidade não atende neste dia da semana.',
  outside_shifts: 'Fora dos turnos de hoje.',
  holiday: 'Fechado por feriado.',
  temporary_closure: 'Fechado temporariamente.',
};

const kindLabel = (kind: string) => exceptionKinds.find((item) => item.value === kind)?.label ?? kind;

function Now({ now }: { now: EffectiveNow }) {
  const exception = now.exception;

  return (
    <div className="institutional__now" data-tour="hours-now" role="status">
      <div className="institutional__now-line">
        <Label variant={statusVariant[now.status]}>{now.status_label}</Label>
        {exception && <Label variant="warning">{exception.kind === 'special_hours' ? 'Horário especial hoje' : 'Fechado excepcionalmente'}</Label>}
        {now.hours_source === 'company' && <Label variant="info">Horário herdado da empresa</Label>}
        {now.hours_source === 'establishment' && <Label variant="primary">Horário próprio desta unidade</Label>}
        {now.status === 'by_appointment' && <Label variant="info">Atendimento sob agendamento</Label>}
      </div>
      <p className="institutional__note">
        Agora são {now.local_time} de {now.local_date.split('-').reverse().join('/')} ({now.timezone.replace('_', ' ')}).
        {now.closed_reason && ` ${reasonText[now.closed_reason] ?? ''}`}
        {now.schedule && now.schedule.shifts.length > 0 && ` Hoje: ${shiftsText(now.schedule.shifts)}.`}
        {exception?.note && ` Observação: ${exception.note}.`}
      </p>
    </div>
  );
}

function ExceptionRow({ item, source, canEdit, onEdit, onDelete }: {
  item: HourException; source: 'own' | 'company'; canEdit: boolean; onEdit: () => void; onDelete: () => void;
}) {
  return (
    <li className="institutional__exception">
      <div className="institutional__exception-main">
        <strong>{kindLabel(item.kind)}</strong> <span>{formatPeriod(item)}</span>
        {item.kind === 'special_hours' && item.shifts && <span>: {shiftsText(item.shifts)}</span>}
        {item.note && <span className="institutional__note"> ({item.note})</span>}
      </div>
      <div className="institutional__badges">
        {source === 'company' && <Label variant="info">Herdada da empresa</Label>}
        {item.applies_to_overrides && <Label variant="warning">Inclui unidades com horário próprio</Label>}
      </div>
      {canEdit && source === 'own' && (
        <div className="institutional__actions">
          <CompactButton icon="fa-pencil" aria-haspopup="dialog" aria-label={`Editar ${kindLabel(item.kind).toLowerCase()} de ${formatPeriod(item)}`} onClick={onEdit}>Editar</CompactButton>
          <CompactButton icon="fa-trash" aria-haspopup="dialog" aria-label={`Excluir ${kindLabel(item.kind).toLowerCase()} de ${formatPeriod(item)}`} onClick={onDelete}>Excluir</CompactButton>
        </div>
      )}
    </li>
  );
}

// Situação atual, fuso, exceções e unidades: o resultado efetivo dos horários, sempre calculado pelo servidor.
export default function InstitutionalHoursPanel({ owner, hours, writable, units }: {
  owner: ProfileOwner; hours: HoursOverview; writable: boolean; units: { code: string; name: string }[];
}) {
  const establishment = owner.type === 'establishment';
  const [editing, setEditing] = useState<HourException | 'new'>();
  const [deleting, setDeleting] = useState<HourException>();

  return (
    <CardBox>
      <section className="institutional__section" aria-labelledby="section-hours-panel" data-tour="hours-panel">
        <header className="institutional__section-header">
          <h3 id="section-hours-panel" className="institutional__section-title">
            Situação e exceções de horário{' '}
            <InfoTooltip label="exceções de horário"
              text="Feriado, fechamento temporário e horário especial valem por data. Operacional: a alteração vale ao salvar e aparece nos sites publicados." />
          </h3>
          <Label variant="warning">Operacional</Label>
        </header>

        {hours.now && <Now now={hours.now} />}

        {establishment && hours.timezone && !writable && (
          <p className="institutional__note" data-tour="hours-timezone">Fuso horário: {hours.timezone.replace('_', ' ')}.</p>
        )}
        {establishment && hours.timezone && writable && (
          <ApiForm action={`${ownerBase(owner)}/fuso`} method="patch" options={{ preserveScroll: true }} className="institutional__timezone"
            data-tour="hours-timezone">
            {({ errors, processing }) => (
              <>
                <Select label="Fuso horário" name="timezone" defaultValue={hours.timezone ?? undefined} searchable
                  options={hours.timezones.map((zone) => ({ value: zone.key, label: zone.label }))}
                  error={errors.timezone} info="Os horários desta unidade são a hora local deste fuso, inclusive no horário de verão." />
                <Button type="submit" tone="success" disabled={processing}>Salvar</Button>
              </>
            )}
          </ApiForm>
        )}

        <h4 className="institutional__subtitle">Exceções</h4>
        {hours.exceptions.length === 0 && hours.inherited_exceptions.length === 0 ? (
          <p className="institutional__empty">Nenhuma exceção cadastrada ou prevista.</p>
        ) : (
          <ul className="institutional__exceptions" data-tour="hours-exceptions">
            {hours.exceptions.map((item) => (
              <ExceptionRow key={`own-${item.id}`} item={item} source="own" canEdit={writable}
                onEdit={() => setEditing(item)} onDelete={() => setDeleting(item)} />
            ))}
            {hours.inherited_exceptions.map((item) => (
              <ExceptionRow key={`company-${item.id}`} item={item} source="company" canEdit={false} onEdit={() => undefined} onDelete={() => undefined} />
            ))}
          </ul>
        )}
        {writable && (
          <div className="institutional__actions">
            <span data-tour="hours-add-exception"><CompactButton icon="fa-plus" aria-haspopup="dialog" onClick={() => setEditing('new')}>Cadastrar exceção</CompactButton></span>
          </div>
        )}

        {!establishment && hours.units.length > 0 && (
          <>
            <h4 className="institutional__subtitle">Situação das unidades agora</h4>
            <ul className="institutional__unit-status" data-tour="hours-units">
              {hours.units.map((unit) => (
                <li key={unit.code}>
                  <Button href={`@/estabelecimento/${unit.code}/institucional`} tone="white" size="sm">
                    {units.find((item) => item.code === unit.code)?.name ?? unit.code}
                  </Button>{' '}
                  <Label variant={statusVariant[unit.status]}>{unit.status_label}</Label>{' '}
                  <span className="institutional__note">
                    {unit.hours_mode === 'override' ? 'Horário próprio' : unit.hours_mode === 'disabled' ? 'Horários não exibidos' : 'Herda da empresa'}
                    , {unit.local_time} em {unit.timezone.replace('_', ' ')}
                  </span>
                </li>
              ))}
            </ul>
          </>
        )}
      </section>

      {editing && (
        <InstitutionalExceptionModal owner={owner} exception={editing === 'new' ? undefined : editing} onClose={() => setEditing(undefined)} />
      )}
      {deleting && (
        <Modal open bootstrap manageFocus size="small" title="Excluir exceção de horário" onClose={() => setDeleting(undefined)}>
          <ApiForm action={`${ownerBase(owner)}/excecoes/${deleting.id}`} method="delete" onSuccess={() => setDeleting(undefined)}
            options={{ preserveScroll: true }}>
            {({ processing }) => (
              <>
                <p>Excluir {kindLabel(deleting.kind).toLowerCase()} de {formatPeriod(deleting)}? A unidade volta a seguir o horário semanal.</p>
                <div className="business-form__actions-inline">
                  <Button tone="white" disabled={processing} onClick={() => setDeleting(undefined)}>Cancelar</Button>
                  <Button type="submit" tone="danger" disabled={processing}>Excluir</Button>
                </div>
              </>
            )}
          </ApiForm>
        </Modal>
      )}
    </CardBox>
  );
}
