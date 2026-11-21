import { useEffect, useId, useRef, useState } from 'react';
import ChoiceControl from '@/components/ChoiceControl/ChoiceControl';
import { SelectMenu, SelectTrigger } from './SelectDropdown';
import './SelectControl.css';

type ChecklistOption = {
  id: string;
  label: string;
  checked: boolean;
  disabled?: boolean;
};

export default function SelectChecklist({
  label,
  options,
  onChange,
  menuLabel = 'Colunas visíveis',
}: {
  label: string;
  menuLabel?: string;
  options: ChecklistOption[];
  onChange: (id: string, checked: boolean) => void;
}) {
  const id = useId();
  const container = useRef<HTMLDivElement>(null);
  const trigger = useRef<HTMLButtonElement>(null);
  const [open, setOpen] = useState(false);

  useEffect(() => {
    const node = container.current;
    const change = (event: Event) =>
      setOpen(Boolean((event as CustomEvent<{ open: boolean }>).detail.open));
    node?.addEventListener('prospera:tour-disclosure', change);
    return () => node?.removeEventListener('prospera:tour-disclosure', change);
  }, []);

  useEffect(() => {
    if (!open) return;
    function closeOutside(event: PointerEvent) {
      if (
        event.target instanceof Node &&
        !container.current?.contains(event.target)
      )
        setOpen(false);
    }
    document.addEventListener('pointerdown', closeOutside);
    return () => document.removeEventListener('pointerdown', closeOutside);
  }, [open]);

  return (
    <div
      ref={container}
      className="selectControl selectControl--checklist"
      onBlur={(event) => {
        if (
          event.relatedTarget &&
          !event.currentTarget.contains(event.relatedTarget)
        )
          setOpen(false);
      }}
      onKeyDown={(event) => {
        if (event.key === 'Escape' && open) {
          event.stopPropagation();
          setOpen(false);
          trigger.current?.focus();
        }
      }}
    >
      <SelectTrigger
        ref={trigger}
        aria-label={label}
        aria-expanded={open}
        aria-haspopup="dialog"
        aria-controls={open ? id : undefined}
        onClick={() => setOpen(!open)}
        onKeyDown={(event) => {
          if (event.key === 'ArrowDown' && !open) {
            event.preventDefault();
            setOpen(true);
          }
        }}
      >
        {label}
      </SelectTrigger>

      <SelectMenu open={open}
        id={id}
        data-tour={menuLabel === 'Colunas visíveis' ? 'business-columns-options' : undefined}
        role="dialog"
        aria-label={menuLabel}
      >
        <div className="selectControl__checklist">
          {options.map((option) => (
            <ChoiceControl
              key={option.id}
              id={`${id}-${option.id}`}
              type="checkbox"
              tone="primary"
              label={option.label}
              checked={option.checked}
              disabled={option.disabled}
              onChange={(checked) => onChange(option.id, checked)}
            />
          ))}
        </div>
      </SelectMenu>
    </div>
  );
}
