import type { AnchorHTMLAttributes } from 'react';
import { useApiPage } from '@/hooks/useApiPage';

export default function ApiLink({ href, onClick, preserveScroll, ...attrs }: AnchorHTMLAttributes<HTMLAnchorElement> & {
  href: string;
  preserveScroll?: boolean;
}) {
  const { navigate } = useApiPage();
  return (
    <a {...attrs} href={href} onClick={(event) => {
      onClick?.(event);
      if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
        || attrs.target || attrs.download !== undefined || href.startsWith('#')
        || event.currentTarget.origin !== location.origin) return;
      event.preventDefault();
      void navigate(href, { preserveScroll });
    }} />
  );
}
