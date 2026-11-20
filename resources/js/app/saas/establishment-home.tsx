import { useState } from 'react';
import ApiLink from '@/components/ApiLink/ApiLink';
import Button from '@/components/Button/Button';
import { useApiPage } from '@/hooks/useApiPage';
import { workspaceUrl } from '@/utils/organizationContext';
import type { ContextEstablishment, SessionProps } from '@/types/Session.types';
import EstablishmentLabels from './EstablishmentLabels';
import EstablishmentPreview from './EstablishmentPreview';
import PreviewSection from './PreviewSection';
import SaasLayout from './SaasLayout';
import './Organization.css';

type Establishment = ContextEstablishment & { id: number; can_view: boolean; can_update: boolean };

// Início do estabelecimento (E06): destino do seletor e base das futuras rotas operacionais da unidade.
export default function EstablishmentHome({ establishment, workspace }: {
  establishment?: Establishment;
  workspace?: ContextEstablishment[];
}) {
  if (workspace) {
    const codes = workspace.map((unit) => unit.code).join(',');
    return (
      <SaasLayout tenant title="Área de trabalho" breadcrumb={[{ label: 'Área de trabalho' }]}>
        <div className="card-box" data-tour="establishment-home-summary">
          <h2>Estabelecimentos selecionados</h2>
          <ul>
            {workspace.map((unit) => (
              <li key={unit.code}>
                <ApiLink href={`/conta/estabelecimento/${unit.code}?voltar-unidades=${codes}`}>
                  {unit.name}
                </ApiLink>
                {' ('}{unit.company_name}{')'}
              </li>
            ))}
          </ul>
          <p data-tour="establishment-home-tabs">
            A seleção vale para esta aba. Abra um estabelecimento para operar nele.
          </p>
        </div>
      </SaasLayout>
    );
  }

  return establishment ? <SingleEstablishment establishment={establishment} /> : null;
}

function SingleEstablishment({ establishment }: { establishment: Establishment }) {
  const { url, props: { auth } } = useApiPage<SessionProps>();
  const returning = new URL(url, window.location.origin).searchParams.get('voltar-unidades')?.split(',') ?? [];
  const workspace = returning.length > 0 && returning.every((code) => /^[a-z0-9][a-z0-9-]{1,39}$/.test(code))
    ? { label: 'Área de trabalho', href: workspaceUrl(returning) }
    : null;
  const [preview, setPreview] = useState(false);
  const writable = auth.inspection_mode !== 'read-only';

  return (
    <SaasLayout
      tenant
      title={establishment.name}
      breadcrumb={[
        ...(workspace ? [workspace] : []),
        { label: establishment.company_name },
        { label: establishment.name },
      ]}
      actions={
        <>
          {establishment.can_view && (
            <Button tone="default" leftIconClass="fa fa-eye" onClick={() => setPreview(true)}>
              Ver cadastro
            </Button>
          )}
          {writable && establishment.can_update && (
            <Button
              href={`/conta/estabelecimentos/${establishment.id}/editar`}
              tone="primary"
              leftIconClass="fa fa-pencil"
            >
              Editar
            </Button>
          )}
        </>
      }
    >
      <div className="card-box establishment-home account-preview" data-tour="establishment-home-summary">
        <PreviewSection title="Estabelecimento atual">
          <dt>Estabelecimento</dt>
          <dd>{establishment.name}</dd>
          <dt>Empresa</dt>
          <dd>{establishment.company_name}</dd>
          <dt>Classificação</dt>
          <dd>
            <EstablishmentLabels classification={establishment.classification} primary={establishment.primary} />
          </dd>
        </PreviewSection>
        <p className="establishment-home__note" data-tour="establishment-home-tabs">
          <i className="fa fa-info-circle" aria-hidden="true" /> A unidade escolhida vale somente para esta aba.
          Outras abas continuam na unidade em que foram abertas, e cada operação é conferida pelo servidor.
        </p>
      </div>
      {preview && (
        <EstablishmentPreview
          establishment={{ id: establishment.id, name: establishment.name }}
          onClose={() => setPreview(false)}
        />
      )}
    </SaasLayout>
  );
}
