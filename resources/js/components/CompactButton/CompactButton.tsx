import type { ReactNode } from 'react';
import './CompactButton.css';

/** Ação secundária compacta, com o ícone num bloco à esquerda: adicionar, editar, copiar, voltar a herdar. */
export default function CompactButton({ icon, children, ...props }: {
  icon: string; children: ReactNode; disabled?: boolean; onClick?: () => void;
  'aria-label'?: string; 'aria-haspopup'?: 'dialog'; 'aria-pressed'?: boolean;
}) {
  return (
    <button type="button" className="compact-button waves-effect waves-light" {...props}>
      <span aria-hidden="true"><i className={`fa ${icon}`} /></span>{children}
    </button>
  );
}
