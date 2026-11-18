import { forwardRef, useLayoutEffect, useRef, type ButtonHTMLAttributes, type HTMLAttributes } from 'react';
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

export function SelectMenu({ children, ...props }: HTMLAttributes<HTMLDivElement>) {
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
  }, []);

  return <div {...props} ref={menuRef} className="selectControl__menu">{children}</div>;
}
