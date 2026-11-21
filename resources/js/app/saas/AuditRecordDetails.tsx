import { useId, useState } from 'react';
import AuditEventLabel from '@/components/AuditEventLabel/AuditEventLabel';
import Table from '@/components/Table/Table';
import Label from '@/components/Label/Label';
import PreviewSection from './PreviewSection';
import type { AuditDetail } from './audit';
import '@/components/Button/Button.css';
import './AuditRecordDetails.css';
const levels: Record<AuditDetail['level'], string> = {
  platform: 'Plataforma SaaS',
  tenant: 'Conta',
  company: 'Empresa',
  establishment: 'Estabelecimento',
};
function value(v: unknown): string {
  if (v === null || v === undefined || v === '') return 'Não informado';
  if (typeof v === 'boolean') return v ? 'Sim' : 'Não';
  if (Array.isArray(v)) return v.map(value).join(', ') || 'Nenhum';
  if (typeof v === 'object') return JSON.stringify(v);
  return String(v);
}
function ListChange({
  before,
  after,
}: {
  before: unknown[];
  after: unknown[];
}) {
  const old = before.map(value),
    next = after.map(value);
  const added = next.filter((v) => !old.includes(v)),
    removed = old.filter((v) => !next.includes(v));
  return (
    <ul className="audit-record__list">
      {added.map((v, i) => (
        <li key={'a' + i} className="audit-record__added">
          <i className="fa fa-plus" aria-hidden="true" />
          <span className="audit-record__sr">Adicionado: </span>
          {v}
        </li>
      ))}
      {removed.map((v, i) => (
        <li key={'r' + i} className="audit-record__removed">
          <i className="fa fa-minus" aria-hidden="true" />
          <span className="audit-record__sr">Removido: </span>
          {v}
        </li>
      ))}
    </ul>
  );
}
export default function AuditRecordDetails({
  detail,
}: {
  detail: AuditDetail;
}) {
  const [view, setView] = useState<'plain' | 'technical'>('plain');
  const id = useId();
  const comparing = detail.event_key === 'updated';
  const deleted = detail.event_key === 'deleted';
  function choose(next: 'plain' | 'technical') {
    setView(next);
  }
  return (
    <div className="audit-record">
      <div className="audit-record__meta">
        <div className="audit-record__badges" data-tour="audit-modal-summary">
          <AuditEventLabel event={detail.event_key}>{detail.event}</AuditEventLabel>
          <Label variant="inverse">{detail.type}</Label>
          <time dateTime={detail.created_at}>
            {new Date(detail.created_at).toLocaleString('pt-BR')}
          </time>
        </div>
        {detail.technical && (
          <div
            role="tablist"
            aria-label="Forma de exibição"
            data-tour="audit-modal-views"
            className="audit-record__tabs"
            onKeyDown={(e) => {
              if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(e.key)) {
                e.preventDefault();
                const next =
                  e.key === 'Home'
                    ? 'plain'
                    : e.key === 'End'
                      ? 'technical'
                      : view === 'plain'
                        ? 'technical'
                        : 'plain';
                choose(next);
                document.getElementById(id + '-' + next)?.focus();
              }
            }}
          >
            <button
              type="button"
              className="button button--inverse button--sm waves-effect waves-light"
              role="tab"
              id={id + '-plain'}
              aria-selected={view === 'plain'}
              aria-controls={id + '-panel'}
              tabIndex={view === 'plain' ? 0 : -1}
              data-audit-view="plain"
              onClick={() => choose('plain')}
            >
              <i className="fa fa-align-left" aria-hidden="true" />
              Formatado
            </button>
            <button
              type="button"
              className="button button--inverse button--sm waves-effect waves-light"
              role="tab"
              id={id + '-technical'}
              aria-selected={view === 'technical'}
              aria-controls={id + '-panel'}
              tabIndex={view === 'technical' ? 0 : -1}
              data-audit-view="technical"
              onClick={() => choose('technical')}
            >
              <i className="fa fa-code" aria-hidden="true" />
              Log técnico
            </button>
          </div>
        )}
      </div>
      <div
        id={id + '-panel'}
        role={detail.technical ? 'tabpanel' : undefined}
        aria-labelledby={detail.technical ? id + '-' + view : undefined}
        tabIndex={0}
        data-tour="audit-modal-content"
      >
        {view === 'technical' && detail.technical ? (
          <pre
            className="audit-record__technical"
            aria-label="Log técnico do registro"
          >
            {JSON.stringify(
              {
                ...detail.technical,
                registro: detail.name,
                acao: detail.event,
                autor: detail.actor,
                data: detail.created_at,
                alteracoes: detail.changes,
              },
              null,
              2,
            )}
          </pre>
        ) : (
          <>
            <div className="audit-record__sections" data-tour="audit-modal-context">
              <PreviewSection title="Operação">
                <dt>Registro</dt>
                <dd>{detail.name}</dd>
                <dt>Tipo</dt>
                <dd>{detail.type}</dd>
                <dt>Ação</dt>
                <dd>{detail.event}</dd>
                <dt>Data e hora</dt>
                <dd>{new Date(detail.created_at).toLocaleString('pt-BR')}</dd>
              </PreviewSection>
              <PreviewSection title="Autoria">
                <dt>Quem fez</dt>
                <dd>{detail.actor}</dd>
                <dt>Origem</dt>
                <dd>{detail.origin}</dd>
                {detail.represented_user_id !== null && (
                  <>
                    <dt>Usuário representado</dt>
                    <dd>{detail.represented ?? 'Não registrado neste evento'}</dd>
                    <dt>Modo da inspeção</dt>
                    <dd>{detail.inspection_mode ?? 'Não registrado neste evento'}</dd>
                  </>
                )}
              </PreviewSection>
              <PreviewSection title="Contexto organizacional">
                <dt>Nível</dt>
                <dd>{levels[detail.level]}</dd>
                {detail.account && (
                  <>
                    <dt>Conta</dt>
                    <dd>{detail.account}</dd>
                  </>
                )}
                <dt>Empresa</dt>
                <dd>{detail.company ?? 'Não se aplica'}</dd>
                <dt>Estabelecimento</dt>
                <dd>{detail.establishment ?? 'Não se aplica'}</dd>
              </PreviewSection>
            </div>
            {detail.changes.length ? (
              <div className="audit-record__table-wrap">
                <Table hover headerTone="primary" className="audit-record__table">
                  <thead>
                    <tr>
                      <th scope="col">Campo</th>
                      {comparing ? (
                        <>
                          <th scope="col">Antes</th>
                          <th scope="col">Depois</th>
                        </>
                      ) : (
                        <th scope="col">
                          {deleted ? 'Valor ao excluir' : 'Valor registrado'}
                        </th>
                      )}
                    </tr>
                  </thead>
                  <tbody>
                    {detail.changes.map((c, i) => (
                      <tr key={i}>
                        <th scope="row">{c.label}</th>
                        {Array.isArray(c.before) || Array.isArray(c.after) ? (
                          <td colSpan={comparing ? 2 : 1}>
                            <ListChange
                              before={Array.isArray(c.before) ? c.before : []}
                              after={Array.isArray(c.after) ? c.after : []}
                            />
                          </td>
                        ) : comparing ? (
                          <>
                            <td className="audit-record__before">
                              {value(c.before)}
                            </td>
                            <td className="audit-record__after">
                              {value(c.after)}
                            </td>
                          </>
                        ) : (
                          <td>{value(deleted ? c.before : c.after)}</td>
                        )}
                      </tr>
                    ))}
                  </tbody>
                </Table>
              </div>
            ) : (
              <p className="audit-record__empty">Nenhum campo registrado.</p>
            )}
          </>
        )}
      </div>
    </div>
  );
}
