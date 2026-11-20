import { tourFor } from './guidance';
import { useApiPage } from '@/hooks/useApiPage';
import type { SessionProps } from '@/types/Session.types';
import { Suspense, useEffect, useLayoutEffect, useRef, type ReactNode } from 'react';
import { usePathname } from '@/hooks/usePathname';
import { useSidebarState, type SidebarState } from './useSidebarState';
import { getCurrentPageKey } from './routes';
import AppFooter from '@/components/AppFooter/AppFooter';
import PageScrollbar from '@/components/PageScrollbar/PageScrollbar';
import type { SidebarEntry } from '@/components/SidebarPrimary/SidebarPrimary.types';
import SidebarPrimary from '@/components/SidebarPrimary/SidebarPrimary';
import TopBarPrimary from '@/components/TopBarPrimary/TopBarPrimary';
import { pages } from '@/pages/Pages/Pages.data';
import { extras } from '@/pages/Extras/Extras.data';
import { layouts } from '@/pages/Layouts/Layouts.data';
import { useLayoutSidebar } from '@/pages/Layouts/useLayoutSidebar';
import { sidebarUser } from '@/components/SidebarUser/SidebarUser.data';
import '@/styles/reset.css';
import '@/styles/tokens.css';
import '@/styles/globals.css';
import RouteContent from './RouteContent';
import './App.css';
import { markAppPageTransitionReady } from '@/utils/appPageTransition';
import AccountInspectionBanner from '@/app/saas/AccountInspectionBanner';

export default function App({ children, navigation }: { children?: ReactNode | ((sidebar: SidebarState) => ReactNode); navigation?: { entries: SidebarEntry[]; currentKey?: string } }): JSX.Element {
  const { props } = useApiPage<SessionProps>();
  const sidebar = useSidebarState();
  const pathname = usePathname();
  const layout = layouts[pathname.split('/').pop() ?? ''];
  const layoutSidebar = useLayoutSidebar(layout);
  const activeSidebarCollapsed = !sidebar.mobile && (layout ? layoutSidebar.collapsed : sidebar.collapsed);
  const mobileSidebarOpen = sidebar.mobile && sidebar.mobileOpen;
  const closeMobile = sidebar.closeMobile;

  useEffect(() => {
    closeMobile();
  }, [pathname, closeMobile]);

  useEffect(() => {
    if (!mobileSidebarOpen) return;

    function closeOnEscape(event: KeyboardEvent) {
      if (event.key === 'Escape' && !event.defaultPrevented) {
        closeMobile();
        document.getElementById('primary-sidebar-toggle')?.focus();
      }
    }
    document.addEventListener('keydown', closeOnEscape);
    return () => document.removeEventListener('keydown', closeOnEscape);
  }, [mobileSidebarOpen, closeMobile]);

  const currentPageKey = getCurrentPageKey(pathname);

  const page = pages[pathname.split('/').pop() ?? ''];
  const extra = extras[pathname.split('/').pop() ?? ''];
  const standalone = Boolean(extra?.standalone || page?.standalone);
  const routeContent = <RouteContent pathname={pathname} pageKey={currentPageKey} />;

  return <>
    <Suspense fallback={null}>
      <RouteReady pathname={pathname} />
      {standalone ? routeContent : (
        <div key={layout?.key ?? 'primary'} className={`app-page ${activeSidebarCollapsed ? 'app-page--sidebarCollapsed' : ''} ${mobileSidebarOpen ? 'app-page--mobile-sidebar-open' : ''} ${layout ? 'app-page--layout' : ''} ${layout?.sidebar === 'small' ? 'app-page--menuSmall' : ''}`}>
          <AppHeader>
            <AccountInspectionBanner />
            <TopBarPrimary tour={tourFor(pathname,props.auth.permissions)} sidebarCollapsed={activeSidebarCollapsed} sidebarExpanded={sidebar.mobile ? mobileSidebarOpen : !activeSidebarCollapsed}
              onToggleSidebar={sidebar.mobile || !layout ? sidebar.toggle : layoutSidebar.toggle} smallMenu={!sidebar.mobile && layout?.sidebar === 'small'} />
          </AppHeader>
          {mobileSidebarOpen && <button type="button" className="app-page__sidebar-backdrop" aria-label="Fechar menu lateral"
            onClick={() => { closeMobile(); document.getElementById('primary-sidebar-toggle')?.focus(); }} />}
          <SidebarPrimary collapsed={activeSidebarCollapsed} currentItemKey={navigation ? navigation.currentKey : currentPageKey} entries={navigation?.entries}
            smallMenu={!sidebar.mobile && layout?.sidebar === 'small'} user={layout?.sidebar === 'user' ? sidebarUser : undefined}
            onNavigate={sidebar.mobile ? closeMobile : undefined} />

          <main className="app-content-placeholder" aria-label="Conteúdo principal">
            <div className="app-content-placeholder__inner">
              {typeof children === 'function' ? children(sidebar) : children ?? routeContent}
            </div>
            <AppFooter />
          </main>
        </div>
      )}
    </Suspense>
    <PageScrollbar belowTopBar={!standalone} />
  </>;
}

function AppHeader({ children }: { children: ReactNode }) {
  const header = useRef<HTMLDivElement>(null);

  useLayoutEffect(() => {
    const element = header.current;
    if (!element) return;

    const root = document.documentElement;
    const update = () => {
      root.style.setProperty('--bold-header-height', `${element.getBoundingClientRect().height}px`);
    };
    update();
    const observer = new ResizeObserver(update);
    observer.observe(element);

    return () => {
      observer.disconnect();
      root.style.removeProperty('--bold-header-height');
    };
  }, []);

  return <div ref={header} className="app-page__header">{children}</div>;
}

function RouteReady({ pathname }: { pathname: string }): null {
  const { transitionId } = useApiPage();
  useEffect(() => {
    const animationFrame = window.requestAnimationFrame(() => {
      markAppPageTransitionReady(transitionId);
    });

    return () => {
      window.cancelAnimationFrame(animationFrame);
    };
  }, [pathname, transitionId]);

  return null;
}
