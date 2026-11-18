import { useEffect, useId, useRef, useState } from 'react';
import ChoiceControl from '@/components/ChoiceControl/ChoiceControl';
import { SelectMenu, SelectTrigger } from './SelectDropdown';
import './SelectControl.css';

type ChecklistOption = { id: string; label: string; checked: boolean; disabled?: boolean };

export default function SelectChecklist({ label, options, onChange, onReset }: {
  label: string;
  options: ChecklistOption[];
  onChange: (id: string, checked: boolean) => void;
  onReset: () => void;
}) {
  const id = useId();
  const container = useRef<HTMLDivElement>(null);
  const trigger = useRef<HTMLButtonElement>(null);
  const [open, setOpen] = useState(false);

  useEffect(() => {
    if (!open) return;
    function closeOutside(event: PointerEvent) {
      if (event.target instanceof Node && !container.current?.contains(event.target)) setOpen(false);
    }
    document.addEventListener('pointerdown', closeOutside);
    return () => document.removeEventListener('pointerdown', closeOutside);
  }, [open]);

  return (
    <div ref={container} className="selectControl selectControl--checklist"
      onBlur={(event) => {
        if (event.relatedTarget && !event.currentTarget.contains(event.relatedTarget)) setOpen(false);
      }}
      onKeyDown={(event) => {
        if (event.key === 'Escape' && open) {
          event.stopPropagation();
          setOpen(false);
          trigger.current?.focus();
        }
      }}>
      <SelectTrigger ref={trigger} aria-label={label} aria-expanded={open} aria-haspopup="dialog"
        aria-controls={open ? id : undefined} onClick={() => setOpen(!open)}
        onKeyDown={(event) => {
          if (event.key === 'ArrowDown' && !open) {
            event.preventDefault();
            setOpen(true);
          }
        }}>{label}</SelectTrigger>
      {open && <SelectMenu id={id} role="dialog" aria-label="Colunas visíveis">
        <div className="selectControl__checklist">
          {options.map((option) => (
            <ChoiceControl key={option.id} id={`${id}-${option.id}`} type="checkbox" tone="primary"
              label={option.label} checked={option.checked} disabled={option.disabled}
              onChange={(checked) => onChange(option.id, checked)} />
          ))}
        </div>
        <button className="selectControl__reset" type="button" onClick={onReset}>Restaurar padrão</button>
      </SelectMenu>}
    </div>
  );
}
