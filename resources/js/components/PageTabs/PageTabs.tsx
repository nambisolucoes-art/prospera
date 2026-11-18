import Link from '@/components/ApiLink/ApiLink';
import { useLayoutEffect, useRef, useState } from 'react';
import './PageTabs.css';

type PageTab = { href: string; label: string };

export default function PageTabs({ items, activeHref, label, tourTarget }: {
  items: readonly PageTab[];
  activeHref: string;
  label: string;
  tourTarget?: string;
}) {
  const listRef = useRef<HTMLUListElement>(null);
  const [indicator, setIndicator] = useState<{ left: number; right: number }>();

  useLayoutEffect(() => {
    const list = listRef.current;
    const active = list?.querySelector<HTMLAnchorElement>('a[aria-current="page"]');
    if (!list || !active) return;

    const measure = () => {
      const left = active.offsetLeft;
      setIndicator({ left, right: list.clientWidth - left - active.offsetWidth });
    };

    measure();
    const observer = new ResizeObserver(measure);
    observer.observe(list);
    list.querySelectorAll('a').forEach((link) => observer.observe(link));
    return () => observer.disconnect();
  }, [activeHref]);

  return (
    <nav className="page-tabs" aria-label={label} data-tour={tourTarget}>
      <ul className="page-tabs__list" ref={listRef}>
        {items.map(({ href, label: tabLabel }) => (
          <li key={href}>
            <Link href={href} className="page-tabs__link" aria-current={href === activeHref ? 'page' : undefined}>
              {tabLabel}
            </Link>
          </li>
        ))}
        {indicator && <li className="page-tabs__indicator" style={indicator} aria-hidden="true" />}
      </ul>
    </nav>
  );
}
