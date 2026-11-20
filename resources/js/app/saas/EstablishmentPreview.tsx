import { useEffect, useState } from 'react';
import Modal from '@/components/Modal/Modal';
import StatusLabel from '@/components/StatusLabel/StatusLabel';
import { formatMasked } from './ClientFormFields';
import PreviewSection from './PreviewSection';
import EstablishmentLabels from './EstablishmentLabels';
import { companyName, type EstablishmentDetails, type EstablishmentRow } from './Organization.types';
import './AccountPreview.css';
import './Organization.css';
export default function EstablishmentPreview({
  establishment: row,
  onClose,
}: {
  establishment: Pick<EstablishmentRow, 'id' | 'name'>;
  onClose: () => void;
}) {
  const [establishment, setEstablishment] = useState<EstablishmentDetails>();
  const [error, setError] = useState(false);
  useEffect(() => {
    const controller = new AbortController();
    fetch(`/conta/estabelecimentos/${row.id}/visualizar`, {
      signal: controller.signal,
      headers: { Accept: 'application/json' },
    })
      .then(async (response) => {
        if (!response.ok) throw new Error();
        setEstablishment((await response.json()) as EstablishmentDetails);
      })
      .catch(() => {
        if (!controller.signal.aborted) setError(true);
      });
    return () => controller.abort();
  }, [row.id]);
  const address = establishment?.address;
  return (
    <Modal open title={row.name} onClose={onClose} size="large" contentClassName="account-preview" bootstrap manageFocus>
      {!establishment ? (
        <p role={error ? 'alert' : 'status'}>
          {error ? 'Não foi possível abrir o estabelecimento.' : 'Carregando…'}
        </p>
      ) : (
        <>
          <PreviewSection title="Dados do estabelecimento">
            <dt>Nome</dt><dd>{establishment.name}</dd>
            <dt>Nome fantasia</dt><dd>{establishment.trade_name || 'Não informado'}</dd>
            <dt>Empresa</dt><dd>{companyName(establishment.company)}</dd>
            <dt>Código</dt><dd>{establishment.code}</dd>
            <dt>Classificação</dt>
            <dd><EstablishmentLabels classification={establishment.classification} primary={establishment.is_primary} /></dd>
            <dt>Situação</dt><dd><StatusLabel active={establishment.active} masculine /></dd>
          </PreviewSection>
          <PreviewSection title="Identificação fiscal">
            <dt>CNPJ</dt><dd>{establishment.tax_document_label ?? 'Não informado'}</dd>
            <dt>Inscrição estadual</dt><dd>{establishment.state_registration || 'Não informado'}</dd>
            <dt>Inscrição municipal</dt><dd>{establishment.municipal_registration || 'Não informado'}</dd>
          </PreviewSection>
          <PreviewSection title="Endereço e contatos">
            <dt>Endereço</dt>
            <dd>{[address?.street, address?.number, address?.complement, address?.neighborhood, address?.city, address?.state,
              address?.postal_code ? formatMasked(address.postal_code, 'cep') : null].filter(Boolean).join(', ') || 'Não informado'}</dd>
            <dt>Telefone</dt><dd>{formatMasked(establishment.phone ?? '', 'phone') || 'Não informado'}</dd>
            <dt>WhatsApp</dt><dd>{formatMasked(establishment.whatsapp ?? '', 'phone') || 'Não informado'}</dd>
            <dt>E-mail</dt><dd>{establishment.email || 'Não informado'}</dd>
            {!!establishment.additional_phones?.length && <><dt>Telefones adicionais</dt>
              <dd>{establishment.additional_phones.map((phone) => formatMasked(phone, 'phone')).join(', ')}</dd></>}
            {!!establishment.additional_emails?.length && <><dt>E-mails adicionais</dt>
              <dd>{establishment.additional_emails.join(', ')}</dd></>}
          </PreviewSection>
        </>
      )}
    </Modal>
  );
}
