import { useRef, useState } from 'react';
import { useApiPage, type NavigationOptions } from '@/hooks/useApiPage';
import { ApiError, apiRequest, type ApiErrors } from '@/utils/apiRequest';
import { contextualUrl } from '@/utils/organizationContext';

type Values = Record<string, string | number | boolean | File | null>;
export type SubmitOptions = NavigationOptions & {
  successHref?: string;
  beforeNavigate?: () => Promise<void>;
  onSuccess?: () => void;
  onError?: (errors: ApiErrors) => void;
};
type Method = 'get' | 'post' | 'patch' | 'put' | 'delete';

export function useApiForm<T extends Values>(initial: T) {
  const defaults = useRef(initial);
  const busy = useRef(false);
  const { url: pageUrl, navigate } = useApiPage();
  const [data, setValues] = useState(initial);
  const [errors, setErrors] = useState<ApiErrors>({});
  const [processing, setProcessing] = useState(false);
  const [recentlySuccessful, setRecentlySuccessful] = useState(false);

  function setData<K extends keyof T>(key: K, value: T[K]) {
    setValues((current) => ({ ...current, [key]: value }));
  }

  function clearErrors(...keys: string[]) {
    setErrors((current) => keys.length
      ? Object.fromEntries(Object.entries(current).filter(([key]) => !keys.includes(key))) : {});
  }

  function resetAndClearErrors() {
    setValues(defaults.current);
    setErrors({});
    setRecentlySuccessful(false);
  }

  async function submit(method: Method, address: string, options: SubmitOptions = {}, submitted?: FormData) {
    if (busy.current) return;
    busy.current = true;
    setProcessing(true);
    setRecentlySuccessful(false);
    setErrors({});

    try {
      // O destino é fixado pela página que montou o formulário, nunca pela unidade aberta em outra aba.
      const endpoint = contextualUrl(address, pageUrl);
      const body = submitted ?? new FormData();
      if (!submitted) Object.entries(data).forEach(([key, value]) => {
        if (value !== null) body.append(key, value instanceof File ? value : String(value));
      });
      if (method === 'get') {
        const url = new URL(endpoint, location.origin);
        for (const [key, value] of body) if (typeof value === 'string') url.searchParams.append(key, value);
        if (!await navigate(url.href, options)) return;
      } else {
        // Laravel reads multipart data on POST, including method overrides for update and delete forms.
        if (method !== 'post') body.set('_method', method.toUpperCase());
        const result = await apiRequest<{ redirect?: string }>(endpoint, { method: 'POST', body });
        await options.beforeNavigate?.();
        const destination = result.redirect ?? options.successHref ?? pageUrl;
        const samePage = new URL(destination, location.origin).href === new URL(pageUrl, location.origin).href;
        if (!await navigate(destination, { replace: samePage, preserveScroll: samePage, ...options })) return;
      }
      setRecentlySuccessful(true);
      options.onSuccess?.();
    } catch (error) {
      if (error instanceof ApiError && (error.status === 401 || error.status === 419)) {
        await navigate('/login', { replace: true });
        return;
      }
      const fields = error instanceof ApiError && Object.keys(error.errors).length
        ? error.errors : { _form: error instanceof Error ? error.message : 'Não foi possível concluir a operação.' };
      setErrors(fields);
      options.onError?.(fields);
    } finally {
      busy.current = false;
      setProcessing(false);
    }
  }

  return {
    data, errors, processing, recentlySuccessful, setData, clearErrors, resetAndClearErrors, submit,
    post: (url: string, options?: SubmitOptions) => void submit('post', url, options),
    delete: (url: string, options?: SubmitOptions) => void submit('delete', url, options),
  };
}
