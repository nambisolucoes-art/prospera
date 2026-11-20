import { forwardRef, useLayoutEffect, useRef, useState, type ButtonHTMLAttributes, type HTMLAttributes } from 'react';
import '@/components/TextInput/TextInput.css';

export const SelectTrigger = forwardRef<HTMLButtonElement, ButtonHTMLAttributes<HTMLButtonElement>>(
  function SelectTrigger({ children, ...props }, ref) {
    return (
      <button {...props} ref={ref} type="button" role="combobox" className="textInput selectControl__trigger">
        <span>{children}</span>
        <i className={props['aria-expanded'] ? 'fa fa-caret-up' : 'fa fa-caret-down'} aria-hidden="true" />
      </button>
    );
  },
);

export function SelectMenu({ open, children, className = '', ...props }: HTMLAttributes<HTMLDivElement> & { open: boolean }) {
  const [present, setPresent] = useState(open);
  const rendered = open || present;

  useLayoutEffect(() => {
    if (open) {
      setPresent(true);
      return;
    }
    if (!present) return;
    const duration = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 250;
    const timer = window.setTimeout(() => setPresent(false), duration);
    return () => window.clearTimeout(timer);
  }, [open, present]);

  const menuRef = useRef<HTMLDivElement>(null);

  useLayoutEffect(() => {
    const menu = menuRef.current;
    const anchor = menu?.parentElement;
    if (!menu || !anchor) return;

    const updatePosition = () => {
      const gutter = 8;
      const { left: anchorLeft, right: anchorRight } = anchor.getBoundingClientRect();
      const menuWidth = menu.getBoundingClientRect().width;
      const rightLimit = document.documentElement.clientWidth - gutter;
      const preferredLeft = anchorLeft + menuWidth <= rightLimit ? anchorLeft : anchorRight - menuWidth;
      const left = Math.max(gutter, Math.min(preferredLeft, rightLimit - menuWidth));
      menu.style.setProperty('--select-menu-offset', `${left - anchorLeft}px`);
    };

    const resizeObserver = new ResizeObserver(updatePosition);
    resizeObserver.observe(anchor);
    resizeObserver.observe(menu);
    window.addEventListener('resize', updatePosition);
    updatePosition();

    return () => {
      resizeObserver.disconnect();
      window.removeEventListener('resize', updatePosition);
    };
  }, [rendered]);

  if (!rendered) return null;

  return <div {...props} ref={menuRef} inert={!open} aria-hidden={!open || undefined}
    className={`selectControl__menu ${!open ? 'selectControl__menu--closing' : ''} ${className}`}>{children}</div>;
}
