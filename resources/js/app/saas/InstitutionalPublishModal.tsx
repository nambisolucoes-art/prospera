import { useEffect, useState } from 'react';
import BusinessForm, { BusinessFormActions, BusinessFormSection } from '@/components/BusinessForm/BusinessForm';
import FormHelp from '@/components/FormHelp/FormHelp';
import ChoiceControl from '@/components/ChoiceControl/ChoiceControl';
import Modal from '@/components/Modal/Modal';
import { useContextUrl } from '@/hooks/useContextUrl';
import { apiRequest } from '@/utils/apiRequest';
import type { ProfileImpact, ProfileOwner, ProfileSection } from './InstitutionalProfile.types';
import { ownerBase } from './InstitutionalProfile.types';
import './Institutional.css';

// Publicação de seções editoriais com o resumo de impacto, sempre recalculado pelo servidor.
export default function InstitutionalPublishModal({ owner, sections, onClose }: {
  owner: ProfileOwner; sections: ProfileSection[]; onClose: () => void;
}) {
  const contextUrl = useContextUrl();
  const pending = sections.filter((section) => section.kind === 'editorial' && section.available && section.pending);
  const [selected, setSelected] = useState<string[]>(pending.map((section) => section.key));
  const [impact, setImpact] = useState<ProfileImpact>();
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    if (selected.length === 0) {
      setImpact(undefined);
      return;
    }

    const controller = new AbortController();
    setFailed(false);
    const query = selected.map((key) => `secoes[]=${encodeURIComponent(key)}`).join('&');
    apiRequest<ProfileImpact>(contextUrl(`${ownerBase(owner)}/impacto?${query}`), { signal: controller.signal })
      .then(setImpact)
      .catch(() => { if (!controller.signal.aborted) setFailed(true); });

    return () => controller.abort();
  }, [selected, owner, contextUrl]);

  const toggle = (key: string, checked: boolean) =>
    setSelected((current) => (checked ? [...current, key] : current.filter((item) => item !== key)));

  return (
    <Modal open bootstrap manageFocus size="large" title="Publicar alterações" onClose={onClose}>
      <BusinessForm className="business-form--wide-labels" action={`${ownerBase(owner)}/publicar`} method="post"
        options={{ preserveScroll: true }} onSuccess={onClose}>
        {({ errors, processing }) => (
          <>
            <BusinessFormSection title={`Publicar em ${owner.name}`}>
              {pending.length === 0 ? (
                <p className="institutional__empty">Não há alterações de rascunho para publicar.</p>
              ) : (
                <div className="institutional__publish-list" data-tour="institutional-publish-list">
                  {pending.map((section) => (
                    <ChoiceControl key={section.key} type="checkbox" tone="primary" id={`publish-${section.key}`}
                      label={section.label} checked={selected.includes(section.key)}
                      onChange={(checked) => toggle(section.key, checked)} />
                  ))}
                </div>
              )}
              {selected.map((key) => <input key={key} type="hidden" name="sections[]" value={key} />)}
              <input type="hidden" name="inheriting" value={impact?.inheriting ?? 0} />
              <input type="hidden" name="overriding" value={impact?.overriding ?? 0} />
              {errors.sections && <p className="formField__error" role="alert">{errors.sections}</p>}
              {errors.impact && <p className="formField__error" role="alert">{errors.impact}</p>}
              {failed && <p className="formField__error" role="alert">Não foi possível calcular o impacto.</p>}
              {impact && (
                <div className="institutional__impact" role="status">
                  <strong>Resumo do impacto</strong>
                  {impact.changed.length === 0 ? (
                    <p>Nada a publicar: as seções escolhidas já estão publicadas.</p>
                  ) : owner.type === 'company' ? (
                    <ul>
                      <li>{impact.changed.length} seção(ões) com alterações serão publicadas.</li>
                      <li>{impact.inheriting} estabelecimento(s) ativo(s) herdam e passam a mostrar o novo conteúdo publicado.</li>
                      <li>{impact.overriding} estabelecimento(s) com conteúdo próprio ou desativado não são afetados.</li>
                    </ul>
                  ) : (
                    <ul>
                      <li>{impact.changed.length} seção(ões) com alterações serão publicadas somente neste estabelecimento.</li>
                    </ul>
                  )}
                  <FormHelp>Os sites ainda não existem nesta etapa: a publicação fica registrada e valerá quando houver sites.</FormHelp>
                </div>
              )}
            </BusinessFormSection>
            <BusinessFormActions inline processing={processing} submit="Publicar" onCancel={onClose}
              submitDisabled={selected.length === 0 || !impact || impact.changed.length === 0} />
          </>
        )}
      </BusinessForm>
    </Modal>
  );
}
