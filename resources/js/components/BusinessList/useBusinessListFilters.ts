import { useEffect, useRef, useState } from 'react';
import { useApiPage } from '@/hooks/useApiPage';

/**
 * Query string state shared by business lists: debounced search, immediate filters and
 * preservation of the other parameters, such as per_page. Changing anything returns to page 1.
 */
export function useBusinessListFilters(base: string, search: string, searchKey = 'search') {
  const { url, navigate } = useApiPage();
  const [query, setQuery] = useState(search);
  const submitted = useRef(search);
  const timer = useRef<number | undefined>(undefined);
  const latest = useRef({ url, navigate, query });
  latest.current = { url, navigate, query };

  function apply(changes: Record<string, string>) {
    window.clearTimeout(timer.current);
    const params = new URL(latest.current.url, window.location.origin).searchParams;
    params.delete('page');
    const values = { [searchKey]: latest.current.query.trim(), ...changes };
    for (const [key, value] of Object.entries(values)) {
      if (value) params.set(key, value);
      else params.delete(key);
    }
    submitted.current = values[searchKey];
    void latest.current.navigate(base + (params.size ? '?' + params : ''), { preserveScroll: true });
  }
  const applyRef = useRef(apply);
  applyRef.current = apply;

  useEffect(() => {
    if (search === submitted.current) return;
    submitted.current = search;
    setQuery(search);
  }, [search]);

  useEffect(() => {
    if (query.trim() === submitted.current) return;
    timer.current = window.setTimeout(() => applyRef.current({}), 350);
    return () => window.clearTimeout(timer.current);
  }, [query]);

  return { query, setQuery, submit: () => apply({}), apply };
}
