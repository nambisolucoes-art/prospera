import type { MouseEvent as ReactMouseEvent } from 'react';

const reducedMotion = '(prefers-reduced-motion: reduce)';
const minimumVisibleTime = 180;
const lastPress = new WeakMap<HTMLElement, number>();
const pendingNavigation = new WeakSet<HTMLAnchorElement>();
let initialized = false;

function showWave(event: PointerEvent) {
  if (event.button !== 0 || window.matchMedia(reducedMotion).matches) return;

  const control = event.target instanceof Element
    ? event.target.closest<HTMLElement>('.waves-effect')
    : null;
  if (!control || control.matches(':disabled, [aria-disabled="true"]')) return;

  const rect = control.getBoundingClientRect();
  const x = event.clientX - rect.left;
  const y = event.clientY - rect.top;
  const radius = Math.hypot(Math.max(x, rect.width - x), Math.max(y, rect.height - y));
  const startedAt = performance.now();
  const ripple = document.createElement('span');
  ripple.className = 'waves-ripple';
  ripple.setAttribute('aria-hidden', 'true');
  ripple.style.left = `${x}px`;
  ripple.style.top = `${y}px`;
  control.append(ripple);
  lastPress.set(control, startedAt);

  ripple.animate(
    { transform: ['scale(0)', `scale(${radius / 10})`] },
    { duration: 500, easing: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)', fill: 'forwards' },
  );

  const release = (releaseEvent: PointerEvent) => {
    if (releaseEvent.pointerId !== event.pointerId) return;
    document.removeEventListener('pointerup', release, true);
    document.removeEventListener('pointercancel', release, true);

    if (releaseEvent.type === 'pointercancel' && lastPress.get(control) === startedAt) {
      lastPress.delete(control);
    }

    const delay = releaseEvent.type === 'pointercancel'
      ? 0
      : Math.max(0, minimumVisibleTime - (performance.now() - startedAt));
    ripple.animate({ opacity: [1, 0] }, { duration: 320, delay, fill: 'forwards' })
      .onfinish = () => ripple.remove();
  };

  document.addEventListener('pointerup', release, true);
  document.addEventListener('pointercancel', release, true);
}

export function initWaves(): void {
  if (initialized || typeof document === 'undefined') return;

  document.addEventListener('pointerdown', showWave, { capture: true, passive: true });
  initialized = true;
}

export function navigateAfterWave(event: ReactMouseEvent, navigate: () => void): void {
  const link = event.currentTarget;
  if (!(link instanceof HTMLAnchorElement) || event.defaultPrevented || event.detail === 0 || event.button !== 0
    || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
  const href = link.getAttribute('href');
  if (!href || href.startsWith('#') || link.origin !== window.location.origin
    || (link.target && link.target !== '_self') || link.hasAttribute('download')) return;

  if (pendingNavigation.has(link)) {
    event.preventDefault();
    return;
  }

  const startedAt = lastPress.get(link);
  if (startedAt === undefined) return;

  const remaining = minimumVisibleTime - (performance.now() - startedAt);
  if (remaining <= 0) return;

  event.preventDefault();
  pendingNavigation.add(link);
  window.setTimeout(() => {
    pendingNavigation.delete(link);
    if (link.isConnected) navigate();
  }, remaining);
}
