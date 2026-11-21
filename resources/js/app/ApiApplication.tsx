import {
  useCallback,
  useEffect,
  useRef,
  useState,
  type ComponentType,
  type JSX,
} from 'react';
import Theme from '@/app/App';
import FlashLayout from '@/app/FlashLayout';
import SettingsLayout from '@/app/account/SettingsLayout';
import { ApiPageContext, type NavigationOptions } from '@/hooks/useApiPage';
import type { SessionProps } from '@/types/Session.types';
import { ApiError, apiRequest } from '@/utils/apiRequest';
import {
  appLoaderPreviewEnabled,
  markAppPageTransitionReady,
  startAppPageTransition,
} from '@/utils/appPageTransition';
import { contextBase, contextualUrl } from '@/utils/organizationContext';
import {
  clearPageAssetRetry,
  isPageAssetError,
  reloadPageAssets,
} from '@/utils/pageAssetRecovery';

const screens = import.meta.glob<{ default: ComponentType }>([
  './account/{auth,settings}/*.tsx',
  './saas/{clients,account-form,home,users,user-form,tags,roles,role-form,audit,help}.tsx',
  './saas/{organization,companies,company-form,establishments,establishment-form,establishment-home}.tsx',
  './saas/{commercial-relations,commercial-registration}.tsx',
]);

const routes: Array<[RegExp, string]> = [
  [/^\/(?:saas|conta)\/auditoria$/, 'saas/audit'],
  [/^\/ajuda(?:\/[a-z0-9-]+)?$/, 'saas/help'],
  [/^\/dashboard$/, 'saas/clients'],
  [/^\/conta$/, 'saas/home'],
  [/^\/conta\/organizacao$/, 'saas/organization'],
  [/^\/conta\/empresas$/, 'saas/companies'],
  [/^\/conta\/empresas\/(?:cadastrar|[0-9]+\/editar)$/, 'saas/company-form'],
  [/^\/conta\/estabelecimentos$/, 'saas/establishments'],
  [/^\/conta\/estabelecimento\/[a-z0-9][a-z0-9-]{1,39}$/, 'saas/establishment-home'],
  [/^\/conta\/estabelecimento\/[a-z0-9][a-z0-9-]{1,39}\/(?:clientes|fornecedores)$/, 'saas/commercial-relations'],
  [/^\/conta\/estabelecimento\/[a-z0-9][a-z0-9-]{1,39}\/(?:clientes|fornecedores)\/(?:cadastrar|[a-z0-9][a-z0-9-]{1,39}\/\d+\/editar)$/, 'saas/commercial-registration'],
  [/^\/conta\/estabelecimentos\/(?:cadastrar|[0-9]+\/editar)$/, 'saas/establishment-form'],
  [
    /^\/(?:saas\/contas\/(?:cadastrar|\d+\/editar)|conta\/editar)$/,
    'saas/account-form',
  ],
  [/^\/conta\/cargos$/, 'saas/roles'],
  [/^\/conta\/cargos\/(?:cadastrar|[0-9]+\/editar)$/, 'saas/role-form'],
  [/^\/conta\/etiquetas$/, 'saas/tags'],
  [/^\/conta\/etiquetas\/(?:cadastrar|[0-9]+\/editar)$/, 'saas/tags'],
  [/^\/(?:saas|conta)\/usuarios$/, 'saas/users'],
  [/^\/(?:saas|conta)\/usuarios\/cadastrar$/, 'saas/user-form'],
  [/^\/settings\/(profile|security|appearance)$/, 'settings/$1'],
  [/^\/(login|forgot-password|two-factor-challenge)$/, 'auth/$1'],
  [/^\/reset-password\/[^/]+$/, 'auth/reset-password'],
  [/^\/email\/verify$/, 'auth/verify-email'],
  [/^\/user\/confirm-password$/, 'auth/confirm-password'],
  [/^\/[a-z0-9][a-z0-9_-]*\.html$/, 'theme'],
];

function componentFor(path: string, props: SessionProps) {
  if (path === '/user/confirm-password' && props.forSecurity)
    return 'settings/confirm-security';
  for (const [pattern, component] of routes) {
    if (pattern.test(path)) return path.replace(pattern, component);
  }
  return null;
}

type Page = {
  component: string;
  Component: ComponentType;
  url: string;
  props: SessionProps;
  transitionId?: number;
};
type PageData = SessionProps & { redirect?: string };

