import Tooltip from '@/components/Tooltip/Tooltip';
import './InfoTooltip.css';

export default function InfoTooltip({ text, label }: { text: string; label: string }): JSX.Element {
  return <Tooltip text={text} touch appearance="ubold">
    <button type="button" className="infoTooltip" aria-label={`Mais informações sobre ${label}`}>
      <i className="fa fa-info-circle" aria-hidden="true" />
    </button>
  </Tooltip>;
}
