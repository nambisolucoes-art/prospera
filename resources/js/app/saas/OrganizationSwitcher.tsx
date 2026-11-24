import { useEffect, useEffectEvent, useId, useRef, useState } from 'react';
import ChoiceControl from '@/components/ChoiceControl/ChoiceControl';
import { SelectMenu, SelectTrigger } from '@/components/SelectControl/SelectDropdown';
import '@/components/SelectControl/SelectControl.css';
import { useApiPage } from '@/hooks/useApiPage';
import type { ContextEstablishment, OrganizationContextProps, SessionProps } from '@/types/Session.types';
import { apiRequest } from '@/utils/apiRequest';
import {
  contextualUrl,
  pageWorkspace,
  readTabWorkspace,
  workspaceEntryUrl,
  workspaceStorageKey,
  workspaceUrl,
  writeTabWorkspace,
} from '@/utils/organizationContext';
import './OrganizationSwitcher.css';

export default function OrganizationSwitcher({ context }: { context: OrganizationContextProps }) {
  const { props, url, navigate } = useApiPage<SessionProps>();
  // O padrão pessoal é da conta desta aba (E09); outra aba pode estar em outra conta.
  const preferenceUrl = contextualUrl('@/area-de-trabalho/padrao', url);
  const id = useId();
  const container = useRef<HTMLDivElement>(null);
  const trigger = useRef<HTMLButtonElement>(null);
  const [open, setOpen] = useState(false);
  const [draft, setDraft] = useState<string[]>([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const saving = useRef(false);
  const units = context.establishments;
  const key = workspaceStorageKey(context.account_id, props.auth.user?.id, props.auth.inspection_mode);
  const [local, setLocal] = useState<{ key: string; codes: string[] }>();
  const stored = local?.key === key ? local.codes : readTabWorkspace(key);
  const available = new Set(units.map((unit) => unit.code));
  // Nas telas com contexto, a URL manda; a seleção guardada na aba vale só nas telas da conta.
  const fromUrl = pageWorkspace(context, url)?.filter((code) => available.has(code)) ?? [];
  const retained = stored.filter((code) => available.has(code));
  const fallback = context.selected_codes.filter((code) => available.has(code));
  const current = fromUrl.length > 0 ? fromUrl
    : retained.length > 0 ? retained
    : fallback.length > 0 ? fallback
    : units.slice(0, 1).map((unit) => unit.code);

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

  /**
   * auto: aplica e, nas telas com área de trabalho, segue para a nova seleção;
   * stay: um link clicado já define o destino; workspace: aplica e abre a área de trabalho.
   */
  async function commitSelection(destination: 'auto' | 'stay' | 'workspace' = 'auto') {
    if (saving.current) return;

    // Uma seleção vazia mantém a anterior.
    const codes = selected.length > 0 ? selected : current;
    const unchanged = codes.length === current.length
      && current.every((code) => codes.includes(code));
    if (unchanged && destination !== 'workspace') {
      setOpen(false);
      return;
    }

    saving.current = true;
    setBusy(true);
    setError('');
    if (!unchanged) {
      writeTabWorkspace(key, codes);
      setLocal({ key, codes });
    }
    try {
      if (!unchanged && props.auth.inspection_mode === null) {
        await apiRequest(preferenceUrl, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ unidades: codes }),
        });
      }

      setOpen(false);

      if (destination === 'workspace') {
        await navigate(workspaceEntryUrl(codes));
      } else if (destination === 'auto' && context.consolidated) {
        // Na escolha de estabelecimento, o destino pendente passa de novo pelas regras da entrada.
        const pending = new URL(url, window.location.origin).searchParams.get('para');
        await navigate(pending ? workspaceEntryUrl(codes, pending) : workspaceUrl(codes, url, true));
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
    void commitSelection(link ? 'stay' : 'auto');
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
        if (event.relatedTarget && !event.currentTarget.contains(event.relatedTarget)) void commitSelection(event.relatedTarget.matches('a[href]') ? 'stay' : 'auto');
      }}
      onKeyDown={(event) => {
        if (event.key !== 'Escape' || !open || busy) return;
        event.stopPropagation();
        close();
      }}
    >
      <SelectTrigger
        ref={trigger}
        aria-label={`Área de trabalho, ${count} ${count === 1 ? 'unidade selecionada' : 'unidades selecionadas'}`}
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
        {!context.consolidated && (
          <a
            className="organization-switcher__open"
            href={contextualUrl(workspaceEntryUrl(selected.length > 0 ? selected : current), url)}
            aria-disabled={busy}
            onClick={(event) => {
              // Com teclas modificadoras, o navegador abre outra aba, que começa com a própria seleção.
              if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
              event.preventDefault();
              void commitSelection('workspace');
            }}
          >
            <i className="fa fa-arrow-right" aria-hidden="true" /> Abrir área de trabalho
          </a>
        )}
        {error && <p className="organization-switcher__error" role="alert">{error}</p>}
      </SelectMenu>
    </div>
  );
}
