import { useEffect, useState } from 'react';
import Modal from '@/components/Modal/Modal';
import { formatMasked } from './ClientFormFields';
import { contactNumber, type ClientData } from './ClientForm.types';
import PreviewSection from './PreviewSection';
import './AccountPreview.css';

type Preview = ClientData & { administrator: { name: string; email: string } | null };

export default function AccountPreview({ id, name, onClose }: { id: number; name: string; onClose: () => void }) {
  const [account, setAccount] = useState<Preview>();
  const [error, setError] = useState(false);

  useEffect(() => {
    const controller = new AbortController();
    fetch(`/saas/contas/${id}/visualizar`, { signal: controller.signal, headers: { Accept: 'application/json' } })
      .then(async (response) => {
        if (!response.ok) throw new Error('Não foi possível carregar a conta.');
        setAccount(await response.json() as Preview);
      })
      .catch(() => { if (!controller.signal.aborted) setError(true); });
    return () => controller.abort();
  }, [id]);

  const address = account?.details?.address;

  return (
    <Modal open title={account?.name ?? name} onClose={onClose} size="large" contentClassName="account-preview" bootstrap manageFocus>
      {!account ? <p role={error ? 'alert' : 'status'}>{error ? 'Não foi possível abrir a conta.' : 'Carregando…'}</p> : (
        <>
          <PreviewSection title="Dados gerais">
            <dt>{account.person_type === 'company' ? 'Razão social' : 'Nome'}</dt><dd>{account.name}</dd>
            {account.details?.trade_name && <><dt>Nome fantasia</dt><dd>{account.details.trade_name}</dd></>}
            <dt>{account.person_type === 'company' ? 'CNPJ' : account.person_type === 'individual' ? 'CPF' : 'Documento'}</dt>
            <dd>{formatMasked(account.document ?? '', account.person_type === 'company' ? 'cnpj' : 'cpf') || 'Não informado'}</dd>
            <dt>Plano</dt><dd>{account.plan || 'Não informado'}</dd>
            <dt>Status</dt><dd>{account.active ? 'Ativa' : 'Inativa'}</dd>
          </PreviewSection>
          <PreviewSection title="Informações de endereço">
            <dt>Logradouro</dt><dd>{[address?.street, address?.number].filter(Boolean).join(', ') || 'Não informado'}</dd>
            {address?.complement && <><dt>Complemento</dt><dd>{address.complement}</dd></>}
            <dt>Bairro</dt><dd>{address?.neighborhood || 'Não informado'}</dd>
            <dt>Cidade</dt><dd>{[address?.city, address?.state].filter(Boolean).join(' - ') || 'Não informado'}</dd>
            <dt>CEP</dt><dd>{formatMasked(address?.postal_code ?? '', 'cep') || 'Não informado'}</dd>
          </PreviewSection>
          <PreviewSection title="Informações de contato">
            <dt>Telefone</dt><dd>{formatMasked(contactNumber(account.details, 'phone'), 'phone') || 'Não informado'}</dd>
            {!!account.details?.additional_phones?.length && <>
              <dt>Telefones adicionais</dt>
              <dd>{account.details.additional_phones.map((phone) => formatMasked(phone, 'phone')).join(', ')}</dd>
            </>}
            <dt>WhatsApp</dt><dd>{formatMasked(contactNumber(account.details, 'whatsapp'), 'phone') || 'Não informado'}</dd>
            <dt>E-mail de contato</dt><dd>{account.email || 'Não informado'}</dd>
            {!!account.details?.additional_emails?.length && <>
              <dt>E-mails adicionais</dt>
              <dd>{account.details.additional_emails.join(', ')}</dd>
            </>}
          </PreviewSection>
          <PreviewSection title="Administrador da conta">
            <dt>Nome</dt><dd>{account.administrator?.name || 'Não informado'}</dd>
            <dt>E-mail</dt><dd>{account.administrator?.email || 'Não informado'}</dd>
          </PreviewSection>
        </>
      )}
    </Modal>
  );
}
