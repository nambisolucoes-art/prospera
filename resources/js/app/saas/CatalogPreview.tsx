import { useEffect, useState } from 'react';
import { useContextUrl } from '@/hooks/useContextUrl';
import Modal from '@/components/Modal/Modal';
import StatusLabel from '@/components/StatusLabel/StatusLabel';
import PreviewSection from './PreviewSection';
import { article, type CatalogConfig, type CatalogPreviewRow, type CatalogRow } from './CompanyCatalog.types';
import './AccountPreview.css';

const dateTime = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' });

function when(value: string | null): string {
  return value ? dateTime.format(new Date(value)) : 'Não informado';
}

export default function CatalogPreview({ config, row, onClose }: {
  config: CatalogConfig;
  row: CatalogRow;
  onClose: () => void;
}) {
  const to = useContextUrl();
  const [record, setRecord] = useState<CatalogPreviewRow>();
  const [error, setError] = useState(false);
  useEffect(() => {
    const controller = new AbortController();
    fetch(to(`@/${config.segment}/${row.id}/visualizar`), {
      signal: controller.signal,
      headers: { Accept: 'application/json' },
    })
      .then(async (response) => {
        if (!response.ok) throw new Error();
        setRecord((await response.json()) as CatalogPreviewRow);
      })
      .catch(() => {
        if (!controller.signal.aborted) setError(true);
      });
    return () => controller.abort();
  }, [config.segment, row.id, to]);

  return (
    <Modal open title={record?.name ?? row.name} onClose={onClose} size="default" contentClassName="account-preview" bootstrap manageFocus>
      {!record ? (
        <p role={error ? 'alert' : 'status'}>
          {error ? `Não foi possível abrir ${article(config)} ${config.noun}.` : 'Carregando…'}
        </p>
      ) : (
        <PreviewSection title={`Dados d${article(config)} ${config.noun}`}>
          <dt>Nome</dt><dd>{record.name}</dd>
          <dt>Empresa</dt><dd>{record.company_name}</dd>
          <dt>Situação</dt><dd><StatusLabel active={record.active} masculine={config.masculine} /></dd>
          <dt>Cadastrado em</dt><dd>{when(record.created_at)}</dd>
          <dt>Última alteração</dt><dd>{when(record.updated_at)}</dd>
        </PreviewSection>
      )}
    </Modal>
  );
}
