import { groupWeek, type HoursValue } from './InstitutionalProfile.types';

// Grade semanal efetiva, com os dias seguidos iguais agrupados ("Seg a Sex"). A hora é a hora local da unidade.
export default function InstitutionalHoursSummary({ value }: { value: HoursValue }) {
  const groups = groupWeek(value);

  if (groups.length === 1 && groups[0].text === 'Fechado') {
    return <p className="institutional__empty">Fechado todos os dias (nenhum horário de atendimento informado).</p>;
  }

  return (
    <dl className="institutional__summary institutional__week" data-testid="hours-week">
      {groups.map((group) => (
        <div key={group.label}>
          <dt>{group.label}</dt>
          <dd>{group.text}</dd>
        </div>
      ))}
    </dl>
  );
}
