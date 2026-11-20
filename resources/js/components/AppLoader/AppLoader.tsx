import { useEffect, useState, type CSSProperties } from 'react';
import { createPortal } from 'react-dom';
import {
  appPageTransitionReadyEvent,
  appPageTransitionStartEvent,
  appLoaderPreviewEnabled,
  type AppPageTransition,
} from '@/utils/appPageTransition';
import './AppLoader.css';

const showDelayMs = 300;
const fadeDurationMs = 180;
const minimumVisibleMs = appLoaderPreviewEnabled ? 500 : 0;
const scrollLockClass = 'app-loader-scroll-lock';
type LoaderPhase = 'hidden' | 'visible' | 'closing';

export default function AppLoader() {
  const [phase, setPhase] = useState<LoaderPhase>('hidden');

  useEffect(() => {
    let activeId: number | undefined;
    let visible = false;
    let shownAt = 0;
    let showTimer: number | undefined;
    let closeTimer: number | undefined;

    function unlockPage() {
      document.documentElement.classList.remove(scrollLockClass);
      document.body.classList.remove(scrollLockClass);
    }

    function start(event: Event) {
      activeId = (event as CustomEvent<AppPageTransition>).detail.id;
      window.clearTimeout(showTimer);
      window.clearTimeout(closeTimer);

      if (visible) {
        if (appLoaderPreviewEnabled) shownAt = performance.now();
        setPhase('visible');
        return;
      }

      const show = () => {
        shownAt = performance.now();
        visible = true;
        document.documentElement.classList.add(scrollLockClass);
        document.body.classList.add(scrollLockClass);
        setPhase('visible');
      };

      if (appLoaderPreviewEnabled) show();
      else showTimer = window.setTimeout(show, showDelayMs);
    }

    function ready(event: Event) {
      if ((event as CustomEvent<AppPageTransition>).detail.id !== activeId) return;
      activeId = undefined;
      window.clearTimeout(showTimer);
      if (!visible) return;

      const close = () => {
        setPhase('closing');
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        closeTimer = window.setTimeout(() => {
          visible = false;
          setPhase('hidden');
          unlockPage();
        }, reducedMotion ? 0 : fadeDurationMs);
      };

      const remaining = Math.max(0, minimumVisibleMs - (performance.now() - shownAt));
      if (remaining > 0) closeTimer = window.setTimeout(close, remaining);
      else close();
    }

    window.addEventListener(appPageTransitionStartEvent, start);
    window.addEventListener(appPageTransitionReadyEvent, ready);

    return () => {
      window.removeEventListener(appPageTransitionStartEvent, start);
      window.removeEventListener(appPageTransitionReadyEvent, ready);
      window.clearTimeout(showTimer);
      window.clearTimeout(closeTimer);
      unlockPage();
    };
  }, []);

  if (phase === 'hidden') return null;

  return createPortal(
    <div
      className={`appLoader${phase === 'closing' ? ' appLoader--hidden' : ''}`}
      role="status"
      aria-live="polite"
      aria-label="Carregando página"
    >
      <div className="appLoader__spinner" aria-hidden="true">
        <img className="appLoader__brand" src="/assets/brand/prospera-symbol.svg" alt="" />
        <div className="appLoader__orbit">
          {Array.from({ length: 8 }, (_, index) => (
            <span
              key={index}
              className="appLoader__dot"
              style={{ '--loader-dot-index': index } as CSSProperties}
            />
          ))}
        </div>
      </div>
    </div>,
    document.body,
  );
}
