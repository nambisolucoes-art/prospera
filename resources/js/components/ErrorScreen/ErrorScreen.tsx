import { useEffect } from 'react';
import AccountLayout from '@/components/AccountLayout/AccountLayout';
import ErrorState from '@/components/ErrorState/ErrorState';
import type { ErrorAction, ErrorCode, ErrorDescription } from '@/components/ErrorState/ErrorState.types';
import StatusPage from '@/components/StatusPage/StatusPage';

const supported: ErrorCode[] = ['400', '403', '404', '419', '429', '500', '503'];

/** Código com conteúdo próprio no `ErrorState`; os demais status usam a página de erro 500. */
export function errorCodeFromStatus(status: number): ErrorCode {
  const code = String(status) as ErrorCode;

  return supported.includes(code) ? code : '500';
}

/**
 * Tela única de erro da aplicação, fora do layout autenticado (funciona sem sessão): falhas de navegação,
 * página inexistente, acesso negado, limite de requisições e manutenção.
 */
export default function ErrorScreen({ code, description, actions }: {
  code: ErrorCode;
  description?: ErrorDescription;
  actions?: ErrorAction[];
}): JSX.Element {
  useEffect(() => {
    document.title = code === '503' ? 'Em manutenção | Prospera' : `${code}, Erro | Prospera`;
  }, [code]);

  if (code === '503') return <StatusPage kind="maintenance" />;

  return <AccountLayout>
    <ErrorState code={code} description={description} actions={actions} homeHref="/dashboard" />
  </AccountLayout>;
}
