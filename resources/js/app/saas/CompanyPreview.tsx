import { useEffect, useState } from 'react';
import Modal from '@/components/Modal/Modal';
import StatusLabel from '@/components/StatusLabel/StatusLabel';
import Link from '@/components/ApiLink/ApiLink';
import PreviewSection from './PreviewSection';
import EstablishmentLabels from './EstablishmentLabels';
import type { CompanyDetails, CompanyRow } from './Organization.types';
import './AccountPreview.css';
import './Organization.css';
export default function CompanyPreview({
  company: row,
  onClose,
  establishmentsHref,
}: {
  company: Pick<CompanyRow, 'id' | 'name'>;
  onClose: () => void;
  establishmentsHref?: string;
}) {
  const [company, setCompany] = useState<CompanyDetails>();
  const [error, setError] = useState(false);
  useEffect(() => {
    const controller = new AbortController();
    fetch(`/conta/empresas/${row.id}/visualizar`, {
      signal: controller.signal,
      headers: { Accept: 'application/json' },
    })
      .then(async (response) => {
        if (!response.ok) throw new Error();
        setCompany((await response.json()) as CompanyDetails);
      })
      .catch(() => {
        if (!controller.signal.aborted) setError(true);
      });
    return () => controller.abort();
  }, [row.id]);
  const individual = company?.person_type === 'individual';
  return (
    <Modal open title={row.name} onClose={onClose} size="large" contentClassName="account-preview" bootstrap manageFocus>
      {!company ? (
        <p role={error ? 'alert' : 'status'}>
          {error ? 'Não foi possível abrir a empresa.' : 'Carregando…'}
        </p>
      ) : (
        <>
          <PreviewSection title="Dados da empresa">
            <dt>{individual ? 'Nome' : 'Razão social'}</dt><dd>{company.name}</dd>
            <dt>Nome fantasia</dt><dd>{company.trade_name || 'Não informado'}</dd>
            <dt>Tipo de pessoa</dt>
            <dd>{company.person_type === null ? 'Não informado' : individual ? 'Pessoa física' : 'Pessoa jurídica'}</dd>
            <dt>{individual ? 'CPF' : 'Raiz do CNPJ'}</dt><dd>{company.document_label ?? 'Não informado'}</dd>
            <dt>Código</dt><dd>{company.code}</dd>
            <dt>Regime tributário</dt><dd>{company.tax_regime || 'Não informado'}</dd>
            <dt>CNAE principal</dt><dd>{company.main_cnae ? company.main_cnae.replace(/^(\d{4})(\d)(\d{2})$/, '$1-$2/$3') : 'Não informado'}</dd>
            <dt>Situação</dt><dd><StatusLabel active={company.active} /></dd>
          </PreviewSection>
          {company.establishments && (
            <PreviewSection title="Estabelecimentos">
              {company.establishments.map((establishment) => (
                <div className="organization-preview__unit" key={establishment.id}>
                  <dt>{establishment.name}</dt>
                  <dd className="organization-badges">
                    <EstablishmentLabels classification={establishment.classification} primary={establishment.is_primary} />
                    <StatusLabel active={establishment.active} masculine />
                  </dd>
                </div>
              ))}
              <dt>Consultar</dt>
              <dd>
                <Link href={establishmentsHref ?? `/conta/estabelecimentos?company=${company.id}`}
                  onClick={establishmentsHref ? onClose : undefined}>
                  Abrir estabelecimentos desta empresa
                </Link>
              </dd>
            </PreviewSection>
          )}
        </>
      )}
    </Modal>
  );
}
