import { useEffect } from 'react';

export default function PageTitle({ title }: { title: string }) {
  useEffect(() => {
    document.title = `${title} · Prospera`;
    return () => { document.title = 'Prospera'; };
  }, [title]);

  return null;
}
