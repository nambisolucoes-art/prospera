import type { ReactNode } from 'react';
import Label, { type LabelVariant } from '@/components/Label/Label';
import './AuditEventLabel.css';

const eventTones: Record<string, LabelVariant> = {
  created: 'success',
  updated: 'warning',
  deleted: 'danger',
  restored: 'info',
  login: 'info',
  logout: 'inverse',
  'inspection-started': 'purple',
  'inspection-ended': 'inverse',
  'catalog-synchronized': 'warning',
};

export default function AuditEventLabel({ event, children }: { event: string; children: ReactNode }) {
  return <Label variant={eventTones[event] ?? 'warning'} className="audit-event-label">{children}</Label>;
}
