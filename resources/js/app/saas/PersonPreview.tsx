import { useEffect, useState } from 'react';
import Modal from '@/components/Modal/Modal';
import { useContextUrl } from '@/hooks/useContextUrl';
import { formatMasked } from './ClientFormFields';
import { formatTaxDocument } from '@/utils/formatTaxDocument';
import PreviewSection from './PreviewSection';
import TagLabel from '@/components/TagLabel/TagLabel';
import type { PersonRow } from './Directory.types';
import './AccountPreview.css';
import './Directory.css';
export default function PersonPreview({
  name,
  endpoint,
  onClose,
}: {
  name: string;
  endpoint: string;
  onClose: () => void;
}) {
  const [person, setPerson] = useState<PersonRow>();
  const [error, setError] = useState(false);
  // O endereço pode vir relativo à conta (@/) ou à unidade (~/): o fetch direto não conhece esses prefixos.
  const contextUrl = useContextUrl();
  useEffect(() => {
    const controller = new AbortController();
    let target: string;
    try {
      target = contextUrl(endpoint);
    } catch {
      setError(true);
      return;
    }
    fetch(target, {
      signal: controller.signal,
      headers: { Accept: 'application/json' },
    })
      .then(async (response) => {
        if (!response.ok) throw new Error();
        setPerson((await response.json()) as PersonRow);
      })
      .catch(() => {
        if (!controller.signal.aborted) setError(true);
      });
    return () => controller.abort();
  }, [contextUrl, endpoint]);
  return (
    <Modal
      open
      title={name}
      onClose={onClose}
      size="large"
      contentClassName="account-preview"
      bootstrap
      manageFocus
    >
      {!person ? (
        <p role={error ? 'alert' : 'status'}>
          {error ? 'Não foi possível abrir o cadastro.' : 'Carregando…'}
        </p>
      ) : (
        <>
          <section
            className="account-preview__section"
            data-tour="person-preview"
          >
            <h3>Dados da pessoa</h3>
            <dl>
              <dt>{person.person_type === 'company' ? 'Razão social' : 'Nome'}</dt>
              <dd>{person.name}</dd>
              <dt>E-mail</dt>
              <dd>{person.email || 'Não informado'}</dd>
              <dt>Telefone</dt>
              <dd>{formatMasked(person.phone ?? '', 'phone') || 'Não informado'}</dd>
              {!!person.additional_phones?.length && <><dt>Telefones adicionais</dt><dd>{person.additional_phones.map((phone) => formatMasked(phone, 'phone')).join(', ')}</dd></>}
              {!!person.additional_emails?.length && <><dt>E-mails adicionais</dt><dd>{person.additional_emails.join(', ')}</dd></>}
              <dt>Etiquetas</dt>
              <dd className="directory-tags">
                {person.tags.length
                  ? person.tags.map((tag) => (
                      <TagLabel
                        key={tag.id}
                        name={tag.name}
                        color={tag.color}
                      />
                    ))
                  : 'Não informado'}
              </dd>
            </dl>
          </section>
          <PreviewSection title="Identificação e endereço">
            <dt>Tipo de pessoa</dt><dd>{person.person_type === 'company' ? 'Pessoa jurídica' : 'Pessoa física'}</dd>
            <dt>{person.person_type === 'company' ? 'CNPJ' : 'CPF'}</dt><dd>{formatTaxDocument(person.document ?? null, person.person_type ?? 'individual')}</dd>
            {person.person_type === 'company' && <><dt>Nome fantasia</dt><dd>{person.trade_name || 'Não informado'}</dd></>}
            <dt>Inscrição estadual</dt><dd>{person.state_registration || 'Não informado'}</dd>
            <dt>Inscrição municipal</dt><dd>{person.municipal_registration || 'Não informado'}</dd>
            <dt>Pessoa de contato</dt><dd>{person.contact_name || 'Não informado'}</dd>
            <dt>WhatsApp</dt><dd>{formatMasked(person.whatsapp ?? '', 'phone') || 'Não informado'}</dd>
            <dt>Site</dt><dd>{person.website || 'Não informado'}</dd>
            <dt>Endereço</dt><dd>{[person.address?.street, person.address?.number, person.address?.complement, person.address?.neighborhood, person.address?.city, person.address?.state, person.address?.postal_code ? formatMasked(person.address.postal_code, 'cep') : null].filter(Boolean).join(', ') || 'Não informado'}</dd>
          </PreviewSection>
        </>
      )}{' '}
    </Modal>
  );
}
