import { useApiPage as usePage } from '@/hooks/useApiPage';

export function usePathname(): string {
  return new URL(usePage().url, window.location.origin).pathname;
}
