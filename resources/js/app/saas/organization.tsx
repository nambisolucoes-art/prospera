import { useRef, useState } from 'react';
import Link from '@/components/ApiLink/ApiLink';
import Button from '@/components/Button/Button';
import CardBox from '@/components/CardBox/CardBox';
import Label from '@/components/Label/Label';
import NestableRow from '@/components/NestableRow/NestableRow';
import StatusLabel from '@/components/StatusLabel/StatusLabel';
import { useApiPage } from '@/hooks/useApiPage';
import { joinClassNames } from '@/utils/joinClassNames';
import CompanyPreview from './CompanyPreview';
import EstablishmentLabels from './EstablishmentLabels';
import EstablishmentPreview from './EstablishmentPreview';
import type { Classification } from './Organization.types';
import SaasLayout from './SaasLayout';
import '@/components/BusinessList/BusinessList.css';
import './Organization.css';

type Company = { id: number; name: string; trade_name: string | null; active: boolean };
type Establishment = Company & { is_primary: boolean; classification: Classification; company_name: string };
type Page<T> = { data: T[]; current_page: number; last_page: number; prev_page_url: string | null; next_page_url: string | null };
type Preview = { kind: 'company' | 'establishment'; row: { id: number; name: string } };

function TreePages({ page, label }: { page: Page<unknown>; label: string }) {
  if (page.last_page <= 1) return null;

  return (
    <nav className="organization-tree__pagination" aria-label={label}>
      {page.prev_page_url && <Link href={page.prev_page_url} preserveScroll>Anterior</Link>}
      <span>Página {page.current_page} de {page.last_page}</span>
      {page.next_page_url && <Link href={page.next_page_url} preserveScroll>Próxima</Link>}
    </nav>
  );
}

