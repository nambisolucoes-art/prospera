import { useEffect, useId, useRef, useState, type ReactNode } from 'react';
import FormField from '@/components/FormField/FormField';
import ChoiceControl from '@/components/ChoiceControl/ChoiceControl';
import { SelectMenu } from '@/components/SelectControl/SelectDropdown';
import '@/components/SelectControl/SelectControl.css';
import './TagSelect.css';
import TagLabel from '@/components/TagLabel/TagLabel';
import { tagColors } from '@/components/TagLabel/TagLabel.colors';

type Option = {
  id: number;
  name: string;
  color: string;
  system_key?: string | null;
  locked?: boolean;
};
export default function TagSelect({
  options,
  selected,
  onChange,
  error,
  notice,
  disabled = false,
}: {
  options: Option[];
  selected: number[];
  onChange: (ids: number[]) => void;
  error?: string;
  notice?: ReactNode;
  disabled?: boolean;
}) {
  const id = useId();
  const root = useRef<HTMLDivElement>(null);
  const input = useRef<HTMLInputElement>(null);
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const matches = options.filter((option) =>
    option.name.toLocaleLowerCase().includes(query.toLocaleLowerCase()),
  );
  function toggle(option: Option) {
    if (disabled || option.locked) return;
    onChange(
      selected.includes(option.id)
        ? selected.filter((value) => value !== option.id)
        : [...selected, option.id],
    );
  }
  useEffect(() => {
    if (!open) return;
    const close = (event: PointerEvent) => {
      if (event.target instanceof Node && !root.current?.contains(event.target))
        setOpen(false);
    };
    document.addEventListener('pointerdown', close);
    return () => document.removeEventListener('pointerdown', close);
  }, [open]);
  return (
    <FormField id={id} label="Etiquetas" layout="horizontal" error={error}>
      <div
        ref={root}
        className="selectControl tag-select"
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
            input.current?.focus();
          }
        }}
      >
        <div
          className={`tag-select__control ${error ? 'tag-select__control--invalid' : ''}`}
          onClick={() => {
            if (!disabled) {
              setOpen(true);
              input.current?.focus();
            }
          }}
        >
          {options
            .filter((option) => selected.includes(option.id))
            .map((option) => (
              <span
                key={option.id}
                className="tag-select__tag"
                style={tagColors(option.color)}
              >
                {option.name}
                {!option.locked && !disabled && (
                  <button
                    type="button"
                    aria-label={`Remover etiqueta ${option.name}`}
                    onClick={(event) => {
                      event.stopPropagation();
                      toggle(option);
                      input.current?.focus();
                    }}
                  >
                    ×
                  </button>
                )}
              </span>
            ))}
          <input
            ref={input}
            id={id}
            role="combobox"
            aria-expanded={open}
            aria-controls={open ? `${id}-options` : undefined}
            aria-haspopup="dialog"
            aria-invalid={Boolean(error)}
            aria-describedby={[error ? `${id}-error` : '', notice ? `${id}-notice` : ''].filter(Boolean).join(' ') || undefined}
            autoComplete="off"
            disabled={disabled}
            placeholder={
              selected.length ? 'Adicionar etiqueta…' : 'Selecionar etiquetas…'
            }
            value={query}
            onFocus={(event) => {
              if (!root.current?.contains(event.relatedTarget)) setOpen(true);
            }}
            onChange={(event) => {
              setQuery(event.target.value);
              setOpen(true);
            }}
            onKeyDown={(event) => {
              if (event.key === 'Enter') {
                event.preventDefault();
                if (open && matches[0]) {
                  toggle(matches[0]);
                  setQuery('');
                } else setOpen(true);
              }
              if (event.key === 'ArrowDown') {
                event.preventDefault();
                setOpen(true);
                requestAnimationFrame(() =>
                  root.current
                    ?.querySelector<HTMLInputElement>(
                      '[role="dialog"] input:not(:disabled)',
                    )
                    ?.focus(),
                );
              }
              if (event.key === 'Backspace' && !query) {
                const removable = options.filter(
                  (option) => selected.includes(option.id) && !option.locked,
                );
                const last = removable[removable.length - 1];
                if (last) toggle(last);
              }
            }}
          />
        </div>
        {selected.map((value) => (
          <input key={value} type="hidden" name="tag_ids[]" value={value} />
        ))}

        <SelectMenu open={open && !disabled}
          id={`${id}-options`}
          role="dialog"
          aria-label="Selecionar etiquetas"
        >
          <div className="selectControl__checklist">
            {matches.map((option) => (
              <ChoiceControl
                key={option.id}
                id={`${id}-${option.id}`}
                type="checkbox"
                tone="primary"
                label={<TagLabel name={option.name} color={option.color} />}
                aria-label={option.name}
                checked={selected.includes(option.id)}
                disabled={option.locked}
                onChange={() => toggle(option)}
              />
            ))}
            {!matches.length && <p>Nenhuma etiqueta encontrada.</p>}
          </div>
        </SelectMenu>
      </div>
      {notice && <div id={`${id}-notice`} className="tag-select__notice" role="status">{notice}</div>}
    </FormField>
  );
}
