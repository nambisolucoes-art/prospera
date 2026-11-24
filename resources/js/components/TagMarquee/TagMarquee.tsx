import { useEffect, useRef, useState, type CSSProperties, type ReactNode } from 'react';
import './TagMarquee.css';

/** Velocidade do letreiro, em pixels por segundo. */
const SPEED = 32;

/**
 * Lista de etiquetas numa única linha. Quando não cabe na largura disponível, as etiquetas passam em ciclo, como um
 * letreiro, e a medição se repete a cada mudança de largura (janela ou coluna redimensionada).
 */
export default function TagMarquee({ children }: { children: ReactNode }) {
  const viewport = useRef<HTMLDivElement>(null);
  const group = useRef<HTMLDivElement>(null);
  const [shift, setShift] = useState(0);

  useEffect(() => {
    const frame = viewport.current;
    const content = group.current;
    if (!frame || !content) return;

    const measure = () => {
      // O grupo tem folga no fim, então a largura dele já é o passo exato do ciclo.
      const overflowing = content.scrollWidth > frame.clientWidth + 1;
      setShift(overflowing ? content.scrollWidth : 0);
    };
    measure();

    const observer = new ResizeObserver(measure);
    observer.observe(frame);
    observer.observe(content);
    return () => observer.disconnect();
  }, []);

  const style = shift
    ? ({ '--tag-marquee-shift': `${shift}px`, '--tag-marquee-duration': `${Math.max(shift / SPEED, 6)}s` } as CSSProperties)
    : undefined;

  return (
    <div ref={viewport} className={`tag-marquee${shift ? ' tag-marquee--running' : ''}`}>
      <div className="tag-marquee__track" style={style}>
        <div ref={group} className="tag-marquee__group">{children}</div>
        {shift > 0 && <div className="tag-marquee__group" aria-hidden="true">{children}</div>}
      </div>
    </div>
  );
}
