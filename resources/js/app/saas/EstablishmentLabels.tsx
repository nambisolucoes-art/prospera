import Label from '@/components/Label/Label';
import { classificationLabels, type Classification } from './Organization.types';
import './Organization.css';

const variants = { headquarters: 'purple', branch: 'info', unregistered: 'inverse' } as const;

export default function EstablishmentLabels({ classification, primary }: { classification: Classification; primary: boolean }) {
  return (
    <span className="organization-badges">
      {primary && <Label variant="primary">Principal</Label>}
      <Label variant={variants[classification]}>{classificationLabels[classification]}</Label>
    </span>
  );
}
