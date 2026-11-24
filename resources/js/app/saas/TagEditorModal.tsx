import { tagModules } from './tagModules';
import { useEffect, useRef } from 'react';
import BusinessForm, {
  BusinessFormActions,
  BusinessFormSection,
} from '@/components/BusinessForm/BusinessForm';
import { Field, Select } from '@/components/BusinessForm/BusinessFormFields';
import ColorPicker from '@/components/ColorPicker/ColorPicker';
import Button from '@/components/Button/Button';
import Modal from '@/components/Modal/Modal';
import { useApiPage } from '@/hooks/useApiPage';
import type { SessionProps } from '@/types/Session.types';
import type { TagRow } from './Directory.types';
import './TagEditorModal.css';
export default function TagEditorModal({
  tag,
  onClose,
}: {
  tag: TagRow | null;
  onClose: (restoreFocus?: boolean) => void;
}) {
  const { auth } = useApiPage<SessionProps>().props;
  const root = useRef<HTMLDivElement>(null);
  useEffect(() => {
    const node = root.current;
    const close = () => onClose(false);
    node?.addEventListener('tag-editor:close-demo', close);
    return () => node?.removeEventListener('tag-editor:close-demo', close);
  }, [onClose]);
  const readOnly =
    auth.inspection_mode === 'read-only' || Boolean(tag?.system_key);
  const title = tag ? 'Editar etiqueta' : 'Cadastrar etiqueta';
  return (
    <Modal
      open
      bootstrap
      manageFocus
      size="large"
      contentClassName="tag-editor"
      title={title}
      onClose={() => onClose(true)}
    >
      <div ref={root} data-tour="tag-editor">
        <BusinessForm
          className="business-form--wide-labels"
          action={tag ? `@/etiquetas/${tag.id}` : '@/etiquetas'}
          method={tag ? 'patch' : 'post'}
          options={{ preserveScroll: true }}
          onSuccess={() => onClose(true)}
        >
          {({ errors, processing }) => (
            <>
              <fieldset disabled={readOnly} className="tag-editor__fields">
                <BusinessFormSection
                  title="Dados da etiqueta"
                  tourTarget="tag-fields"
                >
                  <Field
                    label="Nome"
                    name="name"
                    defaultValue={tag?.name}
                    error={errors.name}
                    required
                  />
                  <Select
                    label="Módulo"
                    info="Define em qual cadastro a etiqueta pode ser utilizada."
                    name="module"
                    defaultValue={tag?.module ?? 'customers'}
                    options={tag?.module === 'people'
                      ? [{ value: 'people', label: 'Cadastro anterior' }]
                      : tagModules}
                    error={errors.module}
                  />
                  <Select
                    label="Situação"
                    info="Etiquetas inativas mantêm os vínculos existentes, mas não podem ser selecionadas em novos vínculos."
                    name="active"
                    required
                    defaultValue={tag?.active === false ? '0' : '1'}
                    options={[
                      ...((tag?.active !== false || auth.permissions.includes('account.tags.activate')) ? [{ value: '1', label: 'Ativa' }] : []),
                      ...((tag?.active === false || auth.permissions.includes('account.tags.deactivate')) ? [{ value: '0', label: 'Inativa' }] : []),
                    ]}
                    error={errors.active}
                  />
                  <ColorPicker
                    defaultValue={tag?.color ?? '#3563e9'}
                    error={errors.color}
                  />
                </BusinessFormSection>
              </fieldset>
              <div data-tour="tag-modal-actions">
                {readOnly ? (
                  <Button tone="white" onClick={() => onClose(true)}>
                    Fechar
                  </Button>
                ) : (
                  <BusinessFormActions
                    inline
                    processing={processing}
                    onCancel={() => onClose(true)}
                  />
                )}
              </div>
            </>
          )}
        </BusinessForm>
      </div>
    </Modal>
  );
}