export default function ApiApplication(): JSX.Element {
  const [page, setPage] = useState<Page>();
  const [error, setError] = useState('');
  const [failedAssetUrl, setFailedAssetUrl] = useState('');
  const [contextFailure, setContextFailure] = useState(false);
  const request = useRef<AbortController>(null);
  const currentPageUrl = useRef<string | undefined>(undefined);

  const navigate = useCallback(
    async (address: string, options: NavigationOptions = {}) => {
      // Ponto oficial de resolução de ~/: o contexto da página atual desta aba.
      let url: string;
      try {
        url = contextualUrl(address, currentPageUrl.current ?? location.href);
      } catch (failure) {
        setError(failure instanceof Error ? failure.message : 'Não foi possível abrir a página.');
        return false;
      }

      request.current?.abort();
      const pending = new AbortController();
      request.current = pending;
      const changesPage = !currentPageUrl.current
        || new URL(url, location.origin).pathname !== new URL(currentPageUrl.current, location.origin).pathname;
      const transitionId = changesPage || appLoaderPreviewEnabled ? startAppPageTransition() : undefined;
      let committed = false;
      setError('');
      setFailedAssetUrl('');
      setContextFailure(false);

      try {
        let target = new URL(url, location.origin);
        for (let redirects = 0; redirects < 8; redirects++) {
          let props: PageData;
          try {
            props = await apiRequest<PageData>(target.href, {
              signal: pending.signal,
            });
          } catch (failure) {
            if (
              failure instanceof ApiError &&
              failure.status === 401 &&
              target.pathname !== '/login'
            ) {
              target = new URL('/login', location.origin);
              continue;
            }
            if (
              failure instanceof ApiError &&
              failure.status === 403 &&
              failure.message === 'Your email address is not verified.'
            ) {
              target = new URL('/email/verify', location.origin);
              continue;
            }
            // Unidade inexistente, sem acesso ou inativa: orienta sem revelar se o código existe.
            if (
              failure instanceof ApiError &&
              [403, 404].includes(failure.status) &&
              contextBase(target.href)
            ) {
              setContextFailure(true);
              throw new Error(
                failure.status === 403 && failure.message.includes('inativ')
                  ? failure.message
                  : 'A seleção contém um estabelecimento indisponível. Abra uma área de trabalho autorizada.',
              );
            }
            throw failure;
          }
          if (props.redirect) {
            target = new URL(props.redirect, location.origin);
            continue;
          }
          const component = componentFor(target.pathname, props);
          const resolve =
            component &&
            (screens[`./account/${component}.tsx`] ??
              screens[`./${component}.tsx`]);
          let Component: ComponentType | null;
          try {
            Component =
              component === 'theme'
                ? Theme
                : resolve
                  ? (await resolve()).default
                  : null;
          } catch (failure) {
            if (pending.signal.aborted) return false;
            if (isPageAssetError(failure)) {
              if (reloadPageAssets(target, options.replace)) return false;
              setFailedAssetUrl(target.pathname + target.search + target.hash);
              throw new Error(
                'Não foi possível carregar esta tela. Recarregue para tentar novamente.',
              );
            }
            throw failure;
          }
          if (!component || !Component)
            throw new Error('Página não encontrada.');
          if (pending.signal.aborted) return false;

          clearPageAssetRetry(target);
          const nextUrl = target.pathname + target.search + target.hash;
          if (target.href !== location.href) {
            if (options.replace) history.replaceState(null, '', nextUrl);
            else history.pushState(null, '', nextUrl);
            if (!options.preserveScroll) scrollTo(0, 0);
          }
          currentPageUrl.current = nextUrl;
          setPage({ component, Component, url: nextUrl, props, transitionId });
          committed = true;
          return true;
        }
        throw new Error('Não foi possível abrir a página.');
      } catch (failure) {
        if (!pending.signal.aborted)
          setError(
            failure instanceof Error
              ? failure.message
              : 'Não foi possível abrir a página.',
          );
        return false;
      } finally {
        if (!committed) markAppPageTransitionReady(transitionId);
      }
    },
    [],
  );

  useEffect(() => {
    if (!page?.component.startsWith('auth/')) return;
    const frame = requestAnimationFrame(() => markAppPageTransitionReady(page.transitionId));
    return () => cancelAnimationFrame(frame);
  }, [page]);

  useEffect(() => {
    const restore = () =>
      void navigate(location.href, { replace: true, preserveScroll: true });
    restore();
    window.addEventListener('popstate', restore);
    return () => {
      request.current?.abort();
      window.removeEventListener('popstate', restore);
    };
  }, [navigate]);

  if (error)
    return (
      <main className="card-box" role="alert">
        <p>{error}</p>
        {failedAssetUrl && (
          <p>
            <button
              type="button"
              className="button button--primary waves-effect waves-light"
              onClick={() => location.assign(failedAssetUrl)}
            >
              Recarregar tela
            </button>
          </p>
        )}
        {contextFailure && (
          <p>
            <a href="/conta/estabelecimento">Abrir área de trabalho</a>
          </p>
        )}
        <a href="/dashboard">Voltar ao painel</a>
      </main>
    );
  if (!page) return <p role="status">Carregando…</p>;

  const { Component, component, props, url } = page;
  const base = contextBase(url);
  const selection = base?.endsWith('/estabelecimento/todos')
    ? new URL(url, window.location.origin).searchParams.get('unidades')
    : null;
  // Formulários e modais são vinculados ao contexto da página em que abriram.
  const content = (
    <Component key={`${component}:${base ?? ''}:${selection ?? ''}`} {...props} />
  );

  return (
    <ApiPageContext.Provider value={{ ...page, navigate }}>
      <FlashLayout>
        {component.startsWith('settings/') ? (
          <SettingsLayout>{content}</SettingsLayout>
        ) : (
          content
        )}
      </FlashLayout>
    </ApiPageContext.Provider>
  );
}
