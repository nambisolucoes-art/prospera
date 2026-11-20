import { useApiPage as usePage } from '@/hooks/useApiPage';
import type { SessionProps } from '@/types/Session.types';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import './TopBarPrimary.css';
import GuidedTour, { type TourDefinition } from '@/components/GuidedTour/GuidedTour';
import TopBarNotificationsDropdown from './TopBarNotificationsDropdown';
import TopBarProfileDropdown from './TopBarProfileDropdown';
import { useFullscreenToggle } from './useFullscreenToggle';

interface TopBarPrimaryProps {
  sidebarCollapsed: boolean;
  onToggleSidebar: () => void;
  sidebarExpanded: boolean;
  smallMenu?: boolean;
  tour?: TourDefinition;
  // Seletor de contexto montado pela tela, como o de estabelecimento das telas da conta.
  context?: ReactNode;
}

export default function TopBarPrimary({ sidebarCollapsed, sidebarExpanded, onToggleSidebar, smallMenu = false, tour, context }: TopBarPrimaryProps): JSX.Element {
  const [tourOpen,setTourOpen]=useState(false);
  useEffect(()=>setTourOpen(false),[tour?.id]);
  const { name } = usePage<SessionProps>().props;
  const [notificationsOpen, setNotificationsOpen] = useState<boolean>(false);
  const [profileOpen, setProfileOpen] = useState<boolean>(false);
  const { isFullscreen, toggleFullscreen } = useFullscreenToggle();
  const notificationsRef = useRef<HTMLLIElement | null>(null);
  const profileRef = useRef<HTMLLIElement | null>(null);

  useEffect(() => {
    function handleOutsidePress(event: PointerEvent): void {
      const target = event.target as Node;

      if (notificationsRef.current && !notificationsRef.current.contains(target)) {
        setNotificationsOpen(false);
      }

      if (profileRef.current && !profileRef.current.contains(target)) {
        setProfileOpen(false);
      }
    }

    document.addEventListener('pointerdown', handleOutsidePress);

    return () => {
      document.removeEventListener('pointerdown', handleOutsidePress);
    };
  }, []);

  function toggleNotifications(): void {
    setNotificationsOpen((open: boolean) => !open);
    setProfileOpen(false);
  }

  function toggleProfile(event: React.MouseEvent<HTMLAnchorElement>): void {
    event.preventDefault();
    setProfileOpen((open: boolean) => !open);
    setNotificationsOpen(false);
  }

  function preventNavigation(event: React.MouseEvent<HTMLAnchorElement>): void {
    event.preventDefault();
  }

  return (
    <>
    <header className={`topbar ${sidebarCollapsed ? 'topbar--collapsed' : ''} ${smallMenu ? 'topbar--small' : ''}`}>
      <div className="topbar__left">
        <div className="topbar__leftInner">
          <a href="/" className="topbar__logo" aria-label={`Página inicial do ${name}`}>
            <img className="topbar__logoIcon" src="/assets/brand/prospera-symbol.svg" alt="" width="32" height="32" />
            <img className="topbar__wordmark" src="/assets/brand/prospera-topbar.svg" alt="" width="137" height="52" />
          </a>
        </div>
      </div>

      <div className="topbar__navBar" role="navigation" aria-label="Topbar">
        <div className="topbar__container">
          <div className="topbar__leftActions pull-left">
            <button
              type="button"
              id="primary-sidebar-toggle"
              className="topbar__menuButton"
              aria-label="Menu lateral"
              aria-controls="primary-sidebar"
              aria-expanded={sidebarExpanded}
              onClick={onToggleSidebar}
            >
              <i className="ion-navicon" aria-hidden="true" />
            </button>
            <span className="clearfix" />
          </div>

          <form className="topbar__search navbar-left pull-left hidden-xs" role="search">
            <input type="text" placeholder="Pesquisar..." aria-label="Pesquisar" />
            <a href="/" onClick={preventNavigation} aria-hidden="true">
              <i className="fa fa-search" />
            </a>
          </form>

          <ul className="topbar__navList nav navbar-nav navbar-right pull-right">
            {context && <li className="topbar__navItem topbar__context">{context}</li>}
            {tour && <li className="topbar__navItem"><button type="button" className="topbar__navLink waves-effect topbar__tour" aria-label="Tutorial desta tela" title="Tutorial desta tela" onClick={()=>{setNotificationsOpen(false);setProfileOpen(false);setTourOpen(true);}}><i className="icon-info" aria-hidden="true"/></button></li>}
            <TopBarNotificationsDropdown
              ref={notificationsRef}
              isOpen={notificationsOpen}
              onToggle={toggleNotifications}
              onClose={() => setNotificationsOpen(false)}
            />

            <li className="topbar__navItem hidden-xs">
              <a
                href="/"
                id="btn-fullscreen"
                className={`topbar__navLink waves-effect ${isFullscreen ? 'is-active' : ''}`}
                onClick={(event) => { event.preventDefault(); void toggleFullscreen(); }}
                aria-label="Alternar tela cheia"
              >
                <i className="icon-size-fullscreen" aria-hidden="true" />
              </a>
            </li>

            <TopBarProfileDropdown
              ref={profileRef}
              isOpen={profileOpen}
              onToggle={toggleProfile}
            />
          </ul>
        </div>
      </div>
    </header>
    {tourOpen && tour && <GuidedTour definition={tour} onClose={()=>setTourOpen(false)}/>}
    </>
  );
}
