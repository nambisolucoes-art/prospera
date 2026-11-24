import { useCallback, useContext } from 'react';
import { ApiPageContext, useApiPage } from '@/hooks/useApiPage';
import { contextualUrl } from '@/utils/organizationContext';

/** Para chamadas diretas a apiRequest e fetch: resolve @/ e ~/ no contexto da página atual desta aba. */
export function useContextUrl() {
  const { url } = useApiPage();
  return useCallback((target: string) => contextualUrl(target, url), [url]);
}

/**
 * Para componentes compartilhados que também aparecem fora da aplicação (telas do tema): resolve @/ e ~/
 * quando há página da API e devolve o endereço intacto nas demais.
 */
export function useOptionalContextUrl() {
  const url = useContext(ApiPageContext)?.url;
  return useCallback((target: string) => (url ? contextualUrl(target, url) : target), [url]);
}
