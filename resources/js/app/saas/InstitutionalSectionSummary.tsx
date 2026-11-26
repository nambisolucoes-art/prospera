import InstitutionalHoursSummary from './InstitutionalHoursSummary';
import type { HoursValue, ProfileFont, ProfileNetwork, Resolved } from './InstitutionalProfile.types';

const text = (value: unknown) => (typeof value === 'string' && value.trim() !== '' ? value : null);
const list = (value: unknown) => (Array.isArray(value) ? value.filter((item): item is string => typeof item === 'string') : []);

// Resumo somente leitura do valor efetivo de uma seção, no mesmo estilo dos demais cadastros.
export default function InstitutionalSectionSummary({ sectionKey, resolved, fonts, networks }: {
  sectionKey: string;
  resolved: Resolved;
  fonts: ProfileFont[];
  networks: ProfileNetwork[];
}) {
  const value = resolved.value;
  if (resolved.mode === 'disabled' || value === null) return <p className="institutional__empty">Sem conteúdo.</p>;

  if (sectionKey === 'palette') {
    const colors = ['primary', 'secondary', 'accent', 'background', 'text'] as const;
    const names = { primary: 'Principal', secondary: 'Secundária', accent: 'Destaque', background: 'Fundo', text: 'Texto' };
    return (
      <ul className="institutional__swatches">
        {colors.map((color) => (
          <li key={color}>
            <span className="institutional__swatch" style={{ background: String(value[color]) }} aria-hidden="true" />
            {names[color]} <code>{String(value[color])}</code>
          </li>
        ))}
      </ul>
    );
  }

  if (sectionKey === 'typography') {
    const label = (key: unknown) => fonts.find((font) => font.key === key)?.label ?? String(key);
    return <p>Títulos: <strong>{label(value.heading)}</strong>. Texto: <strong>{label(value.body)}</strong>.</p>;
  }

  if (sectionKey === 'about') {
    const parts = [
      ['Título', text(value.title)], ['Descrição', text(value.description)], ['Missão', text(value.mission)],
    ] as const;
    const values = list(value.values);
    const differentials = list(value.differentials);
    if (parts.every(([, content]) => content === null) && values.length === 0 && differentials.length === 0) {
      return <p className="institutional__empty">Sem conteúdo (vazio intencional ou ainda não preenchido).</p>;
    }
    return (
      <dl className="institutional__summary">
        {parts.map(([label, content]) => content && <div key={label}><dt>{label}</dt><dd>{content}</dd></div>)}
        {values.length > 0 && <div><dt>Valores</dt><dd>{values.join(', ')}</dd></div>}
        {differentials.length > 0 && <div><dt>Diferenciais</dt><dd>{differentials.join(', ')}</dd></div>}
      </dl>
    );
  }

  if (sectionKey === 'contacts') {
    const rows = [['Telefones', list(value.phones)], ['WhatsApp', list(value.whatsapps)], ['E-mails', list(value.emails)]] as const;
    if (rows.every(([, items]) => items.length === 0)) return <p className="institutional__empty">Sem contatos públicos.</p>;
    return (
      <dl className="institutional__summary">
        {rows.map(([label, items]) => items.length > 0 && <div key={label}><dt>{label}</dt><dd>{items.join(', ')}</dd></div>)}
      </dl>
    );
  }

  if (sectionKey === 'social_links') {
    const items = Array.isArray(value.items) ? (value.items as { network: string; url: string }[]) : [];
    if (items.length === 0) return <p className="institutional__empty">Sem redes sociais.</p>;
    return (
      <dl className="institutional__summary">
        {items.map((item) => (
          <div key={item.network}><dt>{networks.find((network) => network.key === item.network)?.label ?? item.network}</dt><dd>{item.url}</dd></div>
        ))}
      </dl>
    );
  }

  if (sectionKey === 'hours_weekly') {
    return <InstitutionalHoursSummary value={value as unknown as HoursValue} />;
  }

  if (sectionKey === 'seo_defaults') {
    const title = text(value.title);
    const description = text(value.description);
    if (!title && !description) return <p className="institutional__empty">Sem conteúdo de SEO.</p>;
    return (
      <dl className="institutional__summary">
        {title && <div><dt>Título</dt><dd>{title}</dd></div>}
        {description && <div><dt>Descrição</dt><dd>{description}</dd></div>}
      </dl>
    );
  }

  return null;
}
