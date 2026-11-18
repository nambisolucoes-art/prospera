import { useApiForm as useForm } from '@/hooks/useApiForm';
import { useApiPage as usePage } from '@/hooks/useApiPage';
import Button from '@/components/Button/Button';
import type { SessionProps } from '@/types/Session.types';
import './AccountInspection.css';

export default function AccountInspectionBanner() {
  const { auth } = usePage<SessionProps>().props;
  const exit = useForm({});

  if (!auth.inspection_mode) return null;

  return (
    <aside className="account-inspection-banner" aria-label="Inspeção de conta">
      <div>
        <strong>Inspecionando como {auth.user?.name}</strong>
        <span>{auth.inspection_mode === 'edit' ? 'Leitura e edição' : 'Somente leitura'}</span>
      </div>
      <Button type="button" tone="white" disabled={exit.processing}
        onClick={() => exit.post('/conta/inspecao/encerrar')}>Voltar ao SaaS</Button>
    </aside>
  );
}
