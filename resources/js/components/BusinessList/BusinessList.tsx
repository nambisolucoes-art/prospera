import Link from '@/components/ApiLink/ApiLink';
import { useApiPage as usePage } from '@/hooks/useApiPage';
import { useEffect, useId, useLayoutEffect, useRef, useState, type PointerEvent, type ReactNode } from 'react';
import CardBox from '@/components/CardBox/CardBox';
import SelectChecklist from '@/components/SelectControl/SelectChecklist';
import SelectControl from '@/components/SelectControl/SelectControl';
import Table from '@/components/Table/Table';
import type { SessionProps } from '@/types/Session.types';
import './BusinessList.css';

export type PageSummary = {
  total: number; from: number | null; to: number | null;
  current_page: number; last_page: number; per_page: number;
  first_page_url: string; last_page_url: string;
  prev_page_url: string | null; next_page_url: string | null;
};

export type BusinessColumn<Row> = {
  id: string;
  label: string;
  width: number;
  locked?: boolean;
  compact?: boolean;
  compactWidth?: number;
  render: (row: Row) => ReactNode;
};

type Preferences = { hidden: string[]; compactHidden?: string[]; columnWeights: Record<string, number> };
type ColumnPair = {
  leftId: string;
  rightId: string;
  leftWidth: number;
  rightWidth: number;
  weight: number;
};
type ResizeState = { pointerId: number; x: number; pair: ColumnPair };
const defaultPreferences: Preferences = { hidden: [], columnWeights: {} };
const pageSizeOptions = ['10', '25', '50', '100'].map((size) => ({ value: size, label: size }));
const minimumColumnWidth = 48;
const compactTableWidth = 560;

function readPreferences(key: string): Preferences {
  try {
    const stored = localStorage.getItem(key);
    if (stored) {
      const { hidden, compactHidden, columnWeights } = JSON.parse(stored) as Partial<Preferences>;
      return {
        hidden: Array.isArray(hidden) ? hidden : [],
        compactHidden: Array.isArray(compactHidden) ? compactHidden : undefined,
        columnWeights: columnWeights ?? {},
      };
    }
  } catch {
    // A list can operate when browser storage is unavailable.
  }
  return defaultPreferences;
}

