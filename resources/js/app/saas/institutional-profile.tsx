import { useState } from 'react';
import Button from '@/components/Button/Button';
import CardBox from '@/components/CardBox/CardBox';
import Label from '@/components/Label/Label';
import InfoTooltip from '@/components/InfoTooltip/InfoTooltip';
import ChoiceControl from '@/components/ChoiceControl/ChoiceControl';
import { useApiForm } from '@/hooks/useApiForm';
import { useApiPage } from '@/hooks/useApiPage';
import type { SessionProps } from '@/types/Session.types';
import InstitutionalPublishModal from './InstitutionalPublishModal';
import InstitutionalSectionModal from './InstitutionalSectionModal';
import InstitutionalSectionSummary from './InstitutionalSectionSummary';
import {
  originText, ownerBase, type ProfileFont, type ProfileNetwork, type ProfileOwner, type ProfileSection,
} from './InstitutionalProfile.types';
import SaasLayout from './SaasLayout';
import './ContactFields.css';
import './Institutional.css';

type Unit = { code: string; name: string; active: boolean; primary: boolean };

// Perfil institucional de uma empresa ou de um estabelecimento (S06): valor efetivo, origem, rascunho e publicação.
export default function InstitutionalProfile({ owner, canUpdate, canPublish, sections, fonts, networks, units }: {
  owner: ProfileOwner;
  canUpdate: boolean;
  canPublish: boolean;
  sections: ProfileSection[];
  fonts: ProfileFont[];
  networks: ProfileNetwork[];
  units: Unit[];
}) {
  const { auth } = useApiPage<SessionProps>().props;
  const writable = auth.inspection_mode !== 'read-only' && owner.active;
  const [editing, setEditing] = useState<ProfileSection>();
  const [publishing, setPublishing] = useState(false);
  const form = useApiForm({});
  const establishment = owner.type === 'establishment';
  const pending = sections.filter((section) => section.pending && section.available);
  const base = ownerBase(owner);

  const changeMode = (section: ProfileSection, mode: 'inherit' | 'disabled') => {
    const body = new FormData();
    body.set('mode', mode);
    void form.submit('patch', `${base}/secoes/${section.key}`, { preserveScroll: true }, body);
  };

  const changeAddress = (show: boolean) => {
    const body = new FormData();
    body.set('show', show ? '1' : '0');
    void form.submit('patch', `${base}/endereco-publico`, { preserveScroll: true }, body);
  };

  return (
    <SaasLayout
      tenant
      title="Perfil institucional"
      breadcrumb={[{ label: 'Institucional' }, { label: 'Perfil institucional', href: '@/institucional' }, { label: owner.name }]}
      actions={writable && canPublish && (
        <span data-tour="institutional-publish">
          <Button tone="primary" labelIconClass="fa fa-cloud-upload" aria-haspopup="dialog" disabled={pending.length === 0}
            onClick={() => setPublishing(true)}>
            Publicar alterações{pending.length > 0 ? ` (${pending.length})` : ''}
          </Button>
        </span>
      )}
    >
      <CardBox>
        <div className="institutional__owner">
          <div>
            <h2 className="institutional__owner-name">{owner.name}</h2>
            <p className="institutional__note">
              {establishment ? `Estabelecimento de ${owner.company_name}` : 'Empresa'}
              {!owner.active && ' (inativo)'}
            </p>
          </div>
          <Label variant={establishment ? 'info' : 'primary'}>{establishment ? 'Estabelecimento' : 'Empresa'}</Label>
        </div>
        <p className="institutional__note">
          {establishment
            ? 'Cada seção pode herdar da empresa, ser personalizada aqui ou, quando permitido, desativada. A empresa define o padrão.'
            : 'A empresa define o padrão institucional. Os estabelecimentos herdam, personalizam ou desativam cada seção.'}
        </p>
      </CardBox>

      {form.errors._form && <p className="formField__error" role="alert">{form.errors._form}</p>}

      <div data-tour="institutional-sections">
        {sections.map((section) => (
          <CardBox key={section.key}>
            <section className="institutional__section" data-section={section.key} aria-labelledby={`section-${section.key}`}>
              <header className="institutional__section-header">
                <h3 id={`section-${section.key}`} className="institutional__section-title">
                  {section.label}{' '}
                  <InfoTooltip label={section.label.toLowerCase()} text={section.kind === 'operational'
                    ? 'Seção operacional: a alteração vale ao salvar e aparece nos sites publicados.'
                    : 'Seção editorial: a edição é rascunho e só aparece no público depois de publicada.'} />
                </h3>
                <div className="institutional__badges">
                  <Label variant={section.kind === 'operational' ? 'warning' : 'default'}>
                    {section.kind === 'operational' ? 'Operacional' : 'Editorial'}
                  </Label>
                  {section.pending && <Label variant="info">Rascunho não publicado</Label>}
                  {section.available && !section.pending && section.kind === 'editorial' && section.published.mode !== 'default'
                    && section.published.source !== 'platform' && <Label variant="success">Publicado</Label>}
                </div>
              </header>

              {!section.available ? (
                <p className="institutional__empty">{section.unavailable_reason}</p>
              ) : (
                <>
                  <p className="institutional__origin" data-tour="institutional-origin">{originText(section, owner)}</p>
                  <InstitutionalSectionSummary sectionKey={section.key} resolved={section.draft} fonts={fonts} networks={networks} />
                  {writable && canUpdate && (
                    <div className="institutional__actions">
                      <button type="button" className="contact-fields__add waves-effect waves-light" aria-haspopup="dialog"
                        aria-label={`Editar ${section.label.toLowerCase()}`} onClick={() => setEditing(section)}>
                        <span aria-hidden="true"><i className="fa fa-pencil" /></span>Editar
                      </button>
                      {establishment && section.draft.mode === 'override' && !section.establishment_only && (
                        <button type="button" className="contact-fields__add waves-effect waves-light" disabled={form.processing}
                          aria-label={`Voltar a herdar ${section.label.toLowerCase()}`} onClick={() => changeMode(section, 'inherit')}>
                          <span aria-hidden="true"><i className="fa fa-undo" /></span>Voltar a herdar
                        </button>
                      )}
                      {establishment && section.can_disable && section.draft.mode !== 'disabled' && (
                        <button type="button" className="contact-fields__add waves-effect waves-light" disabled={form.processing}
                          aria-label={`Desativar ${section.label.toLowerCase()}`} onClick={() => changeMode(section, 'disabled')}>
                          <span aria-hidden="true"><i className="fa fa-ban" /></span>Desativar
                        </button>
                      )}
                      {establishment && section.draft.mode === 'disabled' && (
                        <button type="button" className="contact-fields__add waves-effect waves-light" disabled={form.processing}
                          aria-label={`Reativar ${section.label.toLowerCase()}`} onClick={() => changeMode(section, 'inherit')}>
                          <span aria-hidden="true"><i className="fa fa-check" /></span>Reativar herdando
                        </button>
                      )}
                    </div>
                  )}
                </>
              )}
            </section>
          </CardBox>
        ))}
      </div>

      {establishment && (
        <CardBox>
          <section className="institutional__section" data-tour="institutional-address" aria-labelledby="section-address">
            <header className="institutional__section-header">
              <h3 id="section-address" className="institutional__section-title">
                Exibir endereço{' '}
                <InfoTooltip label="exibir endereço" text="O endereço é o do cadastro do estabelecimento e nunca é copiado. Por padrão ele não é público. A opção vale ao salvar." />
              </h3>
              <Label variant="warning">Operacional</Label>
            </header>
            <ChoiceControl type="checkbox" tone="primary" id="institutional-address" label="Exibir o endereço deste estabelecimento ao público"
              checked={owner.show_public_address} disabled={!writable || !canUpdate || form.processing}
              onChange={(checked) => changeAddress(checked)} />
            {!owner.has_address && (
              <p className="institutional__note">O estabelecimento ainda não tem cidade e estado no cadastro. Complete o endereço em Organização.</p>
            )}
            {form.errors.show && <p className="formField__error" role="alert">{form.errors.show}</p>}
          </section>
        </CardBox>
      )}

      {!establishment && (
        <CardBox>
          <section className="institutional__section" aria-labelledby="section-units">
            <h3 id="section-units" className="institutional__section-title">Estabelecimentos</h3>
            {units.length === 0 ? (
              <p className="institutional__empty">Nenhum estabelecimento que o seu acesso alcance.</p>
            ) : (
              <ul className="institutional__units">
                {units.map((unit) => (
                  <li key={unit.code}>
                    <Button href={`@/estabelecimento/${unit.code}/institucional`} tone="white" size="sm">
                      {unit.name}{unit.primary ? ' (principal)' : ''}{unit.active ? '' : ' (inativo)'}
                    </Button>
                  </li>
                ))}
              </ul>
            )}
          </section>
        </CardBox>
      )}

      {editing && <InstitutionalSectionModal owner={owner} section={editing} fonts={fonts} networks={networks}
        onClose={() => setEditing(undefined)} />}
      {publishing && <InstitutionalPublishModal owner={owner} sections={sections} onClose={() => setPublishing(false)} />}
    </SaasLayout>
  );
}
