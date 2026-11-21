import './YesNoControl.css';

export default function YesNoControl({ name, value, onChange, labelledBy, yesValue = '1', noValue = '0' }: {
  name: string;
  value: boolean;
  onChange: (value: boolean) => void;
  labelledBy: string;
  yesValue?: string;
  noValue?: string;
}) {
  return (
    <div className="yesNoControl" role="radiogroup" aria-labelledby={labelledBy}>
      {[true, false].map((option) => (
        <label key={String(option)} className="waves-effect">
          <input
            type="radio"
            name={name}
            value={option ? yesValue : noValue}
            checked={value === option}
            onChange={() => onChange(option)}
          />
          <span>{option ? 'Sim' : 'Não'}</span>
        </label>
      ))}
    </div>
  );
}
