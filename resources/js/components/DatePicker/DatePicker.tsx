import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import InputGroup from '@/components/InputGroup/InputGroup';
import TextInput from '@/components/TextInput/TextInput';
import { loadExternalScript } from '@/utils/loadExternalScript';
import { loadExternalStylesheet } from '@/utils/loadExternalStylesheet';
import { dateCaret, previewDate, displayDate, isoDate, isValidDatePrefix, maskDate } from './DatePicker.utils';
import './DatePicker.css';

type DatePickerCollection = {
  datepicker: (options: Record<string, unknown> | string, value?: unknown) => DatePickerCollection;
  on: (events: string, handler: () => void) => DatePickerCollection;
  off: (events: string) => DatePickerCollection;
  data: (key: string) => { picker?: { 0?: HTMLElement } } | undefined;
};
type DatePickerRuntime = ((element: HTMLElement) => DatePickerCollection) & {
  fn?: { datepicker?: { dates?: Record<string, unknown> } };
};

const locale = {
  days: ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'],
  daysShort: ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'],
  daysMin: ['Do', 'Se', 'Te', 'Qu', 'Qu', 'Se', 'Sa', 'Do'],
  months: ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'],
  monthsShort: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
  today: 'Hoje',
  clear: 'Limpar',
  format: 'dd/mm/yyyy',
};

async function loadRuntime(): Promise<DatePickerRuntime> {
  await loadExternalStylesheet('/assets/plugins/bootstrap-datepicker/css/bootstrap-datepicker.min.css');
  await loadExternalScript('/assets/js/jquery.min.js');
  await loadExternalScript('/assets/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js');
  const runtime = (window as Window & { jQuery?: DatePickerRuntime }).jQuery;
  const dates = runtime?.fn?.datepicker?.dates;
  if (!runtime || !dates) throw new Error('Calendário indisponível.');
  dates['pt-BR'] ??= locale;
  return runtime;
}

/**
 * Bootstrap Datepicker of the Bold theme.
 * Receives and emits `AAAA-MM-DD`; partial typing is kept locally and only complete dates are emitted.
 */
