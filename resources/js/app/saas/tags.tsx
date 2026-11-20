import { useEffect, useRef, useState } from 'react';
import BusinessList, {
  type PageSummary,
} from '@/components/BusinessList/BusinessList';
import Button from '@/components/Button/Button';
import TagLabel from '@/components/TagLabel/TagLabel';
import SelectControl from '@/components/SelectControl/SelectControl';
import { useApiPage } from '@/hooks/useApiPage';
import type { SessionProps } from '@/types/Session.types';
import SaasLayout from './SaasLayout';
import { AccessPreview } from './AccessList';
import type { TagRow } from './Directory.types';
import TagEditorModal from './TagEditorModal';
import ApiForm from '@/components/ApiForm/ApiForm';
import Modal from '@/components/Modal/Modal';
import StatusLabel from '@/components/StatusLabel/StatusLabel';
import CardBox from '@/components/CardBox/CardBox';
export default function Tags({
  tags,
  module,
  formOpen = false,
  tag = null,
  canList = true,
}: {
  tags: PageSummary & { data: TagRow[] };
  module: string;
  formOpen?: boolean;
  tag?: TagRow | null;
  canList?: boolean;
}) {
  const {
    navigate,
    props: { auth },
  } = useApiPage<SessionProps>();
  const [editor, setEditor] = useState<TagRow | null | undefined>(
    formOpen ? tag : undefined,
  );
  useEffect(() => {
    setEditor(formOpen ? tag : undefined);
  }, [formOpen, tag]);
  const closeEditor = (restoreFocus = true) => {
    setEditor(undefined);
    if (restoreFocus) requestAnimationFrame(() => trigger.current?.focus());
    if (formOpen && canList)
      void navigate('/conta/etiquetas', {
        replace: true,
        preserveScroll: true,
      });
  };
  const [deleting, setDeleting] = useState<TagRow>();
  const [preview, setPreview] = useState<TagRow>();
  const trigger = useRef<HTMLElement | null>(null);
  const writable = auth.inspection_mode !== 'read-only';
  const can = (action: string) =>
    auth.permissions.includes(`account.tags.${action}`);
  return (
    <SaasLayout
      tenant
      title="Etiquetas"
      actions={
        writable &&
        can('create') && (
          <Button
            onClick={(event) => {
              trigger.current = event.currentTarget;
              setEditor(null);
            }}
            aria-haspopup="dialog"
            tone="primary"
            leftIconClass="fa fa-plus"
          >
            Cadastrar etiqueta
          </Button>
        )
      }
    >
      {canList ? (
        <div data-tour="tags-list">
          <BusinessList
            label="Etiquetas"
            preferenceKey="tags"
            page={tags}
            rows={tags.data}
            rowKey={(tag) => tag.id}
            empty="Nenhuma etiqueta encontrada."
            filters={
              <div className="saas-search" data-tour="tags-module">
                <SelectControl
                  id="tags-module-filter"
                  label="Módulo"
                  value={module}
                  onChange={(value) =>
                    void navigate(
                      '/conta/etiquetas' + (value ? '?module=' + value : ''),
                    )
                  }
                  options={[
                    { value: '', label: 'Todos os módulos' },
                    { value: 'people', label: 'Pessoas' },
                  ]}
                />
              </div>
            }
            columns={[
              {
                id: 'actions',
                label: 'Ações',
                width: 130,
                compactWidth: 112,
                locked: true,
                render: (tag) => (
                  <div className="business-list__actions">
                    {can('view') && (
                      <Button
                        iconOnly
                        tone="primary"
                        leftIconClass="fa fa-info"
                        title="Visualizar cadastro"
                        aria-label={`Visualizar etiqueta ${tag.name}`}
                        onClick={(event) => {
                          trigger.current = event.currentTarget;
                          setPreview(tag);
                        }}
                      />
                    )}
                    {writable && can('update') && !tag.system_key && (
                      <Button
                        iconOnly
                        tone="primary"
                        leftIconClass="fa fa-pencil"
                        title="Editar cadastro"
                        aria-label={`Editar etiqueta ${tag.name}`}
                        onClick={(event) => {
                          trigger.current = event.currentTarget;
                          setEditor(tag);
                        }}
                        aria-haspopup="dialog"
                      />
                    )}
                    {writable && can('delete') && !tag.system_key && (
                      <Button
                        iconOnly
                        tone="danger"
                        leftIconClass="fa fa-eraser"
                        title={tag.people_count ? 'Etiqueta vinculada: use Editar cadastro para inativar' : 'Excluir etiqueta'}
                        aria-label={`Excluir etiqueta ${tag.name}`}
                        disabled={Boolean(tag.people_count)}
                        aria-haspopup="dialog"
                        onClick={(event) => {
                          trigger.current = event.currentTarget;
                          setDeleting(tag);
                        }}
                      />
                    )}
                  </div>
                ),
              },
              {
                id: 'name',
                label: 'Etiqueta',
                width: 220,
                compact: true,
                render: (tag) => (
                  <TagLabel
                    name={tag.name}
                    color={tag.color}
                    special={Boolean(tag.system_key)}
                  />
                ),
              },
              {
                id: 'active',
                label: 'Situação',
                width: 120,
                compact: true,
                render: (tag) => <StatusLabel active={tag.active} />,
              },
              {
                id: 'module',
                label: 'Módulo',
                width: 150,
                render: () => 'Pessoas',
              },
            ]}
          />
        </div>
      ) : (
        <CardBox>
          <p>Use Cadastrar etiqueta para incluir uma etiqueta nesta conta.</p>
        </CardBox>
      )}
      {deleting && (
        <Modal open bootstrap manageFocus size="small" title="Excluir etiqueta" onClose={() => { setDeleting(undefined); trigger.current?.focus(); }}>
          <ApiForm action={`/conta/etiquetas/${deleting.id}`} method="delete" onSuccess={() => { setDeleting(undefined); trigger.current?.focus(); }} options={{ preserveScroll: true }}>
            {({ processing }) => (
              <>
                <p>Excluir a etiqueta {deleting.name}? Esta ação não pode ser desfeita.</p>
                <div className="business-form__actions-inline">
                  <Button tone="white" disabled={processing} onClick={() => { setDeleting(undefined); trigger.current?.focus(); }}>Cancelar</Button>
                  <Button type="submit" tone="danger" disabled={processing}>Excluir</Button>
                </div>
              </>
            )}
          </ApiForm>
        </Modal>
      )}
      {editor !== undefined && (
        <TagEditorModal tag={editor} onClose={closeEditor} />
      )}
      {preview && (
        <AccessPreview
          base="/conta/etiquetas"
          size="default"
          row={preview}
          onClose={() => {
            setPreview(undefined);
            trigger.current?.focus();
          }}
        />
      )}
    </SaasLayout>
  );
}