export default function BusinessList<Row>({ label, preferenceKey, filters, page, rows, columns, rowKey, empty }: {
  label: string; preferenceKey: string; filters?: ReactNode; page: PageSummary;
  rows: Row[]; columns: BusinessColumn<Row>[]; rowKey: (row: Row) => number; empty: string;
}) {
  const { props, url, navigate } = usePage<SessionProps>();
  const pageSizeId = useId();
  const key = `prospera.table.${props.auth.user?.id ?? 'local'}.${preferenceKey}`;
  const [preferences, setPreferences] = useState(() => readPreferences(key));
  const [compact, setCompact] = useState(false);
  const [demoWeights, setDemoWeights] = useState<Record<string,number>>();
  const tableViewport = useRef<HTMLDivElement>(null);
  const drag = useRef<ResizeState | null>(null);
  const defaultCompactHidden = columns.some((column) => column.compact)
    ? columns.filter((column) => !column.compact && !column.locked).map((column) => column.id)
    : [];
  const hidden = compact ? preferences.compactHidden ?? defaultCompactHidden : preferences.hidden;
  const orderedColumns = [
    ...columns.filter((column) => column.id !== 'actions'),
    ...columns.filter((column) => column.id === 'actions'),
  ];
  const visible = orderedColumns.filter((column) => column.locked || !hidden.includes(column.id));
  const hasFixedWidth = (column: BusinessColumn<Row>) =>
    column.locked || column === visible[0] || column === visible[visible.length - 1];
  const fixedWidthOf = (column: BusinessColumn<Row>) =>
    compact ? column.compactWidth ?? Math.min(78, column.width) : column.width;
  const fixedWidth = visible.reduce(
    (total, column) => total + (hasFixedWidth(column) ? fixedWidthOf(column) : 0),
    0,
  );
  const weightOf = (column: BusinessColumn<Row>) => {
    const saved = demoWeights?.[column.id] ?? preferences.columnWeights[column.id];
    return !compact && Number.isFinite(saved) && saved > 0 ? saved : column.width;
  };
  const availableWeight = visible.reduce(
    (total, column) => total + (hasFixedWidth(column) ? 0 : weightOf(column)),
    0,
  );

  useLayoutEffect(() => {
    const viewport = tableViewport.current;
    if (!viewport) return;

    const update = () => setCompact(viewport.clientWidth <= compactTableWidth);
    update();
    const observer = new ResizeObserver(update);
    observer.observe(viewport);
    return () => observer.disconnect();
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      try {
        localStorage.setItem(key, JSON.stringify(preferences));
      } catch {
        // Preferences remain available for the current view.
      }
    }, 150);
    return () => window.clearTimeout(timer);
  }, [key, preferences]);

  function measurePair(handle: HTMLSpanElement, left: BusinessColumn<Row>, right: BusinessColumn<Row>): ColumnPair | undefined {
    const header = handle.closest('th');
    const neighbor = header?.nextElementSibling;
    if (!header || !neighbor) return;

    return {
      leftId: left.id,
      rightId: right.id,
      leftWidth: header.getBoundingClientRect().width,
      rightWidth: neighbor.getBoundingClientRect().width,
      weight: weightOf(left) + weightOf(right),
    };
  }

  function resize(pair: ColumnPair, delta: number) {
    const totalWidth = pair.leftWidth + pair.rightWidth;
    if (!totalWidth) return;
    // Transfer width across this separator while preserving the total width of the pair.
    const lower = Math.min(minimumColumnWidth, pair.leftWidth);
    const upper = totalWidth - Math.min(minimumColumnWidth, pair.rightWidth);
    const leftWidth = Math.min(upper, Math.max(lower, pair.leftWidth + delta));
    const leftWeight = pair.weight * leftWidth / totalWidth;
    setPreferences((current) => ({
      ...current,
      columnWeights: {
        ...current.columnWeights,
        [pair.leftId]: leftWeight,
        [pair.rightId]: pair.weight - leftWeight,
      },
    }));
  }

  function startResize(event: PointerEvent<HTMLSpanElement>, left: BusinessColumn<Row>, right: BusinessColumn<Row>) {
    if (event.button !== 0) return;
    const pair = measurePair(event.currentTarget, left, right);
    if (!pair) return;
    event.currentTarget.setPointerCapture(event.pointerId);
    drag.current = { pointerId: event.pointerId, x: event.clientX, pair };
    event.preventDefault();
  }

  useEffect(() => {
    const root = tableViewport.current?.closest('.business-list');
    if (!root) return;
    let timer: number | undefined;
    const stop = () => { window.clearInterval(timer); setDemoWeights(undefined); };
    const demonstrate = (event: Event) => {
      stop();
      if (!(event as CustomEvent<{active:boolean}>).detail.active || compact) return;
      const handle = event.target as HTMLSpanElement;
      const left = columns.find(column => column.id === handle.closest('th')?.dataset.column);
      const right = columns.find(column => column.id === (handle.closest('th')?.nextElementSibling as HTMLElement | null)?.dataset.column);
      if (!left || !right) return;
      const pair = measurePair(handle, left, right);
      if (!pair) return;
      let tick = 0;
      const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
      const advance = () => {
        tick += 1;
        const total = pair.leftWidth + pair.rightWidth;
        const delta = Math.min(64, pair.rightWidth * .3) * tick / 8;
        const weight = pair.weight * (pair.leftWidth + delta) / total;
        setDemoWeights({[left.id]:weight,[right.id]:pair.weight-weight});
        window.dispatchEvent(new Event('prospera:tour-layout'));
        if (tick >= 8) window.clearInterval(timer);
      };
      if (reduced) { tick = 7; advance(); }
      else timer = window.setInterval(advance, 70);
    };
    root.addEventListener('business-list:resize-demo', demonstrate);
    return () => { root.removeEventListener('business-list:resize-demo', demonstrate); window.clearInterval(timer); };
  }, [columns, compact, preferences]);

  function changePageSize(size: string) {
    const next = new URL(url, window.location.origin);
    next.searchParams.delete('page');
    next.searchParams.set('per_page', size);
    void navigate(next.pathname + next.search, { preserveScroll: true });
  }

  function setColumnVisibility(id: string, checked: boolean) {
    const next = checked ? hidden.filter((hiddenId) => hiddenId !== id) : [...hidden, id];
    setPreferences((current) => compact ? { ...current, compactHidden: next } : { ...current, hidden: next });
  }

  return (
    <CardBox className="business-list" data-tour="business-list">
      {filters && <div className="business-list__filters" data-tour="business-filters">{filters}</div>}
      <div className="business-list__summary">
        <span>{page.total} {page.total === 1 ? 'registro' : 'registros'}</span>
        <div className="business-list__columns" data-tour="business-columns">
          <SelectChecklist label="Colunas" options={orderedColumns.map((column) => ({
            id: column.id,
            label: column.label,
            checked: column.locked || !hidden.includes(column.id),
            disabled: column.locked || (visible.length === 1 && visible[0].id === column.id),
          }))}
            onChange={setColumnVisibility} />
        </div>
      </div>
      <div ref={tableViewport} className="business-list__table" data-tour="business-table" role="region" aria-label={label}>
        <Table hover condensed headerTone="primary">
          <colgroup>{visible.map((column) => {
            const share = availableWeight > 0 ? weightOf(column) / availableWeight : 0;
            const width = hasFixedWidth(column)
              ? fixedWidthOf(column)
              : `calc(${share * 100}cqw - ${share * fixedWidth}px)`;
            return <col key={column.id} style={{ width }} />;
          })}</colgroup>
          <thead>
            <tr>{visible.map((column, index) => {
              const neighbor = visible[index + 1];
              return (
                <th key={column.id} scope="col" data-column={column.id}>
                  <span className="business-list__heading" title={column.label}>{column.label}</span>
                  {!compact && !hasFixedWidth(column) && neighbor && !hasFixedWidth(neighbor) && (
                    <span className="business-list__resize" data-tour="business-resize" role="separator" tabIndex={0} aria-orientation="vertical"
                      aria-label={`Redimensionar colunas ${column.label} e ${neighbor.label}`}
                      aria-valuenow={Math.round(100 * weightOf(column) / (weightOf(column) + weightOf(neighbor)))}
                      onPointerDown={(event) => startResize(event, column, neighbor)}
                      onPointerMove={(event) => {
                        const current = drag.current;
                        if (current?.pointerId === event.pointerId) resize(current.pair, event.clientX - current.x);
                      }}
                      onLostPointerCapture={() => { drag.current = null; }}
                      onKeyDown={(event) => {
                        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
                        event.preventDefault();
                        const pair = measurePair(event.currentTarget, column, neighbor);
                        if (pair) resize(pair, event.key === 'ArrowRight' ? 10 : -10);
                      }} />
                  )}
                </th>
              );
            })}</tr>
          </thead>
          <tbody>
            {rows.length ? rows.map((row) => (
              <tr key={rowKey(row)}>{visible.map((column) => (
                <td key={column.id} data-column={column.id} data-tour={column.id === 'actions' ? 'business-row-actions' : undefined}>
                  <div className="business-list__cell">{column.render(row)}</div>
                </td>
              ))}</tr>
            )) : <tr><td className="business-list__empty" colSpan={Math.max(1, visible.length)}>{empty}</td></tr>}
          </tbody>
        </Table>
      </div>
      <footer className="business-list__footer">
        <div className="business-list__range">
          <span>{page.from !== null ? `${page.from} a ${page.to} de ${page.total}` : '0 registros'}</span>
          <div className="business-list__page-size" data-tour="business-page-size">
            <SelectControl menuTourTarget="business-page-size-options" id={pageSizeId} label="Registros por página" value={String(page.per_page)}
              options={pageSizeOptions} onChange={changePageSize} />
          </div>
        </div>
        <nav className="business-list__pages" data-tour="business-pagination" aria-label="Paginação">
          <PageLink href={page.prev_page_url ? page.first_page_url : null} label="Primeira página" icon="fa-angle-double-left" />
          <PageLink href={page.prev_page_url} label="Página anterior" icon="fa-angle-left" />
          <span aria-current="page" aria-label={`Página ${page.current_page} de ${page.last_page}`}>{page.current_page}</span>
          <PageLink href={page.next_page_url} label="Próxima página" icon="fa-angle-right" />
          <PageLink href={page.next_page_url ? page.last_page_url : null} label="Última página" icon="fa-angle-double-right" />
        </nav>
      </footer>
    </CardBox>
  );
}

function PageLink({ href, label, icon }: { href: string | null; label: string; icon: string }) {
  const content = <i className={`fa ${icon}`} aria-hidden="true" />;
  return href ? <Link href={href} aria-label={label}>{content}</Link>
    : <span aria-disabled="true" aria-label={label}>{content}</span>;
}
