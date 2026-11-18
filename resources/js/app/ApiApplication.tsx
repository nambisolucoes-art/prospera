import { useCallback, useEffect, useRef, useState, type ComponentType, type JSX } from 'react';
import Theme from '@/app/App';
import FlashLayout from '@/app/FlashLayout';
import SettingsLayout from '@/app/account/SettingsLayout';
import { ApiPageContext, type NavigationOptions } from '@/hooks/useApiPage';
import type { SessionProps } from '@/types/Session.types';
import { ApiError, apiRequest } from '@/utils/apiRequest';
import { markAppPageTransitionReady } from '@/utils/appPageTransition';

const screens = import.meta.glob<{ default: ComponentType }>([
  './account/{auth,settings}/*.tsx',
  './saas/{clients,account-form,tenant,users,user-form}.tsx',
]);

const routes: Array<[RegExp, string]> = [
  [/^\/dashboard$/, 'saas/clients'],
  [/^\/conta$/, 'saas/tenant'],
  [/^\/(?:saas\/contas\/(?:cadastrar|\d+\/editar)|conta\/editar)$/, 'saas/account-form'],
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
  if (path === '/user/confirm-password' && props.forSecurity) return 'settings/confirm-security';
  for (const [pattern, component] of routes) {
    if (pattern.test(path)) return path.replace(pattern, component);
  }
  return null;
}

type Page = { component: string; Component: ComponentType; url: string; props: SessionProps };
type PageData = SessionProps & { redirect?: string };

export default function ApiApplication(): JSX.Element {
  const [page, setPage] = useState<Page>();
  const [error, setError] = useState('');
  const request = useRef<AbortController>(null);

  const navigate = useCallback(async (url: string, options: NavigationOptions = {}) => {
    request.current?.abort();
    const pending = new AbortController();
    request.current = pending;
    setError('');

    try {
      let target = new URL(url, location.origin);
      for (let redirects = 0; redirects < 8; redirects++) {
        let props: PageData;
        try {
          props = await apiRequest<PageData>(target.href, { signal: pending.signal });
        } catch (failure) {
          if (failure instanceof ApiError && failure.status === 401 && target.pathname !== '/login') {
            target = new URL('/login', location.origin);
            continue;
          }
          if (failure instanceof ApiError && failure.status === 403 && failure.message === 'Your email address is not verified.') {
            target = new URL('/email/verify', location.origin);
            continue;
          }
          throw failure;
        }
        if (props.redirect) {
          target = new URL(props.redirect, location.origin);
          continue;
        }
        const component = componentFor(target.pathname, props);
        const resolve = component && (screens[`./account/${component}.tsx`] ?? screens[`./${component}.tsx`]);
        const Component = component === 'theme' ? Theme : resolve ? (await resolve()).default : null;
        if (!component || !Component) throw new Error('Página não encontrada.');
        if (pending.signal.aborted) return false;

        const nextUrl = target.pathname + target.search + target.hash;
        if (target.href !== location.href) {
          if (options.replace) history.replaceState(null, '', nextUrl);
          else history.pushState(null, '', nextUrl);
          if (!options.preserveScroll) scrollTo(0, 0);
        }
        setPage({ component, Component, url: nextUrl, props });
        return true;
      }
      throw new Error('Não foi possível abrir a página.');
    } catch (failure) {
      if (!pending.signal.aborted) setError(failure instanceof Error ? failure.message : 'Não foi possível abrir a página.');
      return false;
    } finally {
      if (!pending.signal.aborted) requestAnimationFrame(markAppPageTransitionReady);
    }
  }, []);

  useEffect(() => {
    const restore = () => void navigate(location.href, { replace: true, preserveScroll: true });
    restore();
    window.addEventListener('popstate', restore);
    return () => {
      request.current?.abort();
      window.removeEventListener('popstate', restore);
    };
  }, [navigate]);

  if (error) return <main className="card-box" role="alert"><p>{error}</p><a href="/dashboard">Voltar ao painel</a></main>;
  if (!page) return <p role="status">Carregando…</p>;

  const { Component, component, props } = page;
  const content = <Component key={component} {...props} />;

  return (
    <ApiPageContext.Provider value={{ ...page, navigate }}>
      <FlashLayout>{component.startsWith('settings/') ? <SettingsLayout>{content}</SettingsLayout> : content}</FlashLayout>
    </ApiPageContext.Provider>
  );
}
