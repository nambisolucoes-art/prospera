import type { OrganizationContextProps } from '@/types/Session.types';

// URLs com conta (E09) e contexto organizacional (E06). Ambos vêm sempre da URL da página, nunca de estado global.
const code = '[a-z0-9][a-z0-9-]{1,39}';
const accountPattern = new RegExp(`^/contas/${code}(?=/|$)`);
const contextPattern = new RegExp(`^/contas/${code}/(empresa|estabelecimento)/${code}(?=/|$)`);

/** Prefixo da conta da URL, como /contas/grupo-gartan, ou null fora de uma conta. */
export function accountBase(url: string): string | null {
  const { pathname } = new URL(url, window.location.origin);
  return accountPattern.exec(pathname)?.[0] ?? null;
}

/** Prefixo de contexto da URL, como /contas/grupo-gartan/estabelecimento/gartan-guanambi, ou null nas telas da conta. */
export function contextBase(url: string): string | null {
  const { pathname } = new URL(url, window.location.origin);
  return contextPattern.exec(pathname)?.[0] ?? null;
}

/** Caminho da tela sem a conta, para identificar o módulo: /contas/x/usuarios vira /conta/usuarios. */
export function screenPath(pathname: string): string {
  return pathname.replace(accountPattern, '/conta');
}

/**
 * Resolve endereços relativos no contexto da página que os declarou, de modo que um formulário aberto
 * numa aba continue gravando na conta e na unidade dela:
 * - @/ é relativo à conta da página (/contas/{conta}/...);
 * - ~/ é relativo à empresa ou ao estabelecimento da página.
 */
export function contextualUrl(url: string, pageUrl: string): string {
  if (url === '@' || url.startsWith('@/') || url.startsWith('@?')) {
    const account = accountBase(pageUrl);
    if (!account) throw new Error('Esta operação precisa de uma conta na URL.');
    const rest = url.slice(1).replace(/^\/$/, '');
    return `${account}${rest}`;
  }
  if (!url.startsWith('~/')) return url;
  const base = contextBase(pageUrl);
  if (!base) throw new Error('Esta operação precisa de uma empresa ou de um estabelecimento na URL.');
  const target = new URL(`${base}/${url.slice(2)}`, window.location.origin);
  const current = new URL(pageUrl, window.location.origin);
  const selection = current.searchParams.get('unidades');
  const returnSelection = current.searchParams.get('voltar-unidades');
  if (base.includes('/estabelecimento/') && returnSelection !== null) {
    target.searchParams.set('voltar-unidades', returnSelection);
  }
  if (base.endsWith('/estabelecimento/todos') && selection !== null) {
    target.searchParams.set('unidades', selection);
  }
  return target.pathname + target.search + target.hash;
}

/** Mantém o módulo compatível, descartando identificadores e filtros do contexto anterior. */
export function workspaceUrl(codes: string[], pageUrl = '', supportsConsolidated = false): string {
  const { pathname } = new URL(pageUrl, window.location.origin);
  const match = new RegExp(`^/contas/${code}/estabelecimento/${code}(/[a-z0-9-]+)?`).exec(pathname);
  const module = codes.length === 1 || supportsConsolidated ? match?.[1] ?? '' : '';
  const base = '@/estabelecimento';
  if (codes.length === 1) return `${base}/${codes[0]}${module}`;
  return `${base}/todos${module}?unidades=${codes.join(',')}`;
}

/**
 * Entrada da área de trabalho com a seleção desta aba. O servidor revalida as unidades e decide
 * o destino: uma unidade, o consolidado ou a escolha do estabelecimento quando o módulo exige um só.
 */
export function workspaceEntryUrl(codes: string[], destination: string | null = null): string {
  const query = [`unidades=${codes.join(',')}`];
  if (destination) query.push(`para=${encodeURIComponent(destination)}`);
  return `@/estabelecimento?${query.join('&')}`;
}

/**
 * Seleção definida pela URL da página: o consolidado usa os códigos validados pelo servidor, e uma
 * unidade aberta a partir do conjunto mantém o conjunto de retorno. Nas telas da conta, retorna null.
 */
export function pageWorkspace(context: OrganizationContextProps, pageUrl: string): string[] | null {
  if (context.mode === 'consolidated') return context.selected_codes;
  if (context.mode !== 'establishment') return null;

  const returning = new URL(pageUrl, window.location.origin).searchParams.get('voltar-unidades')?.split(',') ?? [];
  return returning.includes(context.selected_codes[0]) ? returning : context.selected_codes;
}

/** Chave da seleção desta aba, por conta, usuário e modo de inspeção (E06). */
export function workspaceStorageKey(accountId: number | null, userId: number | undefined, inspection: string | null): string {
  return `prospera.workspace.${accountId}.${userId}.${inspection ?? 'own'}`;
}

/**
 * Endereço de um módulo da área de trabalho a partir de qualquer tela: usa a seleção da URL ou a desta aba,
 * e passa pela entrada, que revalida as unidades no servidor. Sem seleção, a entrada usa o padrão pessoal.
 */
export function workspaceModuleUrl(context: OrganizationContextProps | null | undefined, pageUrl: string, tabCodes: string[], module: string): string {
  const available = new Set(context?.establishments.map((unit) => unit.code) ?? []);
  const codes = (context ? pageWorkspace(context, pageUrl) : null) ?? tabCodes;
  const valid = codes.filter((code) => available.has(code));
  return valid.length > 0 ? workspaceEntryUrl(valid, module) : `@/estabelecimento?para=${module}`;
}

/** Selection local to this browser tab. The server still validates every operational URL. */
export function readTabWorkspace(key: string): string[] {
  try {
    const value: unknown = JSON.parse(sessionStorage.getItem(key) ?? 'null');
    return Array.isArray(value) ? value.filter((code): code is string => typeof code === 'string') : [];
  } catch {
    return [];
  }
}

export function writeTabWorkspace(key: string, codes: string[]): void {
  try {
    sessionStorage.setItem(key, JSON.stringify(codes));
  } catch {
    // Keep the in-memory selection available when browser storage is unavailable.
  }
}
