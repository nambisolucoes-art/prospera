import { useApiPage as usePage } from '@/hooks/useApiPage';
import { useCallback, useEffect, useRef, useState, type ReactNode } from 'react';
import type { NotificationToastItem, NotificationToastTone } from '@/components/NotificationToast/NotificationToast';
import NotificationToastLayer from '@/components/NotificationToast/NotificationToastLayer';

const titles = {
  success: 'Sucesso',
  error: 'Não foi possível concluir',
  warning: 'Atenção',
  info: 'Informação',
} satisfies Partial<Record<NotificationToastTone, string>>;

export default function FlashLayout({ children }: { children: ReactNode }) {
  const toast = usePage().props.toast;
  const [notifications, setNotifications] = useState<NotificationToastItem[]>([]);
  const displayed = useRef<unknown>(undefined);
  const sequence = useRef(0);

  useEffect(() => {
    if (!toast || toast === displayed.current || typeof toast !== 'object') return;
    if (!('type' in toast) || typeof toast.type !== 'string' || !Object.prototype.hasOwnProperty.call(titles, toast.type)) return;
    if (!('message' in toast) || typeof toast.message !== 'string' || !toast.message.trim()) return;

    displayed.current = toast;
    const tone = toast.type as keyof typeof titles;
    const notification: NotificationToastItem = {
      id: ++sequence.current,
      title: titles[tone],
      text: toast.message,
      tone,
      position: 'top-right',
      autoHideDelay: 5000,
      clickToHide: false,
    };
    setNotifications((items) => [...items, notification]);
  }, [toast]);

  const close = useCallback((id: number) => {
    setNotifications((items) => items.filter((item) => item.id !== id));
  }, []);

  return (
    <>
      {children}
      <NotificationToastLayer notifications={notifications} onClose={close} />
    </>
  );
}
