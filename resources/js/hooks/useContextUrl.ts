import { useCallback } from 'react';
import { useApiPage } from '@/hooks/useApiPage';
import { contextualUrl } from '@/utils/organizationContext';

/** Para chamadas diretas a apiRequest: resolve ~/ no contexto da página atual desta aba. */
export function useContextUrl() {
  const { url } = useApiPage();
  return useCallback((target: string) => contextualUrl(target, url), [url]);
}
