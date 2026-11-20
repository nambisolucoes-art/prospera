import type { ReactNode } from 'react';
import './AccountPreview.css';
export default function PreviewSection({
  title,
  children,
}: {
  title: string;
  children: ReactNode;
}) {
  return (
    <section className="account-preview__section">
      <h3>{title}</h3>
      <dl>{children}</dl>
    </section>
  );
}
