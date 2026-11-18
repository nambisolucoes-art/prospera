import { useApiForm } from '@/hooks/useApiForm';
import { useApiPage as usePage } from '@/hooks/useApiPage';
import { forwardRef } from 'react';
import type { SessionProps } from '@/types/Session.types';
import { joinClassNames } from '@/utils/joinClassNames';
import ProfileAvatar from '@/components/ProfileAvatar/ProfileAvatar';

interface TopBarProfileDropdownProps {
  isOpen: boolean;
  onToggle: (event: React.MouseEvent<HTMLAnchorElement>) => void;
}

const TopBarProfileDropdown = forwardRef<HTMLLIElement, TopBarProfileDropdownProps>(function TopBarProfileDropdown(
  { isOpen, onToggle },
  ref
): JSX.Element {
  const logout = useApiForm({});
  const { auth } = usePage<SessionProps>().props;
  const links = auth.user
    ? [
      { href: '/settings/profile', icon: 'ti-user', label: 'Perfil' },
    ]
    : [
      { href: '/login', icon: 'ti-user', label: 'Entrar' },
    ];

  return (
    <li ref={ref} className={joinClassNames('topbar__navItem', 'topbar__navItem--dropdown', 'dropdown', isOpen && 'is-open')}>
      <a
        href={auth.user ? '/settings/profile' : '/login'}
        className="topbar__navLink topbar__profile dropdown-toggle profile waves-effect"
        onClick={onToggle}
        aria-label={auth.user ? `Menu de ${auth.user.name}` : 'Menu da conta'}
        aria-expanded={isOpen}
      >
        <ProfileAvatar name={auth.user?.name ?? ''} photoUrl={auth.user?.photo_url} small />
      </a>

      <ul className="topbarDropdown topbarDropdown--profile dropdown-menu">
        {links.map(({ href, icon, label }) => (
          <li key={href}>
            <a href={href} className="topbarDropdown__profileLink">
              <i className={`${icon} topbarDropdown__profileIcon m-r-5`} aria-hidden="true" />
              {label}
            </a>
          </li>
        ))}
        {auth.user && (
          <li>
            <button type="button" onClick={() => logout.post('/logout')} disabled={logout.processing} className="topbarDropdown__profileLink">
              <i className="ti-power-off topbarDropdown__profileIcon m-r-5" aria-hidden="true" />
              Sair
            </button>
          </li>
        )}
      </ul>
    </li>
  );
});

export default TopBarProfileDropdown;
