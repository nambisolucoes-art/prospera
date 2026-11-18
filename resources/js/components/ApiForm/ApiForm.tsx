import type { FormHTMLAttributes, ReactNode } from 'react';
import { useApiForm, type SubmitOptions } from '@/hooks/useApiForm';
import type { ApiErrors } from '@/utils/apiRequest';

type FormState = { errors: ApiErrors; processing: boolean; recentlySuccessful: boolean };
type Props = Omit<FormHTMLAttributes<HTMLFormElement>, 'children' | 'method' | 'action' | 'onSubmit'> & {
  action: string;
  method?: 'get' | 'post' | 'put' | 'patch' | 'delete';
  children: ReactNode | ((state: FormState) => ReactNode);
  resetOnSuccess?: string[];
  options?: SubmitOptions;
  onSuccess?: () => void;
};

export default function ApiForm({ action, method = 'post', children, resetOnSuccess, onSuccess, options, ...attrs }: Props) {
  const form = useApiForm({});

  return (
    <form {...attrs} action={action} method={method === 'get' ? 'get' : 'post'} onSubmit={(event) => {
      event.preventDefault();
      const element = event.currentTarget;
      const data = new FormData(element, (event.nativeEvent as SubmitEvent).submitter);
      void form.submit(method, action, {
        ...options,
        onSuccess: () => {
          resetOnSuccess?.forEach((name) => {
            const field = element.elements.namedItem(name);
            if (field instanceof HTMLInputElement) field.value = field.defaultValue;
          });
          onSuccess?.();
        },
      }, data);
    }}>
      {typeof children === 'function' ? children(form) : children}
      {form.errors._form && <p className="formField__error" role="alert">{form.errors._form}</p>}
    </form>
  );
}
