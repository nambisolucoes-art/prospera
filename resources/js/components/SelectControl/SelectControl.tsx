import { useEffect, useRef, useState, type KeyboardEvent, type ReactNode } from 'react';
import { SelectMenu, SelectTrigger } from './SelectDropdown';
import './SelectControl.css';

export type SelectOption = { value: string; label: string; searchText?: string };

export type SelectControlProps<Option extends SelectOption = SelectOption> = {
  name?: string;
  options: readonly Option[];
  defaultValue?: string;
  value?: string;
  onChange?: (value: string) => void;
  searchable?: boolean;
  required?: boolean;
  maxVisible?: number;
  renderOption?: (option: Option) => ReactNode;
};

function searchKey(value: string) {
  return value.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();
}

export default function SelectControl<Option extends SelectOption>({
  id, label, labelledBy, invalid, name, options, defaultValue, value, onChange, searchable, required,
  renderOption = (option) => option.label, maxVisible,
}: SelectControlProps<Option> & { id: string; label: string; labelledBy?: string; invalid?: boolean }) {
  const [internalValue, setInternalValue] = useState(defaultValue ?? options[0]?.value ?? '');
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [active, setActive] = useState(-1);
  const trigger = useRef<HTMLButtonElement>(null);
  const search = useRef<HTMLInputElement>(null);
  const activeOption = useRef<HTMLLIElement>(null);
  const listId = `${id}-options`;
  const current = value ?? internalValue;
  const selected = options.find((option) => option.value === current);
  const match = searchKey(query.trim());
  const matches = match ? options.filter(({ value, label, searchText }) =>
    searchKey(`${value} ${label} ${searchText ?? ''}`).includes(match)) : options;
  const visible = maxVisible ? matches.slice(0, maxVisible) : matches;
  const activeId = open && visible[active] ? `${listId}-${active}` : undefined;

  useEffect(() => {
    if (open && searchable) search.current?.focus();
  }, [open, searchable]);

  useEffect(() => {
    activeOption.current?.scrollIntoView({ block: 'nearest' });
  }, [activeId]);

  function show(initial = options.findIndex((option) => option.value === current)) {
    setQuery('');
    setActive(initial >= 0 && initial < (maxVisible ?? options.length) ? initial : 0);
    setOpen(true);
  }

  function close(restoreFocus = false) {
    setOpen(false);
    if (restoreFocus) trigger.current?.focus();
  }

  function choose(option: SelectOption) {
    setInternalValue(option.value);
    onChange?.(option.value);
    close(true);
  }

  function onKeyDown(event: KeyboardEvent<HTMLButtonElement | HTMLInputElement>) {
    const fromSearch = event.currentTarget === search.current;
    switch (event.key) {
      case 'ArrowDown':
      case 'ArrowUp': {
        event.preventDefault();
        const down = event.key === 'ArrowDown';
        if (!open) show(down ? 0 : Math.min(options.length, maxVisible ?? options.length) - 1);
        else if (visible.length) setActive((index) => (index + (down ? 1 : -1) + visible.length) % visible.length);
        break;
      }
      case 'Home':
      case 'End':
        if (open && !fromSearch) {
          event.preventDefault();
          setActive(event.key === 'Home' ? 0 : visible.length - 1);
        }
        break;
      case 'Escape':
        if (open) {
          event.preventDefault();
          event.stopPropagation();
          close(true);
        }
        break;
      case 'Enter':
      case ' ':
        if (fromSearch && event.key === ' ') break;
        event.preventDefault();
        if (!open) show();
        else if (visible[active]) choose(visible[active]);
        break;
    }
  }

  return (
    <div className={`selectControl${searchable ? ' selectControl--searchable' : ''}`}
      onBlur={(event) => {
        if (!event.currentTarget.contains(event.relatedTarget)) close();
      }}>
      {name && <input type="hidden" name={name} value={current} />}
      <SelectTrigger ref={trigger} id={id} aria-label={labelledBy ? undefined : label}
        aria-labelledby={labelledBy} aria-expanded={open} aria-controls={open ? listId : undefined}
        aria-activedescendant={searchable ? undefined : activeId}
        aria-required={required || undefined} aria-invalid={invalid || undefined} aria-describedby={invalid ? `${id}-error` : undefined}
        onClick={() => open ? close() : show()} onKeyDown={onKeyDown}>
        {selected ? renderOption(selected) : 'Selecione'}
      </SelectTrigger>
      {open && (
        <SelectMenu>
          {searchable && (
            <div className="selectControl__search">
              <input ref={search} type="text" role="combobox" aria-label={`Buscar ${label.toLowerCase()}`}
                aria-expanded aria-controls={listId} aria-activedescendant={activeId} aria-autocomplete="list"
                autoComplete="off" value={query} onKeyDown={onKeyDown}
                onChange={(event) => { setQuery(event.target.value); setActive(0); }} />
              <i className="fa fa-search" aria-hidden="true" />
            </div>
          )}
          <ul id={listId} role="listbox" aria-label={label} className="selectControl__options">
            {visible.map((option, index) => (
              <li key={option.value} id={`${listId}-${index}`} ref={index === active ? activeOption : undefined}
                role="option" aria-selected={option.value === current} data-active={index === active || undefined}
                onMouseDown={(event) => event.preventDefault()} onClick={() => choose(option)}>
                {renderOption(option)}
              </li>
            ))}
          </ul>
          {!visible.length && <p role="status" className="selectControl__empty">Nenhum resultado encontrado.</p>}
          {matches.length > visible.length && <p className="selectControl__empty">Digite para refinar a busca.</p>}
        </SelectMenu>
      )}
    </div>
  );
}
