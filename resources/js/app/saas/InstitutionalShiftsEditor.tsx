import CompactButton from '@/components/CompactButton/CompactButton';
import ChoiceControl from '@/components/ChoiceControl/ChoiceControl';
import ClockPicker from '@/components/ClockPicker/ClockPicker';
import './ContactFields.css';
import type { HoursShift } from './InstitutionalProfile.types';

export const blankShift = (): HoursShift => ({ start: '08:00', end: '12:00' });
export const maxShifts = 6;

// Um turno: dois campos de hora e a opção "até o fim do dia" (24:00), que um campo de hora não representa.
function ShiftRow({ name, idPrefix, shift, error, onChange, onRemove }: {
  name: string; idPrefix: string; shift: HoursShift; error?: string; onChange: (shift: HoursShift) => void; onRemove: () => void;
}) {
  const endOfDay = shift.end === '24:00';

  return (
    <div className="institutional__shift">
      <div className="institutional__shift-field">
        <span className="institutional__shift-label">Das</span>
        <ClockPicker id={`${idPrefix}-start`} label={`${name}: início`} value={shift.start}
          onChange={(start) => onChange({ ...shift, start })} />
      </div>
      <div className="institutional__shift-field">
        <span className="institutional__shift-label">às</span>
        <ClockPicker id={`${idPrefix}-end`} label={`${name}: término`} value={endOfDay ? '' : shift.end} disabled={endOfDay}
          onChange={(end) => onChange({ ...shift, end })} />
      </div>
      <ChoiceControl type="checkbox" tone="primary" id={`${idPrefix}-end-of-day`} label="Até o fim do dia (24:00)"
        checked={endOfDay} onChange={(checked) => onChange({ ...shift, end: checked ? '24:00' : '12:00' })} />
      <button type="button" className="inputGroup__button contact-fields__remove waves-effect waves-light"
        aria-label={`Remover ${name}`} title="Remover turno" onClick={onRemove}>
        <i className="fa fa-times" aria-hidden="true" />
      </button>
      {error && <p className="formField__error institutional__shift-error" role="alert">{error}</p>}
    </div>
  );
}

// Lista de turnos de um dia ou de uma exceção. Envia campos escondidos no formato do contrato e, sem turnos, o campo
// vazio de propósito (limpar não é omitir).
export default function InstitutionalShiftsEditor({ label, idPrefix, fieldName, errorPrefix, shifts, errors, onChange }: {
  label: string;
  idPrefix: string;
  fieldName: string;
  errorPrefix: string;
  shifts: HoursShift[];
  errors: Record<string, string | undefined>;
  onChange: (shifts: HoursShift[]) => void;
}) {
  return (
    <>
      {shifts.length === 0
        ? <input type="hidden" name={fieldName} value="" />
        : shifts.map((shift, index) => (
          <span key={index}>
            <input type="hidden" name={`${fieldName}[${index}][start]`} value={shift.start} />
            <input type="hidden" name={`${fieldName}[${index}][end]`} value={shift.end} />
          </span>
        ))}
      {shifts.map((shift, index) => (
        <ShiftRow key={index} name={`${label}, turno ${index + 1}`} idPrefix={`${idPrefix}-${index}`} shift={shift}
          error={errors[`${errorPrefix}.${index}.start`] ?? errors[`${errorPrefix}.${index}.end`]}
          onChange={(next) => onChange(shifts.map((item, at) => (at === index ? next : item)))}
          onRemove={() => onChange(shifts.filter((_, at) => at !== index))} />
      ))}
      {shifts.length < maxShifts && (
        <CompactButton icon="fa-plus" aria-label={`Adicionar turno em ${label}`} onClick={() => onChange([...shifts, blankShift()])}>Adicionar turno</CompactButton>
      )}
    </>
  );
}
