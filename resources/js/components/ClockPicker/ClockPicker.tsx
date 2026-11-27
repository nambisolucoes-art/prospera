import { useEffect, useRef, useState } from 'react';
import InputGroup from '@/components/InputGroup/InputGroup';
import TextInput from '@/components/TextInput/TextInput';
import { loadExternalScript } from '@/utils/loadExternalScript';
import { loadExternalStylesheet } from '@/utils/loadExternalStylesheet';
import { maskTime, timeCaret } from './ClockPicker.utils';
import './ClockPicker.css';

type ClockWidget = {
  hours: number; minutes: number;
  spanHours: { html: (value: string) => void }; spanMinutes: { html: (value: string) => void };
  toggleView: (view: 'hours' | 'minutes') => void;
};
type ClockCollection = {
  clockpicker: (options?: Record<string, unknown> | string) => ClockCollection;
  data: (key: string) => ClockWidget | undefined;
  on: (events: string, handler: () => void) => ClockCollection;
  off: (events: string) => ClockCollection;
};
type ClockRuntime = (element: HTMLElement) => ClockCollection;

const validTime = /^([01]\d|2[0-3]):[0-5]\d$/;

async function loadRuntime(): Promise<ClockRuntime> {
  await loadExternalStylesheet('/assets/plugins/clockpicker/jquery-clockpicker.min.css');
  await loadExternalScript('/assets/js/jquery.min.js');
  await loadExternalScript('/assets/plugins/clockpicker/jquery-clockpicker.min.js');
  const runtime = (window as Window & { jQuery?: ClockRuntime & { fn?: { clockpicker?: unknown } } }).jQuery;
  if (!runtime?.fn?.clockpicker) throw new Error('Relógio indisponível.');

  return runtime;
}

/**
 * Hora (24 h, `HH:MM`) com o relógio circular do tema Bold. Controlado: `value` vazio limpa o campo e `onChange`
 * recebe `HH:MM` completo ou texto vazio enquanto a digitação estiver incompleta.
 */
export default function ClockPicker({ id, label, value, onChange, required = false, disabled = false, invalid = false, describedBy }: {
  id: string; label: string; value: string; onChange: (value: string) => void;
  required?: boolean; disabled?: boolean; invalid?: boolean; describedBy?: string;
}) {
  const input = useRef<HTMLInputElement>(null);
  const widget = useRef<ClockWidget | null>(null);
  const callback = useRef(onChange);
  const [display, setDisplay] = useState(value);
  const [invalidEntry, setInvalidEntry] = useState(false);
  const [loadFailed, setLoadFailed] = useState(false);
  const lastValid = useRef(value);
  callback.current = onChange;

  // O relógio acompanha o que é digitado: horas preenchidas marcam a hora, minutos preenchidos marcam o minuto.
  function syncDial(text: string): void {
    const dial = widget.current;
    if (!dial) return;
    const [hourPart = '', minutePart = ''] = text.split(':');
    if (hourPart) { dial.hours = Number(hourPart); dial.spanHours.html(hourPart.padStart(2, '0')); }
    if (minutePart) { dial.minutes = Number(minutePart); dial.spanMinutes.html(minutePart.padStart(2, '0')); }
    dial.toggleView(text.includes(':') ? 'minutes' : 'hours');
  }

  function edit(raw: string, caret?: number): void {
    const result = maskTime(raw);
    if (!result.valid) {
      // Hora ou minuto impossível (por exemplo 25): mantém o último texto válido.
      setInvalidEntry(true);
      setDisplay(lastValid.current);
      return;
    }
    setInvalidEntry(false);
    lastValid.current = result.value;
    setDisplay(result.value);
    syncDial(result.value);
    callback.current(result.complete ? result.value : '');
    if (caret !== undefined) {
      const digits = raw.slice(0, caret).replace(/\D/g, '').length;
      requestAnimationFrame(() => {
        if (document.activeElement !== input.current) return;
        const next = timeCaret(result.value, digits, caret === raw.length);
        input.current?.setSelectionRange(next, next);
      });
    }
  }
  const editRef = useRef(edit);
  editRef.current = edit;

  // Mudança externa do valor (ex.: "até o fim do dia" limpa o término); a digitação incompleta não é apagada.
  useEffect(() => {
    const next = validTime.test(value) ? value : '';
    const current = validTime.test(lastValid.current) ? lastValid.current : '';
    if (next === current) return;
    lastValid.current = next;
    setDisplay(next);
    setInvalidEntry(false);
    if (next) syncDial(next);
  }, [value]);

  useEffect(() => {
    let cancelled = false;
    let picker: ClockCollection | undefined;
    loadRuntime().then((runtime) => {
      if (cancelled || !input.current) return;
      picker = runtime(input.current);
      picker.clockpicker({ twelvehour: false, autoclose: false, donetext: 'Concluir', 'default': lastValid.current || 'now' });
      widget.current = picker.data('clockpicker') ?? null;
      picker.on('change.prosperaClock', () => {
        if (!cancelled) editRef.current(input.current?.value ?? '');
      });
    }).catch(() => {
      if (!cancelled) setLoadFailed(true);
    });

    return () => {
      cancelled = true;
      widget.current = null;
      picker?.off('.prosperaClock');
      picker?.clockpicker('remove');
    };
    // O plugin nasce uma vez; o valor é sincronizado pelo estado.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const description = [describedBy, invalidEntry && `${id}-time-error`, loadFailed && `${id}-clock-error`]
    .filter(Boolean).join(' ') || undefined;

  return (
    <div className="clockPicker">
      <InputGroup action={
        <button type="button" className="inputGroup__button waves-effect" disabled={disabled || loadFailed}
          aria-label={`Abrir relógio: ${label}`} title="Abrir relógio"
          onClick={(event) => { event.stopPropagation(); input.current?.focus(); input.current?.click(); }}>
          <i className="fa fa-clock-o" aria-hidden="true" />
        </button>
      }>
        <TextInput ref={input} id={id} value={display} placeholder="HH:MM" required={required} disabled={disabled}
          invalid={invalid || invalidEntry} inputMode="numeric" autoComplete="off" aria-label={label}
          aria-describedby={description} onChange={(event) => edit(event.target.value, event.target.selectionStart ?? event.target.value.length)}
          onBlur={() => { if (display && !validTime.test(display)) { setInvalidEntry(true); callback.current(''); } }} />
      </InputGroup>
      {invalidEntry && <span id={`${id}-time-error`} role="alert" className="clockPicker__error">Informe uma hora entre 00:00 e 23:59.</span>}
      {loadFailed && <span id={`${id}-clock-error`} role="alert" className="clockPicker__error">Não foi possível carregar o relógio. Digite a hora no formato HH:MM.</span>}
    </div>
  );
}
