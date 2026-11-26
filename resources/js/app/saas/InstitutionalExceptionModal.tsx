import { useEffect, useState } from 'react';
import BusinessForm, { BusinessFormActions, BusinessFormSection } from '@/components/BusinessForm/BusinessForm';
import { Field, Select } from '@/components/BusinessForm/BusinessFormFields';
import ChoiceControl from '@/components/ChoiceControl/ChoiceControl';
import FormField from '@/components/FormField/FormField';
import FormHelp from '@/components/FormHelp/FormHelp';
import Modal from '@/components/Modal/Modal';
import { useContextUrl } from '@/hooks/useContextUrl';
import { apiRequest } from '@/utils/apiRequest';
import InstitutionalShiftsEditor, { blankShift } from './InstitutionalShiftsEditor';
import {
  exceptionKinds, formatPeriod, ownerBase, type ExceptionImpact, type HourException, type HourExceptionKind, type HoursShift,
  type ProfileOwner,
} from './InstitutionalProfile.types';
import './Institutional.css';

// Cadastro e edição de uma exceção de horário. Na empresa, mostra o alcance sobre as unidades e, para atingir as que têm
// horário próprio, exige a confirmação do resumo; o servidor recalcula o alcance e recusa se ele tiver mudado.
export default function InstitutionalExceptionModal({ owner, exception, onClose }: {
  owner: ProfileOwner; exception?: HourException; onClose: () => void;
}) {
  const contextUrl = useContextUrl();
  const company = owner.type === 'company';
  const [kind, setKind] = useState<HourExceptionKind>(exception?.kind ?? 'holiday');
  const [startsOn, setStartsOn] = useState(exception?.starts_on ?? '');
  const [endsOn, setEndsOn] = useState(exception && exception.ends_on !== exception.starts_on ? exception.ends_on : '');
  const [shifts, setShifts] = useState<HoursShift[]>(exception?.shifts ?? [blankShift()]);
  const [applies, setApplies] = useState(exception?.applies_to_overrides ?? false);
  const [confirmed, setConfirmed] = useState(false);
  const [impact, setImpact] = useState<ExceptionImpact>();
  const [failed, setFailed] = useState(false);
  const [reload, setReload] = useState(0);

  useEffect(() => {
    if (!company || !startsOn) {
      setImpact(undefined);
      return;
    }

    const controller = new AbortController();
    setFailed(false);
    setConfirmed(false);
    const query = new URLSearchParams({ kind, starts_on: startsOn, ends_on: endsOn });
    apiRequest<ExceptionImpact>(contextUrl(`${ownerBase(owner)}/excecoes/impacto?${query}`), { signal: controller.signal })
      .then(setImpact)
      .catch(() => { if (!controller.signal.aborted) setFailed(true); });

    return () => controller.abort();
  }, [company, kind, startsOn, endsOn, owner, contextUrl, reload]);

  const reaches = impact?.overriding.length ?? 0;
  const needsConfirmation = company && applies && reaches > 0;
  const action = exception ? `${ownerBase(owner)}/excecoes/${exception.id}` : `${ownerBase(owner)}/excecoes`;

  return (
    <Modal open bootstrap manageFocus size="large" title={exception ? 'Editar exceção de horário' : 'Cadastrar exceção de horário'} onClose={onClose}>
      <BusinessForm className="business-form--wide-labels" action={action} method={exception ? 'patch' : 'post'}
        options={{ preserveScroll: true }} onSuccess={onClose}>
        {({ errors, processing }) => {
          if (errors.impact && !failed) {
            // O alcance mudou: pede um resumo novo em vez de repetir a confirmação antiga.
            queueMicrotask(() => setReload((count) => count + 1));
          }

          return (
            <>
              <BusinessFormSection title={owner.name}>
                <p className="institutional__note">Esta alteração vale ao salvar e já aparece nos sites publicados.</p>
                <Select label="Tipo" name="kind" value={kind} onChange={(value) => setKind(value as HourExceptionKind)}
                  error={errors.kind} options={exceptionKinds} />
                <Field label="Primeiro dia" name="starts_on" type="date" value={startsOn} required error={errors.starts_on}
                  onChange={(event) => setStartsOn(event.target.value)} />
                <Field label="Último dia" name="ends_on" type="date" value={endsOn} error={errors.ends_on}
                  onChange={(event) => setEndsOn(event.target.value)} info="Deixe em branco para uma data só. O último dia vale inteiro." />
                {kind === 'special_hours' && (
                  <FormField id="exception-shifts" label="Turnos" error={errors.shifts} layout="horizontal">
                    <div className="institutional__exception-shifts">
                      <InstitutionalShiftsEditor label="Horário especial" idPrefix="exception" fieldName="shifts" errorPrefix="shifts"
                        shifts={shifts} errors={errors} onChange={setShifts} />
                    </div>
                  </FormField>
                )}
                <Field label="Observação pública" name="note" defaultValue={exception?.note ?? ''} maxLength={160} error={errors.note}
                  info="Opcional, até 160 caracteres, em texto simples." />
                {kind !== 'special_hours' && <p className="institutional__note">A unidade fica fechada nos dias da exceção.</p>}

                {company && (
                  <div className="institutional__impact" role="status" data-tour="hours-impact">
                    <strong>Alcance desta exceção</strong>
                    {failed && <p className="formField__error" role="alert">Não foi possível calcular o alcance.</p>}
                    {!impact && !failed && <p>Informe o primeiro dia para ver as unidades alcançadas.</p>}
                    {impact && (
                      <>
                        <ul>
                          <li>{impact.inheriting.length} unidade(s) herdam o horário da empresa e são sempre alcançadas
                            {impact.inheriting.length > 0 && `: ${impact.inheriting.map((unit) => unit.name).join(', ')}`}.</li>
                          <li>{reaches} unidade(s) têm horário próprio
                            {reaches > 0 && `: ${impact.overriding.map((unit) => unit.name).join(', ')}`}
                            {applies ? ' e também serão alcançadas.' : ' e não serão alcançadas.'}</li>
                          {impact.disabled > 0 && <li>{impact.disabled} unidade(s) não exibem horários e não são afetadas.</li>}
                        </ul>
                        {startsOn && <p>Período: {formatPeriod({ starts_on: startsOn, ends_on: endsOn || startsOn })}.</p>}
                      </>
                    )}
                    <input type="hidden" name="applies_to_overrides" value={applies ? '1' : '0'} />
                    <ChoiceControl type="checkbox" tone="primary" id="exception-applies" checked={applies}
                      label="Aplicar também às unidades com horário próprio"
                      onChange={(checked) => { setApplies(checked); setConfirmed(false); }} />
                    {applies && impact && <input type="hidden" name="impact_token" value={impact.token} />}
                    {needsConfirmation && (
                      <ChoiceControl type="checkbox" tone="danger" id="exception-confirm" checked={confirmed}
                        label={`Confirmo que ${reaches} unidade(s) com horário próprio também serão atingidas`}
                        onChange={setConfirmed} />
                    )}
                    {applies && (
                      <FormHelp>Vale também para unidades que passarem a ter horário próprio depois desta confirmação.</FormHelp>
                    )}
                    {errors.impact && <p className="formField__error" role="alert">{errors.impact}</p>}
                    {errors.applies_to_overrides && <p className="formField__error" role="alert">{errors.applies_to_overrides}</p>}
                  </div>
                )}
              </BusinessFormSection>
              <BusinessFormActions inline processing={processing} onCancel={onClose}
                submitDisabled={(company && applies && !impact) || (needsConfirmation && !confirmed)} />
            </>
          );
        }}
      </BusinessForm>
    </Modal>
  );
}
