export type Resolved = {
  value: Record<string, unknown> | null;
  source: 'company' | 'establishment' | 'platform';
  mode: 'override' | 'inherit' | 'disabled' | 'default';
  inherited_from: 'company' | 'platform' | null;
};

export type ProfileSection = {
  key: string;
  label: string;
  kind: 'editorial' | 'operational';
  available: boolean;
  unavailable_reason: string | null;
  can_disable: boolean;
  establishment_only: boolean;
  draft: Resolved;
  published: Resolved;
  pending: boolean;
  empty_value: Record<string, unknown>;
};

export type ProfileOwner = {
  type: 'company' | 'establishment';
  code: string;
  name: string;
  active: boolean;
  company_code: string;
  company_name: string;
  show_public_address: boolean;
  has_address: boolean;
};

export type ProfileFont = { key: string; label: string };
export type ProfileNetwork = { key: string; label: string };
export type ProfileImpact = { changed: string[]; inheriting: number; overriding: number };

// Base das rotas do dono, relativa à conta: `@/empresa/{codigo}` ou `@/estabelecimento/{codigo}`.
export function ownerBase(owner: ProfileOwner) {
  return `@/${owner.type === 'company' ? 'empresa' : 'estabelecimento'}/${owner.code}/institucional`;
}

// De onde vem o valor mostrado: própria, herdada da empresa ou padrão da plataforma.
export function originText(section: ProfileSection, owner: ProfileOwner) {
  const { draft } = section;
  if (draft.mode === 'disabled') return 'Esta seção está desativada neste estabelecimento.';
  if (owner.type === 'establishment') {
    if (draft.source === 'establishment') return 'Informação própria deste estabelecimento.';
    if (draft.inherited_from === 'company') return `Herdado da empresa ${owner.company_name}.`;
    return section.establishment_only ? 'Ainda não cadastrada neste estabelecimento.' : 'Padrão da plataforma: a empresa ainda não definiu esta seção.';
  }
  return draft.source === 'company' ? 'Definido pela empresa.' : 'Padrão da plataforma: ainda não definido.';
}

// Horários (S07). A hora é sempre a hora local da unidade; o fuso IANA é uma coluna do estabelecimento.
export type HoursShift = { start: string; end: string };
export type HoursDayKind = 'open' | 'appointment' | 'closed';
export type HoursDay = { kind: HoursDayKind; shifts: HoursShift[] };
export const weekdays = [
  { key: 'mon', label: 'Segunda-feira', short: 'Seg' },
  { key: 'tue', label: 'Terça-feira', short: 'Ter' },
  { key: 'wed', label: 'Quarta-feira', short: 'Qua' },
  { key: 'thu', label: 'Quinta-feira', short: 'Qui' },
  { key: 'fri', label: 'Sexta-feira', short: 'Sex' },
  { key: 'sat', label: 'Sábado', short: 'Sáb' },
  { key: 'sun', label: 'Domingo', short: 'Dom' },
] as const;
export type WeekdayKey = (typeof weekdays)[number]['key'];
export type HoursValue = { days: Record<WeekdayKey, HoursDay> };

export type HourExceptionKind = 'holiday' | 'temporary_closure' | 'special_hours';
export const exceptionKinds: { value: HourExceptionKind; label: string }[] = [
  { value: 'holiday', label: 'Feriado' },
  { value: 'temporary_closure', label: 'Fechamento temporário' },
  { value: 'special_hours', label: 'Horário especial' },
];

export type HourException = {
  id: number;
  kind: HourExceptionKind;
  starts_on: string;
  ends_on: string;
  shifts: HoursShift[] | null;
  note: string | null;
  applies_to_overrides: boolean;
};

export type OpenStatus = 'open' | 'closed' | 'by_appointment' | 'not_informed' | 'disabled' | 'inactive';

export type EffectiveNow = {
  status: OpenStatus;
  status_label: string;
  closed_reason: 'closed_day' | 'outside_shifts' | 'holiday' | 'temporary_closure' | null;
  schedule: HoursDay | null;
  schedule_source: 'weekly' | 'exception' | null;
  hours_source: 'company' | 'establishment' | 'platform' | null;
  hours_mode: string | null;
  timezone: string;
  local_date: string;
  local_time: string;
  exception: { id: number; kind: HourExceptionKind; owner: 'company' | 'establishment'; starts_on: string; ends_on: string; note: string | null; reached_override: boolean } | null;
  valid_until: string;
};

export type HoursOverview = {
  now: EffectiveNow | null;
  timezone: string | null;
  timezones: { key: string; label: string }[];
  exceptions: HourException[];
  inherited_exceptions: HourException[];
  units: { code: string; status: OpenStatus; status_label: string; timezone: string; hours_source: string | null; hours_mode: string | null; local_time: string }[];
};

export type ExceptionImpact = {
  inheriting: { code: string; name: string }[];
  overriding: { code: string; name: string }[];
  disabled: number;
  token: string;
};

const clock = (shift: HoursShift) => `${shift.start} às ${shift.end}`;

// "08:00 às 12:00 e 14:00 às 18:00".
export function shiftsText(shifts: HoursShift[]) {
  return shifts.map(clock).join(' e ');
}

export function dayText(day: HoursDay) {
  if (day.kind === 'closed') return 'Fechado';
  if (day.kind === 'appointment') {
    return day.shifts.length === 0 ? 'Somente com agendamento' : `Somente com agendamento, ${shiftsText(day.shifts)}`;
  }
  return shiftsText(day.shifts);
}

// Dias seguidos com o mesmo atendimento viram um intervalo: "Seg a Sex".
export function groupWeek(value: HoursValue) {
  const groups: { label: string; text: string }[] = [];
  let run: { from: string; to: string; text: string }[] = [];

  for (const weekday of weekdays) {
    const text = dayText(value.days[weekday.key]);
    const last = run[run.length - 1];
    if (last && last.text === text) last.to = weekday.short;
    else run.push({ from: weekday.short, to: weekday.short, text });
  }

  for (const item of run) groups.push({ label: item.from === item.to ? item.from : `${item.from} a ${item.to}`, text: item.text });

  return groups;
}

export function formatDay(date: string) {
  const [year, month, day] = date.split('-');
  return `${day}/${month}/${year}`;
}

export function formatPeriod(exception: { starts_on: string; ends_on: string }) {
  return exception.starts_on === exception.ends_on
    ? formatDay(exception.starts_on)
    : `${formatDay(exception.starts_on)} a ${formatDay(exception.ends_on)}`;
}