export default function DatePicker({
  id, label, value, onChange, name, min, max, placeholder = 'DD/MM/AAAA',
  required = false, disabled = false, invalid = false, describedBy,
}: {
  id: string; label: string; value: string; onChange: (value: string) => void; name?: string;
  min?: string; max?: string; placeholder?: string; required?: boolean; disabled?: boolean;
  invalid?: boolean; describedBy?: string;
}) {
  const input = useRef<HTMLInputElement>(null);
  const instance = useRef<DatePickerCollection | undefined>(undefined);
  const [display, setDisplay] = useState(() => displayDate(value));
  const [invalidEntry, setInvalidEntry] = useState(false);
  const [loadFailed, setLoadFailed] = useState(false);
  const callback = useRef(onChange);
  callback.current = onChange;
  const emitted = useRef(value);
  const syncing = useRef(false);
  const pendingCaret = useRef<number | null>(null);
  const viewBase = useRef(new Date());
  const displayRef = useRef(display);

  function emit(next: string) {
    if (next === emitted.current) return;
    emitted.current = next;
    callback.current(next);
  }

  function edit(rawValue: string, caret: number) {
    const formatted = maskDate(rawValue);
    pendingCaret.current = dateCaret(formatted, rawValue.slice(0, caret).replace(/\D/g, '').length);
    setDisplay(formatted);
    setInvalidEntry(!isValidDatePrefix(formatted));
    if (!formatted) emit('');
    else if (formatted.length === 10 && isoDate(formatted)) emit(isoDate(formatted));
  }

  useLayoutEffect(() => {
    if (pendingCaret.current === null || document.activeElement !== input.current) return;
    input.current?.setSelectionRange(pendingCaret.current, pendingCaret.current);
    pendingCaret.current = null;
  });

  useEffect(() => {
    if (value === emitted.current) return;
    emitted.current = value;
    setDisplay(displayDate(value));
    setInvalidEntry(false);
  }, [value]);

  // O calendário acompanha a digitação desde o dia: o que falta (mês ou ano) vem do último mês e ano já digitados (no
  // início, de hoje), só para marcar e navegar. O texto do campo não muda e o valor só é emitido com a data completa.
  function previewTyped() {
    const field = input.current;
    if (!field) return;
    const typed = displayRef.current;
    const [, typedMonth = '', typedYear = ''] = typed.split('/');
    if (/^(0[1-9]|1[0-2])$/.test(typedMonth)) viewBase.current.setMonth(Number(typedMonth) - 1, 1);
    if (typedYear.length === 4) viewBase.current.setFullYear(Number(typedYear));
    const candidate = previewDate(typed, viewBase.current);
    if (candidate && candidate !== typed) field.value = candidate;
    try {
      syncing.current = true;
      instance.current?.datepicker('update');
    } finally {
      syncing.current = false;
      if (field.value !== typed) field.value = typed;
    }
  }
  const previewRef = useRef(previewTyped);
  previewRef.current = previewTyped;
  displayRef.current = display;

  useEffect(() => { previewRef.current(); }, [display]);

  useEffect(() => {
    // O plugin relê o texto parcial a cada tecla e apagaria a seleção, o que piscava o calendário antes da prévia. A
    // digitação (teclas que mudam o texto) não chega a ele: a prévia acima já cuida do calendário.
    const field = input.current;
    const navigation = ['Escape', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Enter', 'Tab', ' '];
    const block = (event: KeyboardEvent) => { if (!navigation.includes(event.key)) event.stopImmediatePropagation(); };
    field?.addEventListener('keyup', block, true);
    return () => field?.removeEventListener('keyup', block, true);
  }, []);

  useEffect(() => {
    instance.current?.datepicker('setStartDate', min ? displayDate(min) : '');
    instance.current?.datepicker('setEndDate', max ? displayDate(max) : '');
  }, [min, max]);

  useEffect(() => {
    if (disabled) instance.current?.datepicker('hide');
  }, [disabled]);

  useEffect(() => {
    let cancelled = false;
    let picker: DatePickerCollection | undefined;
    loadRuntime().then((runtime) => {
      if (cancelled || !input.current) return;
      picker = runtime(input.current);
      picker.datepicker({
        language: 'pt-BR',
        format: 'dd/mm/yyyy',
        autoclose: true,
        todayHighlight: true,
        orientation: 'top auto', // no plugin, "top" abre o calendário abaixo do campo
        forceParse: false,
        clearBtn: true,
        startDate: min ? displayDate(min) : undefined,
        endDate: max ? displayDate(max) : undefined,
      });
      picker.data('datepicker')?.picker?.[0]?.classList.add('datePicker__popup');
      picker.on('changeDate.prosperaDatePicker clearDate.prosperaDatePicker', () => {
        if (cancelled || syncing.current) return;
        const selected = input.current?.value ?? '';
        setDisplay(selected);
        setInvalidEntry(false);
        emit(isoDate(selected));
      });
      instance.current = picker;
    }).catch(() => {
      if (!cancelled) setLoadFailed(true);
    });
    return () => {
      cancelled = true;
      picker?.off('.prosperaDatePicker');
      picker?.datepicker('remove');
      instance.current = undefined;
    };
    // The plugin is created once; limits and value are synchronized by the effects above.
  }, []);

  const description = [describedBy, invalidEntry && `${id}-date-error`, loadFailed && `${id}-picker-error`]
    .filter(Boolean).join(' ') || undefined;

  return (
    <div className="datePicker">
      <InputGroup action={
        <button type="button" className="inputGroup__button datePicker__toggle waves-effect" disabled={disabled || loadFailed}
          aria-label={`Abrir calendário: ${label}`} title="Abrir calendário"
          onClick={() => { input.current?.focus(); instance.current?.datepicker('show'); }}>
          <i className="fa fa-calendar" aria-hidden="true" />
        </button>
      }>
        <TextInput ref={input} id={id} value={display} placeholder={placeholder} required={required}
          disabled={disabled} invalid={invalid || invalidEntry} inputMode="numeric" autoComplete="off"
          aria-label={label} aria-describedby={description}
          onChange={(event) => edit(event.target.value, event.target.selectionStart ?? event.target.value.length)}
          onBlur={() => {
            // Incomplete or impossible dates are not kept as a value.
            if (display && !isoDate(display)) { setInvalidEntry(true); emit(''); }
          }}
          onKeyDown={(event) => {
            const control = event.currentTarget;
            const start = control.selectionStart ?? 0;
            if (start !== control.selectionEnd) return;
            // Remove the adjacent digit when the caret crosses an automatic slash.
            if (event.key === 'Backspace' && control.value[start - 1] === '/') {
              event.preventDefault();
              edit(control.value.slice(0, start - 2) + control.value.slice(start), start - 2);
            } else if (event.key === 'Delete' && control.value[start] === '/') {
              event.preventDefault();
              edit(control.value.slice(0, start) + control.value.slice(start + 2), start);
            }
          }} />
      </InputGroup>
      {/* O campo visível mostra DD/MM/AAAA; o formulário recebe sempre AAAA-MM-DD, só quando a data está completa. */}
      {name && <input type="hidden" name={name} value={value} />}
      {invalidEntry && <span id={`${id}-date-error`} role="alert" className="datePicker__error">Informe uma data real no formato DD/MM/AAAA.</span>}
      {loadFailed && <span id={`${id}-picker-error`} role="alert" className="datePicker__error">Não foi possível carregar o calendário. Digite a data no formato DD/MM/AAAA.</span>}
    </div>
  );
}
