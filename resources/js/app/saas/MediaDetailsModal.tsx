import { useState } from 'react';
import ApiForm from '@/components/ApiForm/ApiForm';
import '@/components/BusinessForm/BusinessForm.css';
import { Field } from '@/components/BusinessForm/BusinessFormFields';
import Button from '@/components/Button/Button';
import Table from '@/components/Table/Table';
import FormHelp from '@/components/FormHelp/FormHelp';
import Label from '@/components/Label/Label';
import Modal from '@/components/Modal/Modal';
import { useContextUrl } from '@/hooks/useContextUrl';
import { formatBytes, formatDate, mediaBase, type MediaItem, type MediaOwner } from './Media.types';
import './Media.css';

// Detalhes de uma mídia: pré-visualização autorizada, metadados editáveis, variantes e exclusão conservadora.
export default function MediaDetailsModal({ owner, item, writable, onClose }: {
  owner: MediaOwner; item: MediaItem; writable: boolean; onClose: () => void;
}) {
  const contextUrl = useContextUrl();
  const base = `${mediaBase(owner)}/${item.id}`;
  const largest = item.variants[item.variants.length - 1];
  const [deleting, setDeleting] = useState(false);

  if (deleting) {
    return (
      <Modal open bootstrap manageFocus size="small" title="Excluir imagem" onClose={() => setDeleting(false)}>
        <ApiForm action={base} method="delete" onSuccess={onClose} options={{ preserveScroll: true }}>
          {({ processing }) => (
            <>
              <p>Excluir esta imagem? Se ela estiver em uso, a exclusão fica solicitada e acontece quando deixar de ser usada.</p>
              <div className="business-form__actions-inline">
                <Button tone="white" disabled={processing} onClick={() => setDeleting(false)}>Cancelar</Button>
                <Button type="submit" tone="danger" disabled={processing}>Excluir</Button>
              </div>
            </>
          )}
        </ApiForm>
      </Modal>
    );
  }

  return (
    <Modal open bootstrap manageFocus size="large" title={`${item.purpose_label} #${item.id}`} onClose={onClose}>
      <div className="media__details">
        {largest && (
          <img className="media__preview" src={contextUrl(`${base}/variantes/${largest.kind}`)}
            alt={item.alt_text ?? ''} width={largest.width} height={largest.height} />
        )}
        <div className="institutional__badges">
          <Label variant={item.scope === 'corporate' ? 'primary' : 'info'}>
            {item.scope === 'corporate' ? 'Biblioteca corporativa' : `Local${item.establishment_name ? `: ${item.establishment_name}` : ''}`}
          </Label>
          {item.status === 'removal_requested' && <Label variant="warning">Exclusão solicitada</Label>}
          {!item.alt_text && <Label variant="warning">Sem texto alternativo</Label>}
        </div>
        <dl className="institutional__summary">
          <div><dt>Original</dt><dd>{item.width} x {item.height} px, {formatBytes(item.bytes)}, {item.mime.replace('image/', '').toUpperCase()}</dd></div>
          <div><dt>Enviada em</dt><dd>{formatDate(item.created_at)}</dd></div>
        </dl>

        <h4 className="institutional__subtitle">Variantes otimizadas</h4>
        <Table condensed className="media__variants" data-tour="media-variants">
          <thead><tr><th>Tipo</th><th>Dimensões</th><th>Tamanho</th></tr></thead>
          <tbody>
            {item.variants.map((variant) => (
              <tr key={variant.kind}><td>{variant.kind}</td><td>{variant.width} x {variant.height}</td><td>{formatBytes(variant.bytes)}</td></tr>
            ))}
          </tbody>
        </Table>

        {item.can_update && writable ? (
          <ApiForm className="business-form" action={base} method="patch" onSuccess={onClose} options={{ preserveScroll: true }}>
            {({ errors, processing }) => (
              <>
                <Field label="Texto alternativo" name="alt_text" defaultValue={item.alt_text ?? ''} maxLength={300} error={errors.alt_text}
                  info="Descreva a imagem. Obrigatório quando ela for conteúdo de página; uma imagem decorativa exige marcação explícita no uso." />
                <Field label="Licença ou crédito" name="license_note" defaultValue={item.license_note ?? ''} maxLength={300} error={errors.license_note} />
                <div className="business-form__actions-inline business-form__control-start">
                  <Button tone="white" onClick={onClose}>Cancelar</Button>
                  <Button type="submit" tone="success" disabled={processing}>Salvar</Button>
                </div>
              </>
            )}
          </ApiForm>
        ) : (
          <dl className="institutional__summary">
            <div><dt>Texto alternativo</dt><dd>{item.alt_text ?? 'Não informado'}</dd></div>
            <div><dt>Licença ou crédito</dt><dd>{item.license_note ?? 'Não informado'}</dd></div>
          </dl>
        )}

        {item.scope === 'corporate' && !item.can_update && (
          <FormHelp>Esta imagem é da biblioteca corporativa: a empresa a gerencia e a unidade só a consulta.</FormHelp>
        )}
        <div className="institutional__actions">
          {item.can_update && (
            <a className="button button--white button--sm" href={contextUrl(`${base}/original`)} download>
              <i className="fa fa-download" aria-hidden="true" /> Baixar original
            </a>
          )}
          {item.can_delete && writable && (
            <Button tone="danger" size="sm" labelIconClass="fa fa-trash" onClick={() => setDeleting(true)}>Excluir imagem</Button>
          )}
        </div>
      </div>
    </Modal>
  );
}
