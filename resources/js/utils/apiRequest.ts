export type ApiErrors = Record<string, string>;

export class ApiError extends Error {
  constructor(message: string, public status: number, public errors: ApiErrors) {
    super(message);
  }
}

function csrfCookie() {
  const cookie = document.cookie.split('; ').find((item) => item.startsWith('XSRF-TOKEN='));
  return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
}

export async function apiRequest<T>(url: string, init: RequestInit = {}): Promise<T> {
  const target = new URL(url, window.location.origin);
  if (target.origin !== window.location.origin) throw new Error('Endereço inválido.');
  const headers = new Headers(init.headers);
  headers.set('Accept', 'application/json');
  if (!['GET', 'HEAD'].includes(init.method?.toUpperCase() ?? 'GET')) {
    const csrf = csrfCookie();
    if (csrf) headers.set('X-XSRF-TOKEN', csrf);
  }

  const response = await fetch(target, { ...init, headers, credentials: 'same-origin' });
  const content = response.headers.get('Content-Type');
  const payload = content?.includes('application/json') && response.status !== 204 ? await response.json() : undefined;
  if (!response.ok) {
    const errors: ApiErrors = {};
    if (payload?.errors && typeof payload.errors === 'object') {
      for (const [name, value] of Object.entries(payload.errors)) {
        const first = Array.isArray(value) ? value[0] : value;
        if (typeof first === 'string') errors[name] = first;
      }
    }
    throw new ApiError(payload?.message ?? 'Não foi possível concluir a operação.', response.status, errors);
  }
  if (!content?.includes('application/json') && response.status !== 204) {
    throw new ApiError('Resposta inesperada do servidor.', response.status, {});
  }

  return (payload ?? {}) as T;
}
