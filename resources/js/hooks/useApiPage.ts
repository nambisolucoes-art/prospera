import { createContext, useContext } from 'react';
import type { SessionProps } from '@/types/Session.types';

export type NavigationOptions = { preserveScroll?: boolean; replace?: boolean };
type ApiPage = {
  url: string;
  component: string;
  transitionId?: number;
  props: SessionProps;
  navigate: (url: string, options?: NavigationOptions) => Promise<boolean>;
};

export const ApiPageContext = createContext<ApiPage | null>(null);

export function useApiPage<T = SessionProps>() {
  const page = useContext(ApiPageContext);
  if (!page) throw new Error('Página fora do contexto da aplicação.');

  return { ...page, props: page.props as T };
}