export default function Organization({
  account,
  companies,
  focusedCompany,
  establishments,
  expandedCompany,
  level,
}: {
  account: { name: string };
  companies: Page<Company> | null;
  focusedCompany: Company | null;
  establishments: Page<Establishment> | null;
  expandedCompany: number | null;
  level: 'conta' | null;
}) {
  const { props: { auth }, url } = useApiPage();
  const can = (permission: string) => auth.permissions.includes(permission);
  const writable = auth.inspection_mode !== 'read-only';
  const [preview, setPreview] = useState<Preview>();
  const previewTrigger = useRef<HTMLElement | null>(null);
  const companyRows = [...(focusedCompany ? [focusedCompany] : []), ...(companies?.data ?? [])];
  const companyPage = new URLSearchParams(url.split('?')[1]).get('page');
  const treePath = '/conta/organizacao';
  const origin = 'origem=organizacao';
  const companyPageParam = companyPage ? `&page=${encodeURIComponent(companyPage)}` : '';
  const closePreview = () => {
    setPreview(undefined);
    requestAnimationFrame(() => previewTrigger.current?.focus());
  };

  const renderUnits = (page: Page<Establishment>) => (
    <>
      {page.data.length ? (
        <ul className="organization-tree__branch">
          {page.data.map((unit) => (
            <li key={unit.id}>
              <NestableRow className="organization-tree__row" data-tour="organization-establishments">
                <div className="organization-tree__entry">
                  <strong>{unit.trade_name || unit.name}</strong>
                  <Label variant="inverse">Estabelecimento</Label>
                  {!companies && <span className="organization-tree__type">{unit.company_name}</span>}
                  <EstablishmentLabels classification={unit.classification} primary={unit.is_primary} />
                  <StatusLabel active={unit.active} masculine />
                </div>
                <div className="business-list__actions organization-tree__actions">
                  {can('account.establishments.view') && (
                    <Button iconOnly tone="primary" leftIconClass="fa fa-info"
                      title="Visualizar cadastro" aria-label={`Visualizar estabelecimento ${unit.name}`} aria-haspopup="dialog"
                      onClick={(event) => {
                        previewTrigger.current = event.currentTarget;
                        setPreview({ kind: 'establishment', row: unit });
                      }} />
                  )}
                  {writable && can('account.establishments.update') && (
                    <Button iconOnly tone="primary" leftIconClass="fa fa-pencil"
                      title="Editar cadastro" aria-label={`Editar estabelecimento ${unit.name}`}
                      href={`/conta/estabelecimentos/${unit.id}/editar?${origin}`} />
                  )}
                </div>
              </NestableRow>
            </li>
          ))}
        </ul>
      ) : <p className="organization-tree__empty">Nenhum estabelecimento cadastrado.</p>}
      <TreePages page={page} label="Páginas de estabelecimentos" />
    </>
  );

  return (
    <SaasLayout tenant title="Organização">
      <CardBox className="organization-tree">
        <NestableRow className={joinClassNames('organization-tree__row', level === 'conta' && 'organization-tree__row--selected')}
          data-tour="organization-account">
          <div className="organization-tree__entry">
            <strong>{account.name}</strong>
            <Label variant="inverse">Conta contratante</Label>
          </div>
          <div className="business-list__actions organization-tree__actions">
            {writable && can('account.account-data.update') && (
              <Button iconOnly tone="primary" leftIconClass="fa fa-pencil" title="Editar dados da conta"
                aria-label="Editar dados da conta" href={`/conta/editar?${origin}`} />
            )}
            {writable && can('account.companies.create') && (
              <Button iconOnly tone="primary" leftIconClass="fa fa-plus" title="Cadastrar empresa"
                aria-label="Cadastrar empresa" href={`/conta/empresas/cadastrar?${origin}`} />
            )}
          </div>
        </NestableRow>
        {companies && (
          <div>
            {companyRows.length ? (
              <ul className="organization-tree__branch">
                {companyRows.map((company) => {
                  const expanded = expandedCompany === company.id;
                  const label = company.trade_name || company.name;
                  const branchId = `organization-company-${company.id}`;
                  return (
                    <li key={company.id}>
                      <NestableRow className="organization-tree__row" data-tour="organization-companies">
                        {can('account.establishments.list') ? (
                          <Link className="organization-tree__expand" preserveScroll aria-expanded={expanded}
                            aria-controls={branchId} aria-label={`${expanded ? 'Recolher' : 'Expandir'} estabelecimentos de ${label}`}
                            href={expanded ? `${treePath}?recolhida=1${companyPageParam}` : `${treePath}?empresa=${company.id}${companyPageParam}`}>
                            <span aria-hidden="true">{expanded ? '-' : '+'}</span>
                          </Link>
                        ) : null}
                        <div className="organization-tree__entry">
                          <strong>{label}</strong>
                          <Label variant="inverse">Empresa</Label>
                          <StatusLabel active={company.active} />
                        </div>
                        <div className="business-list__actions organization-tree__actions">
                          {can('account.companies.view') && (
                            <Button iconOnly tone="primary" leftIconClass="fa fa-info"
                              title="Visualizar cadastro" aria-label={`Visualizar empresa ${label}`} aria-haspopup="dialog"
                              onClick={(event) => {
                                previewTrigger.current = event.currentTarget;
                                setPreview({ kind: 'company', row: company });
                              }} />
                          )}
                          {writable && can('account.companies.update') && (
                            <Button iconOnly tone="primary" leftIconClass="fa fa-pencil"
                              title="Editar cadastro" aria-label={`Editar empresa ${label}`}
                              href={`/conta/empresas/${company.id}/editar?${origin}`} />
                          )}
                          {writable && company.active && can('account.establishments.create') && (
                            <Button iconOnly tone="primary" leftIconClass="fa fa-plus"
                              title="Cadastrar estabelecimento" aria-label={`Cadastrar estabelecimento em ${label}`}
                              href={`/conta/estabelecimentos/cadastrar?empresa=${company.id}&${origin}`} />
                          )}
                        </div>
                      </NestableRow>
                      <div id={branchId}>{expanded && establishments && renderUnits(establishments)}</div>
                    </li>
                  );
                })}
              </ul>
            ) : <p className="organization-tree__empty">Nenhuma empresa cadastrada.</p>}
            <TreePages page={companies} label="Páginas de empresas" />
          </div>
        )}
        {!companies && establishments && renderUnits(establishments)}
      </CardBox>
      {preview?.kind === 'company' && (
        <CompanyPreview company={preview.row} onClose={closePreview}
          establishmentsHref={`${treePath}?empresa=${preview.row.id}${companyPageParam}`} />
      )}
      {preview?.kind === 'establishment' && <EstablishmentPreview establishment={preview.row} onClose={closePreview} />}
    </SaasLayout>
  );
}
