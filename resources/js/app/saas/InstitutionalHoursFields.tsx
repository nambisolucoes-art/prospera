import { useState } from 'react';
import { Select } from '@/components/BusinessForm/BusinessFormFields';
import FormHelp from '@/components/FormHelp/FormHelp';
import InstitutionalShiftsEditor, { blankShift } from './InstitutionalShiftsEditor';
import { weekdays, type HoursDay, type HoursDayKind, type HoursValue, type WeekdayKey } from './InstitutionalProfile.types';

type Errors = Record<string, string | undefined>;
type Week = Record<WeekdayKey, HoursDay>;

const kinds: { value: HoursDayKind; label: string }[] = [
  { value: 'open', label: 'Aberto' },
  { value: 'appointment', label: 'Somente com agendamento' },
  { value: 'closed', label: 'Fechado' },
];

// Editor da grade semanal: tipo do dia e turnos, sem JSON. O envio usa campos escondidos no formato do contrato.
export default function InstitutionalHoursFields({ value, errors }: { value: HoursValue; errors: Errors }) {
  const [week, setWeek] = useState<Week>(() => structuredClone(value.days) as Week);

  const update = (key: WeekdayKey, day: HoursDay) => setWeek((current) => ({ ...current, [key]: day }));
  const setKind = (key: WeekdayKey, kind: HoursDayKind) => {
    const day = week[key];
    // Fechado não tem turnos; aberto precisa de ao menos um.
    const shifts = kind === 'closed' ? [] : (kind === 'open' && day.shifts.length === 0 ? [blankShift()] : day.shifts);
    update(key, { kind, shifts });
  };
  const copyMonday = (keys: WeekdayKey[]) => setWeek((current) => {
    const next = { ...current };
    for (const key of keys) next[key] = structuredClone(current.mon);
    return next;
  });

  return (
    <div className="institutional__hours-editor" data-tour="hours-editor">
      <FormHelp className="business-form__control-start">
        A hora é a hora local do estabelecimento (o fuso fica em Fuso horário). Um turno não atravessa a meia-noite: para
        atender depois dela, use um turno até 24:00 num dia e outro a partir de 00:00 no dia seguinte.
      </FormHelp>
      <div className="institutional__hours-tools" data-tour="hours-copy">
        <button type="button" className="contact-fields__add waves-effect waves-light"
          onClick={() => copyMonday(['tue', 'wed', 'thu', 'fri'])}>
          <span aria-hidden="true"><i className="fa fa-copy" /></span>Copiar segunda-feira para terça a sexta
        </button>
        <button type="button" className="contact-fields__add waves-effect waves-light"
          onClick={() => copyMonday(['tue', 'wed', 'thu', 'fri', 'sat', 'sun'])}>
          <span aria-hidden="true"><i className="fa fa-copy" /></span>Copiar segunda-feira para todos os dias
        </button>
      </div>

      {weekdays.map(({ key, label }) => {
        const day = week[key];
        const dayError = errors[`value.days.${key}`] ?? errors[`value.days.${key}.kind`] ?? errors[`value.days.${key}.shifts`];

        return (
          <fieldset key={key} className="institutional__day" data-day={key}>
            <legend className="institutional__day-title">{label}</legend>
            <Select label="Atendimento" name={`value[days][${key}][kind]`} value={day.kind}
              onChange={(kind) => setKind(key, kind as HoursDayKind)} options={kinds} />
            {day.kind !== 'closed' && (
              <InstitutionalShiftsEditor label={label} idPrefix={`day-${key}`} fieldName={`value[days][${key}][shifts]`}
                errorPrefix={`value.days.${key}.shifts`} shifts={day.shifts} errors={errors}
                onChange={(shifts) => update(key, { ...day, shifts })} />
            )}
            {day.kind === 'closed' && <input type="hidden" name={`value[days][${key}][shifts]`} value="" />}
            {dayError && <p className="formField__error" role="alert">{dayError}</p>}
          </fieldset>
        );
      })}
    </div>
  );
}
