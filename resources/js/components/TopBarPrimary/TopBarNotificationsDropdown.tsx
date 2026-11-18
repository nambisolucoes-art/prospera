import { forwardRef, useId } from 'react';
import { joinClassNames } from '@/utils/joinClassNames';

interface TopBarNotificationsDropdownProps {
  isOpen: boolean;
  onToggle: () => void;
  onClose: () => void;
}

const TopBarNotificationsDropdown = forwardRef<HTMLLIElement, TopBarNotificationsDropdownProps>(function TopBarNotificationsDropdown(
  { isOpen, onToggle, onClose },
  ref
): JSX.Element {
  const panelId = useId();
  const titleId = `${panelId}-title`;

  return (
    <li ref={ref} className={joinClassNames('topbar__navItem', isOpen && 'is-open')}>
      <button
        type="button"
        className="topbar__navLink topbar__notificationsButton waves-effect"
        onClick={onToggle}
        onKeyDown={(event) => {
          if (event.key === 'Escape' && isOpen) onClose();
        }}
        aria-label="Notificações"
        aria-expanded={isOpen}
        aria-controls={panelId}
      >
        <i className="icon-bell" aria-hidden="true" />
      </button>

      <section id={panelId} aria-labelledby={titleId} className="topbarDropdown topbarDropdown--notifications">
        <h2 id={titleId} className="topbarDropdown__title">Notificações</h2>
        <p className="topbarDropdown__empty">Nenhuma notificação por enquanto.</p>
      </section>
    </li>
  );
});

export default TopBarNotificationsDropdown;
