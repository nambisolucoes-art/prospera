import Label from '@/components/Label/Label';

export default function StatusLabel({ active, masculine = false }: { active: boolean; masculine?: boolean }) {
  const text = masculine ? (active ? 'Ativo' : 'Inativo') : (active ? 'Ativa' : 'Inativa');
  return <Label variant={active ? 'success' : 'danger'}>{text}</Label>;
}
