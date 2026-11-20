import {
  cloneElement, useId, useLayoutEffect, useRef, useState,
  type ReactElement, type CSSProperties, type HTMLAttributes, type Ref,
} from 'react';
import { createPortal } from 'react-dom';
import './Tooltip.css';

type TriggerProps = HTMLAttributes<HTMLElement> & { ref?: Ref<HTMLElement> };

export default function Tooltip({ text, children, side = 'top', disabled = false, asChild = false, touch = false }: {
  text: string;
  children: ReactElement;
  side?: 'top' | 'right';
  disabled?: boolean;
  asChild?: boolean;
  touch?: boolean;
}): JSX.Element {
  const id = useId();
  const trigger = useRef<HTMLElement>(null);
  const overlay = useRef<HTMLSpanElement>(null);
  const [visible, setVisible] = useState(false);
  const [hovered, setHovered] = useState(false);
  const [focused, setFocused] = useState(false);
  const [pinned, setPinned] = useState(false);
  const [dismissed, setDismissed] = useState(false);
  const [position, setPosition] = useState<CSSProperties>({});
  const [placement, setPlacement] = useState<string>(side);
  const open = !disabled && !dismissed && (hovered || focused || pinned);
  const child = children as ReactElement<TriggerProps>;

  useLayoutEffect(() => {
    if (open) { setVisible(true); return; }
    const timer = window.setTimeout(() => setVisible(false), 120);
    return () => window.clearTimeout(timer);
  }, [open]);

  useLayoutEffect(() => {
    if (!visible || disabled) return;
    function update(): void {
      if (!trigger.current || !overlay.current) return;
      const rect = trigger.current.getBoundingClientRect();
      const width = overlay.current.offsetWidth;
      const height = overlay.current.offsetHeight;
      const gap = 9;
      const margin = 8;
      let nextSide: string = side;
      if (side === 'top' && rect.top < height + gap + margin) nextSide = 'bottom';
      if (side === 'right' && rect.right + gap + width > window.innerWidth - margin) nextSide = 'left';
      const horizontal = nextSide === 'right' || nextSide === 'left';
      const desiredLeft = horizontal
        ? (nextSide === 'right' ? rect.right + gap : rect.left - gap - width)
        : rect.left + rect.width / 2 - width / 2;
      const desiredTop = horizontal ? rect.top + rect.height / 2 - height / 2
        : (nextSide === 'top' ? rect.top - gap - height : rect.bottom + gap);
      const left = Math.max(margin, Math.min(window.innerWidth - width - margin, desiredLeft));
      const top = Math.max(margin, Math.min(window.innerHeight - height - margin, desiredTop));
      setPlacement(nextSide);
      setPosition({ left, top, '--tooltip-arrow': `${horizontal
        ? Math.max(8, Math.min(height - 8, rect.top + rect.height / 2 - top))
        : Math.max(8, Math.min(width - 8, rect.left + rect.width / 2 - left))}px` } as CSSProperties);
    }
    function dismiss(event: KeyboardEvent | PointerEvent): void {
      if (event instanceof KeyboardEvent) {
        if (event.key !== 'Escape') return;
        event.preventDefault();
        event.stopPropagation();
      }
      if (event instanceof PointerEvent && trigger.current?.contains(event.target as Node)) return;
      setDismissed(true);
      setPinned(false);
    }
    update();
    const observer = new ResizeObserver(update);
    if (trigger.current) observer.observe(trigger.current);
    if (overlay.current) observer.observe(overlay.current);
    window.addEventListener('scroll', update, true);
    window.addEventListener('resize', update);
    document.addEventListener('keydown', dismiss, true);
    document.addEventListener('pointerdown', dismiss);
    return () => {
      observer.disconnect();
      window.removeEventListener('scroll', update, true);
      window.removeEventListener('resize', update);
      document.removeEventListener('keydown', dismiss, true);
      document.removeEventListener('pointerdown', dismiss);
    };
  }, [visible, disabled, side, text]);

  useLayoutEffect(() => {
    setHovered(false);
    setFocused(false);
    setPinned(false);
  }, [disabled]);

  const handlers: TriggerProps = {
    onMouseEnter: () => { setHovered(true); setDismissed(false); },
    onMouseLeave: () => setHovered(false),
    onFocus: () => { setFocused(true); setDismissed(false); },
    onBlur: () => { setFocused(false); setPinned(false); },
    onClick: () => { if (touch) { setPinned(!pinned); setDismissed(pinned); } },
  };
  const describedBy = [child.props['aria-describedby'], open ? id : undefined].filter(Boolean).join(' ') || undefined;
  const control = asChild ? cloneElement(child, {
    ref: trigger,
    'aria-describedby': describedBy,
    onMouseEnter: (event) => {
      child.props.onMouseEnter?.(event);
      handlers.onMouseEnter?.(event);
    },
    onMouseLeave: (event) => {
      child.props.onMouseLeave?.(event);
      handlers.onMouseLeave?.(event);
    },
    onFocus: (event) => {
      child.props.onFocus?.(event);
      handlers.onFocus?.(event);
    },
    onBlur: (event) => {
      child.props.onBlur?.(event);
      handlers.onBlur?.(event);
    },
    onClick: (event) => {
      child.props.onClick?.(event);
      handlers.onClick?.(event);
    },
  }) : <span ref={trigger} className="legacyTooltip__trigger" {...handlers}>
    {cloneElement(child, { 'aria-describedby': describedBy })}
  </span>;

  return <>
    {control}
    {visible && !disabled && createPortal(
      <span ref={overlay} id={id} role="tooltip" className="legacyTooltip" data-side={placement} data-open={open} aria-hidden={!open} style={position}>
        <span className="legacyTooltip__inner">{text}</span>
        <span className="legacyTooltip__arrow" />
      </span>, document.body,
    )}
  </>;
}
