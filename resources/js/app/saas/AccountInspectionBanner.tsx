import { useApiForm as useForm } from '@/hooks/useApiForm';
import { useApiPage as usePage } from '@/hooks/useApiPage';
import type { SessionProps } from '@/types/Session.types';
import './AccountInspection.css';

export default function AccountInspectionBanner() {
  const { auth } = usePage<SessionProps>().props;
  const exit = useForm({});

  if (!auth.inspection_mode) return null;

  return (
    <aside className="account-inspection-banner" aria-label="Inspeção de conta" data-tour="account-inspection-banner">
      <div className="account-inspection-banner__context">
        <i className="fa fa-eye account-inspection-banner__icon" aria-hidden="true" />
        <div className="account-inspection-banner__identity">
          <strong>Acessando como {auth.user?.name}</strong>
          <span className="account-inspection-banner__mode">{auth.inspection_mode === 'edit' ? 'Leitura e edição' : 'Somente leitura'}</span>
        </div>
      </div>
      <button type="button" className="account-inspection-banner__exit"
        title="Voltar ao SaaS" aria-label="Voltar ao SaaS" disabled={exit.processing}
        onClick={() => exit.post('/conta/inspecao/encerrar')}>
        <i className="icon-logout" aria-hidden="true" />
      </button>
    </aside>
  );
}
