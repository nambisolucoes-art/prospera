import Label from '@/components/Label/Label';

export default function StatusLabel({ active }: { active: boolean }) {
  return <Label variant={active ? 'success' : 'danger'}>{active ? 'Ativa' : 'Inativa'}</Label>;
}
