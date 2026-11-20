import { useEffect, useEffectEvent, useId, useRef, useState } from 'react';
import ChoiceControl from '@/components/ChoiceControl/ChoiceControl';
import { SelectMenu, SelectTrigger } from '@/components/SelectControl/SelectDropdown';
import '@/components/SelectControl/SelectControl.css';
import { useApiPage } from '@/hooks/useApiPage';
import type { ContextEstablishment, OrganizationContextProps, SessionProps } from '@/types/Session.types';
import { apiRequest } from '@/utils/apiRequest';
import { readTabWorkspace, workspaceUrl, writeTabWorkspace } from '@/utils/organizationContext';
import './OrganizationSwitcher.css';

const preferenceUrl = '/conta/area-de-trabalho/padrao';

export default function OrganizationSwitcher({ context }: { context: OrganizationContextProps }) {
  const { props, url, navigate } = useApiPage<SessionProps>();
  const id = useId();
  const container = useRef<HTMLDivElement>(null);
  const trigger = useRef<HTMLButtonElement>(null);
  const [open, setOpen] = useState(false);
  const [draft, setDraft] = useState<string[]>([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const saving = useRef(false);
  const units = context.establishments;
  const key = `prospera.workspace.${context.account_id}.${props.auth.user?.id}.${props.auth.inspection_mode ?? 'own'}`;
  const [local, setLocal] = useState<{ key: string; codes: string[] }>();
  const stored = local?.key === key ? local.codes : readTabWorkspace(key);
  const available = new Set(units.map((unit) => unit.code));
  const retained = stored.filter((code) => available.has(code));
  const fallback = context.selected_codes.filter((code) => available.has(code));
  const current = retained.length > 0 ? retained : fallback.length > 0 ? fallback : units.slice(0, 1).map((unit) => unit.code);

  useEffect(() => {
    if (units.length > 1) writeTabWorkspace(key, current);
  }, [key, current, units.length]);

  const companies = new Map<string, ContextEstablishment[]>();
  for (const unit of units) {
    const group = companies.get(unit.company_code) ?? [];
    group.push(unit);
    companies.set(unit.company_code, group);
  }
  const marked = new Set(draft);
  const selected = units.filter((unit) => marked.has(unit.code)).map((unit) => unit.code);

  async function commitSelection(keepDestination = false) {
    if (saving.current) return;

    const unchanged = selected.length === current.length
      && current.every((code) => marked.has(code));
    if (selected.length === 0 || unchanged) {
      setOpen(false);
      return;
    }

    saving.current = true;
    setBusy(true);
    setError('');
    writeTabWorkspace(key, selected);
    setLocal({ key, codes: selected });
    try {
      if (props.auth.inspection_mode === null) {
        await apiRequest(preferenceUrl, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ unidades: selected }),
        });
      }

      setOpen(false);

      if (context.consolidated && !keepDestination) {
        await navigate(workspaceUrl(selected, url, true));
      }
    } catch (failure) {
      writeTabWorkspace(key, current);
      setLocal({ key, codes: current });
      setError(failure instanceof Error ? failure.message : 'Não foi possível salvar a seleção.');
    } finally {
      saving.current = false;
      setBusy(false);
    }
  }

  const commitOutside = useEffectEvent((target: Node) => {
    const link = target instanceof Element && target.closest('a[href]');
    void commitSelection(Boolean(link));
  });

  useEffect(() => {
    if (!open) return;
    function closeOutside(event: PointerEvent) {
      if (event.target instanceof Node && !container.current?.contains(event.target)) commitOutside(event.target);
    }
    document.addEventListener('pointerdown', closeOutside);
    return () => document.removeEventListener('pointerdown', closeOutside);
  }, [open]);

  if (units.length < 2) return null;

  function openMenu() {
    setDraft(current);
    setError('');
    setOpen(true);
  }

  function close() {
    setOpen(false);
    trigger.current?.focus();
  }

  const count = open ? selected.length : current.length;

  return (
    <div
      className="selectControl organization-switcher"
      data-tour="organization-switcher"
      ref={container}
      onBlur={(event) => {
        if (event.relatedTarget && !event.currentTarget.contains(event.relatedTarget)) void commitSelection(event.relatedTarget.matches('a[href]'));
      }}
      onKeyDown={(event) => {
        if (event.key !== 'Escape' || !open || busy) return;
        event.stopPropagation();
        close();
      }}
    >
      <SelectTrigger
        ref={trigger}
        aria-label={`Área de trabalho, ${count} unidades selecionadas`}
        aria-haspopup="dialog"
        aria-expanded={open}
        aria-controls={open ? id : undefined}
        disabled={busy}
        onClick={() => open ? void commitSelection() : openMenu()}
        onKeyDown={(event) => {
          if (event.key !== 'ArrowDown' || open) return;
          event.preventDefault();
          openMenu();
        }}
      >
        <i className="md md-store-mall-directory" aria-hidden="true" />
        <span className="organization-switcher__label">Área de trabalho</span>
        <span>{count}</span>
      </SelectTrigger>

      <SelectMenu open={open} id={id} role="dialog" aria-label="Área de trabalho" className="organization-switcher__menu">
        <div className="selectControl__checklist organization-switcher__groups">
          <ChoiceControl
            id={`${id}-all`}
            type="checkbox"
            tone="primary"
            label="Selecionar todas"
            checked={selected.length === units.length}
            disabled={busy}
            onChange={(checked) => {
              setDraft(checked ? units.map((unit) => unit.code) : []);
            }}
          />
          {Array.from(companies, ([code, group]) => {
            const company = group[0].company_name;
            const codes = group.map((unit) => unit.code);

            return (
              <div className="organization-switcher__company" key={code} role="group" aria-label={company}>
                <div className="organization-switcher__company-heading">
                  <span>{company}</span>
                  <span className="organization-switcher__company-rule" aria-hidden="true" />
                  <ChoiceControl
                    id={`workspace-company-${code}`}
                    type="checkbox"
                    tone="primary"
                    single
                    aria-label={`Todas as unidades de ${company}`}
                    checked={codes.every((unitCode) => marked.has(unitCode))}
                    disabled={busy}
                    onChange={(checked) => setDraft((current) => checked
                      ? [...new Set([...current, ...codes])]
                      : current.filter((unitCode) => !codes.includes(unitCode)))}
                  />
                </div>
                {group.map((unit) => (
                  <ChoiceControl
                    key={unit.code}
                    id={`workspace-unit-${unit.code}`}
                    type="checkbox"
                    tone="primary"
                    label={unit.name}
                    checked={marked.has(unit.code)}
                    disabled={busy}
                    onChange={(checked) => setDraft((current) => checked
                      ? [...current, unit.code]
                      : current.filter((unitCode) => unitCode !== unit.code))}
                  />
                ))}
              </div>
            );
          })}
        </div>
        {error && <p className="organization-switcher__error" role="alert">{error}</p>}
      </SelectMenu>
    </div>
  );
}
