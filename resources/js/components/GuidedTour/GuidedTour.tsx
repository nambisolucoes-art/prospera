import { useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import Button from '@/components/Button/Button';
import type { TourDefinition, TourStep } from './GuidedTour.types';
export type { TourDefinition, TourStep } from './GuidedTour.types';
import './GuidedTour.css';
function visible(node: HTMLElement) {
  const rect = node.getBoundingClientRect();
  const style = getComputedStyle(node);
  return (
    rect.width > 0 &&
    rect.height > 0 &&
    rect.right > 0 &&
    rect.left < innerWidth &&
    style.visibility !== 'hidden' &&
    style.display !== 'none'
  );
}
function nodesFor(target: string | string[]) {
  return (Array.isArray(target) ? target : [target])
    .map((selector) => document.querySelector<HTMLElement>(selector))
    .filter((node): node is HTMLElement => !!node && visible(node));
}
function available(step: TourStep) {
  return nodesFor(step.availableWith ?? step.target).length > 0;
}
function bounds(nodes: HTMLElement[]) {
  const rects = nodes.map((node) => node.getBoundingClientRect());
  return DOMRect.fromRect({
    x: Math.min(...rects.map((r) => r.left)),
    y: Math.min(...rects.map((r) => r.top)),
    width:
      Math.max(...rects.map((r) => r.right)) -
      Math.min(...rects.map((r) => r.left)),
    height:
      Math.max(...rects.map((r) => r.bottom)) -
      Math.min(...rects.map((r) => r.top)),
  });
}
export default function GuidedTour({
  definition,
  onClose,
}: {
  definition: TourDefinition;
  onClose: () => void;
}) {
  const [steps] = useState(() => definition.steps.filter(available));
  const [index, setIndex] = useState(0);
  const [box, setBox] = useState<DOMRect>();
  const [position, setPosition] = useState({ top: 80, left: 12 });
  const [note, setNote] = useState('');
  const [trail, setTrail] = useState(false);
  const card = useRef<HTMLDivElement>(null);
  const title = useRef<HTMLHeadingElement>(null);
  const prior = useRef(document.activeElement as HTMLElement | null);
  const step = steps[index];
  useLayoutEffect(() => {
    const previous = prior.current;
    card.current?.focus({ preventScroll: true });
    document.documentElement.dataset.guidedTour = 'active';
    return () => {
      delete document.documentElement.dataset.guidedTour;
      previous?.focus({ preventScroll: true });
    };
  }, []);
  useLayoutEffect(() => {
    setNote('');
    setBox(undefined);
    if (!step) return;
    const demo = step.onEnter?.(
      nodesFor(step.availableWith ?? step.target)[0] ?? null,
    );
    if (demo?.note) setNote(demo.note);
    let scrolled: HTMLElement | undefined;
    let frame = 0;
    const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const place = () => {
      const nodes = nodesFor(step.target);
      if (!nodes.length) return;
      const first = nodes[0];
      if (scrolled !== first) {
        scrolled = first;
        first.scrollIntoView({
          block: 'center',
          behavior: reduced ? 'instant' : 'smooth',
        });
      }
      const rect = bounds(nodes);
      nodes.forEach((node) => observer.observe(node));
      setBox((previous) =>
        previous &&
        Math.abs(previous.x - rect.x) < 0.5 &&
        Math.abs(previous.y - rect.y) < 0.5 &&
        Math.abs(previous.width - rect.width) < 0.5 &&
        Math.abs(previous.height - rect.height) < 0.5
          ? previous
          : rect,
      );
      const height = card.current?.offsetHeight ?? 300;
      const width = Math.min(380, innerWidth - 24);
      const clampX = (x: number) =>
        Math.max(12, Math.min(x, innerWidth - width - 12));
      const clampY = (y: number) =>
        Math.max(72, Math.min(y, innerHeight - height - 12));
      const candidates = [
        { top: rect.bottom + 14, left: clampX(rect.left) },
        { top: rect.top - height - 14, left: clampX(rect.left) },
        { top: clampY(rect.top), left: rect.right + 14 },
        { top: clampY(rect.top), left: rect.left - width - 14 },
      ];
      const fits = (p: { top: number; left: number }) =>
        p.top >= 72 &&
        p.left >= 12 &&
        p.top + height <= innerHeight - 12 &&
        p.left + width <= innerWidth - 12;
      const overlap = (p: { top: number; left: number }) =>
        Math.max(
          0,
          Math.min(p.left + width, rect.right) - Math.max(p.left, rect.left),
        ) *
        Math.max(
          0,
          Math.min(p.top + height, rect.bottom) - Math.max(p.top, rect.top),
        );
      const best =
        candidates.find(fits) ??
        candidates
          .map((p) => ({ top: clampY(p.top), left: clampX(p.left) }))
          .sort((a, b) => overlap(a) - overlap(b))[0];
      setPosition((previous) =>
        Math.abs(previous.top - best.top) < 0.5 &&
        Math.abs(previous.left - best.left) < 0.5
          ? previous
          : best,
      );
    };
    let followUntil = 0;
    const tick = () => {
      place();
      if (performance.now() < followUntil) frame = requestAnimationFrame(tick);
    };
    const schedule = () => {
      cancelAnimationFrame(frame);
      followUntil = reduced ? 0 : performance.now() + 400;
      frame = requestAnimationFrame(tick);
    };
    // Follow targets revealed asynchronously and their modal opening animations.
    const mutations = new MutationObserver((records) => {
      if (
        records.some(
          (record) =>
            !(
              record.target instanceof Element &&
              record.target.closest('.guided-tour')
            ),
        )
      )
        schedule();
    });
    mutations.observe(document.body, {
      childList: true,
      attributes: true,
      attributeFilter: [
        'class',
        'style',
        'hidden',
        'aria-selected',
        'aria-expanded',
      ],
      subtree: true,
    });
    const observer = new ResizeObserver(schedule);
    if (card.current) observer.observe(card.current);
    nodesFor(step.target).forEach((node) => observer.observe(node));
    place();
    title.current?.focus({ preventScroll: true });
    window.addEventListener('prospera:tour-layout', schedule);
    window.addEventListener('scroll', schedule, true);
    window.addEventListener('resize', schedule);
    return () => {
      mutations.disconnect();
      observer.disconnect();
      cancelAnimationFrame(frame);
      window.removeEventListener('prospera:tour-layout', schedule);
      window.removeEventListener('scroll', schedule, true);
      window.removeEventListener('resize', schedule);
      demo?.undo?.();
    };
  }, [step]);
  function go(next: number) {
    setIndex(next);
  }
  function key(event: React.KeyboardEvent) {
    if (event.key === 'Escape') {
      event.preventDefault();
      event.stopPropagation();
      onClose();
    }
    if (event.key === 'Tab') {
      const items = Array.from(
        card.current?.querySelectorAll<HTMLElement>(
          'button:not(:disabled),a[href]',
        ) ?? [],
      ).filter((node) => node.getClientRects().length);
      const first = items[0],
        last = items[items.length - 1];
      if (!first) return;
      const current = document.activeElement;
      if (
        event.shiftKey &&
        (current === first ||
          current === title.current ||
          current === card.current)
      ) {
        event.preventDefault();
        last.focus();
      } else if (
        !event.shiftKey &&
        (current === last ||
          current === title.current ||
          current === card.current)
      ) {
        event.preventDefault();
        first.focus();
      }
    }
  }
  return createPortal(
    <div className="guided-tour" onKeyDown={key}>
      {!box && <div className="guided-tour__backdrop" />}
      {box && (
        <div
          className="guided-tour__highlight"
          style={{
            left: Math.max(4, box.left - 4),
            top: Math.max(4, box.top - 4),
            width: Math.min(
              innerWidth - Math.max(4, box.left - 4) - 4,
              box.width + 8,
            ),
            height: Math.min(innerHeight - 8, box.height + 8),
          }}
        />
      )}
      <div
        className="guided-tour__card"
        ref={card}
        role="dialog"
        aria-modal="true"
        aria-labelledby="guided-tour-title"
        aria-describedby="guided-tour-text"
        tabIndex={-1}
        style={position}
      >
        <header>
          <small>{definition.title}</small>
          <button
            className="guided-tour__close"
            aria-label="Fechar tutorial"
            onClick={onClose}
          >
            ×
          </button>
        </header>
        <div className="guided-tour__counter">
          Passo {index + 1} de {steps.length || 1}
          {step?.group && <span>{step.group}</span>}
        </div>
        <div
          className="guided-tour__progress"
          role="progressbar"
          aria-label="Progresso do tutorial"
          aria-valuenow={index + 1}
          aria-valuemin={1}
          aria-valuemax={steps.length || 1}
        >
          <span
            style={{ width: `${(100 * (index + 1)) / (steps.length || 1)}%` }}
          />
        </div>
        <h2 id="guided-tour-title" ref={title} tabIndex={-1}>
          {step?.title ?? 'Orientação da tela'}
        </h2>
        <div id="guided-tour-text" className="guided-tour__text">
          {(Array.isArray(step?.text)
            ? step.text
            : [step?.text ?? 'Consulte a ajuda para conhecer este fluxo.']
          ).map((paragraph, i) => (
            <p key={i}>{paragraph}</p>
          ))}
        </div>
        {note && (
          <p className="guided-tour__note">
            <i className="fa fa-lightbulb-o" aria-hidden="true" />
            {note}
          </p>
        )}
        <button
          className="guided-tour__trail-toggle"
          aria-expanded={trail}
          onClick={() => setTrail(!trail)}
        >
          {trail ? 'Esconder os passos' : 'Ver todos os passos'}{' '}
          <i
            className={trail ? 'fa fa-angle-up' : 'fa fa-angle-down'}
            aria-hidden="true"
          />
        </button>
        {trail && (
          <ol className="guided-tour__trail">
            {steps.map((item, i) => (
              <li key={item.id ?? i}>
                {(i === 0 || item.group !== steps[i - 1].group) &&
                  item.group && <small>{item.group}</small>}
                <button
                  aria-current={i === index ? 'step' : undefined}
                  onClick={() => {
                    go(i);
                    setTrail(false);
                  }}
                >
                  <span>{i + 1}</span>
                  {item.title}
                </button>
              </li>
            ))}
          </ol>
        )}
        {index >= steps.length - 1 && definition.help && (
          <a href={definition.help}>Ler ajuda completa</a>
        )}
        <footer className="guided-tour__actions">
          <Button tone="white" disabled={index === 0} onClick={() => go(index - 1)}>
            Anterior
          </Button>
          <Button tone="success"
            onClick={() =>
              index >= steps.length - 1 ? onClose() : go(index + 1)
            }
          >
            {index >= steps.length - 1 ? 'Concluir' : 'Próximo'}
          </Button>
        </footer>
      </div>
    </div>,
    document.body,
  );
}
