import type { AnchorHTMLAttributes } from 'react';
import { useApiPage } from '@/hooks/useApiPage';
import { contextualUrl } from '@/utils/organizationContext';

export default function ApiLink({ href, onClick, preserveScroll, ...attrs }: AnchorHTMLAttributes<HTMLAnchorElement> & {
  href: string;
  preserveScroll?: boolean;
}) {
  const { navigate, url } = useApiPage();
  const target = contextualUrl(href, url);
  return (
    <a {...attrs} href={target} onClick={(event) => {
      onClick?.(event);
      if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
        || attrs.target || attrs.download !== undefined || target.startsWith('#')
        || event.currentTarget.origin !== location.origin) return;
      event.preventDefault();
      void navigate(target, { preserveScroll });
    }} />
  );
}
