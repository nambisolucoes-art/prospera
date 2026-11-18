import { useCallback, useEffect, useState } from 'react';

export interface SidebarState {
  collapsed: boolean;
  mobile: boolean;
  mobileOpen: boolean;
  closeMobile: () => void;
  toggle: () => void;
}

const SIDEBAR_COLLAPSED_STORAGE_KEY = 'bold.sidebarCollapsed';
const MOBILE_VIEWPORT = '(max-width: 767px)';

function getInitialSidebarCollapsed(): boolean {
  const storedValue = window.localStorage.getItem(SIDEBAR_COLLAPSED_STORAGE_KEY);

  if (storedValue === 'true') {
    return true;
  }

  if (storedValue === 'false') {
    return false;
  }

  return window.matchMedia('(max-width: 990px)').matches;
}

export function useSidebarState(): SidebarState {
  const [sidebarCollapsed, setSidebarCollapsed] = useState<boolean>(getInitialSidebarCollapsed);
  const [mobile, setMobile] = useState(() => window.matchMedia(MOBILE_VIEWPORT).matches);
  const [mobileOpen, setMobileOpen] = useState(false);
  const closeMobile = useCallback(() => setMobileOpen(false), []);

  useEffect(() => {
    const mediaQuery = window.matchMedia('(max-width: 990px)');
    const mobileQuery = window.matchMedia(MOBILE_VIEWPORT);

    function handleViewportChange(event: MediaQueryListEvent): void {
      if (window.localStorage.getItem(SIDEBAR_COLLAPSED_STORAGE_KEY) === null) {
        setSidebarCollapsed(event.matches);
      }
    }

    function handleMobileChange(event: MediaQueryListEvent) {
      setMobile(event.matches);
      closeMobile();
    }

    mediaQuery.addEventListener('change', handleViewportChange);
    mobileQuery.addEventListener('change', handleMobileChange);

    return () => {
      mediaQuery.removeEventListener('change', handleViewportChange);
      mobileQuery.removeEventListener('change', handleMobileChange);
    };
  }, [closeMobile]);

  function toggleSidebar(): void {
    if (mobile) {
      setMobileOpen((open) => !open);
      return;
    }

    setSidebarCollapsed((currentValue: boolean) => {
      const nextValue = !currentValue;
      window.localStorage.setItem(SIDEBAR_COLLAPSED_STORAGE_KEY, String(nextValue));
      return nextValue;
    });
  }

  return { collapsed: sidebarCollapsed, mobile, mobileOpen, closeMobile, toggle: toggleSidebar };
}
