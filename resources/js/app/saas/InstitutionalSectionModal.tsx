import { useId, useState } from 'react';
import BusinessForm, { BusinessFormActions, BusinessFormSection } from '@/components/BusinessForm/BusinessForm';
import { ColorField, Field, Select } from '@/components/BusinessForm/BusinessFormFields';
import FormField from '@/components/FormField/FormField';
import FormHelp from '@/components/FormHelp/FormHelp';
import Modal from '@/components/Modal/Modal';
import TextArea from '@/components/TextArea/TextArea';
import InstitutionalHoursFields from './InstitutionalHoursFields';
import type { HoursValue, ProfileFont, ProfileNetwork, ProfileOwner, ProfileSection } from './InstitutionalProfile.types';
import { ownerBase } from './InstitutionalProfile.types';
import './Institutional.css';

type Errors = Record<string, string | undefined>;
type Value = Record<string, unknown>;

const str = (value: unknown) => (typeof value === 'string' ? value : '');
const lines = (value: unknown) => (Array.isArray(value) ? value.filter((item): item is string => typeof item === 'string') : []);

// Uma lista de textos, um por linha. Sem linhas, envia o campo vazio de propósito (limpar não é omitir).
function LinesField({ label, name, initial, errors, info, maxLength }: {
  label: string; name: string; initial: string[]; errors: Errors; info?: string; maxLength?: number;
}) {
  const id = useId();
  const [text, setText] = useState(initial.join('\n'));
  const items = text.split('\n').map((item) => item.trim()).filter(Boolean);
  const error = Object.entries(errors).find(([key]) => key === `value.${name}` || key.startsWith(`value.${name}.`))?.[1];

  return (
    <FormField id={id} label={label} info={info} error={error} layout="horizontal">
      <TextArea id={id} rows={3} value={text} maxLength={maxLength ? maxLength * 8 : undefined}
        onChange={(event) => setText(event.target.value)} />
      {items.length === 0
        ? <input type="hidden" name={`value[${name}]`} value="" />
        : items.map((item, index) => <input key={`${index}-${item}`} type="hidden" name={`value[${name}][]`} value={item} />)}
    </FormField>
  );
}

function TextAreaField({ label, name, initial, errors, rows = 3, maxLength }: {
  label: string; name: string; initial: string; errors: Errors; rows?: number; maxLength: number;
}) {
  const id = useId();

  return (
    <FormField id={id} label={label} error={errors[`value.${name}`]} layout="horizontal">
      <TextArea id={id} name={`value[${name}]`} rows={rows} defaultValue={initial} maxLength={maxLength} />
    </FormField>
  );
}

function Fields({ section, value, errors, fonts, networks }: {
  section: ProfileSection; value: Value; errors: Errors; fonts: ProfileFont[]; networks: ProfileNetwork[];
}) {
  switch (section.key) {
    case 'palette': {
      const colors: [string, string][] = [
        ['primary', 'Cor principal'], ['secondary', 'Cor secundária'], ['accent', 'Cor de destaque'],
        ['background', 'Fundo'], ['text', 'Texto'],
      ];
      return (
        <>
          <FormHelp className="business-form__control-start">
            O texto precisa ter contraste de pelo menos 4,5 para 1 com o fundo, e a cor principal de pelo menos 3 para 1.
          </FormHelp>
          {colors.map(([name, label]) => <ColorField key={name} label={label} name={`value[${name}]`} defaultValue={str(value[name])} error={errors[`value.${name}`]} />)}
        </>
      );
    }
    case 'typography':
      return (
        <>
          {[['heading', 'Fonte dos títulos'], ['body', 'Fonte do texto']].map(([name, label]) => (
            <Select key={name} label={label} name={`value[${name}]`} defaultValue={str(value[name])}
              error={errors[`value.${name}`]} options={fonts.map((font) => ({ value: font.key, label: font.label }))} />
          ))}
        </>
      );
    case 'about':
      return (
        <>
          <Field label="Título" name="value[title]" defaultValue={str(value.title)} maxLength={100} error={errors['value.title']} />
          <TextAreaField label="Descrição" name="description" initial={str(value.description)} errors={errors} rows={5} maxLength={2000} />
          <TextAreaField label="Missão" name="mission" initial={str(value.mission)} errors={errors} maxLength={500} />
          <LinesField label="Valores" name="values" initial={lines(value.values)} errors={errors} info="Um por linha, até 8." maxLength={120} />
          <LinesField label="Diferenciais" name="differentials" initial={lines(value.differentials)} errors={errors}
            info="Um por linha, até 8." maxLength={120} />
          <FormHelp className="business-form__control-start">Texto simples: tags e HTML são removidos ao salvar.</FormHelp>
        </>
      );
    case 'contacts':
      return (
        <>
          <FormHelp className="business-form__control-start">
            Contatos para divulgação, digitados aqui. Eles nunca vêm do cadastro de Pessoas, do colaborador nem dos
            contatos operacionais do estabelecimento. Deixar tudo vazio é uma escolha válida e não herda da empresa.
          </FormHelp>
          <LinesField label="Telefones" name="phones" initial={lines(value.phones)} errors={errors} info="Um por linha, com DDD." />
          <LinesField label="WhatsApp" name="whatsapps" initial={lines(value.whatsapps)} errors={errors} info="Um por linha, com DDD." />
          <LinesField label="E-mails" name="emails" initial={lines(value.emails)} errors={errors} info="Um por linha." />
        </>
      );
    case 'social_links': {
      const items = (Array.isArray(value.items) ? value.items : []) as { network: string; url: string }[];

      return (
        <>
          <FormHelp className="business-form__control-start">O link deve começar com https:// e ser do domínio da própria rede.</FormHelp>
          {networks.map((network, index) => (
            <div key={network.key}>
              <input type="hidden" name={`value[items][${index}][network]`} value={network.key} />
              <Field label={network.label} name={`value[items][${index}][url]`} type="url" placeholder="https://"
                defaultValue={items.find((item) => item.network === network.key)?.url ?? ''}
                error={errors[`value.items.${index}.url`] ?? errors[`value.items.${index}.network`]} />
            </div>
          ))}
        </>
      );
    }
    case 'hours_weekly':
      return <InstitutionalHoursFields value={value as unknown as HoursValue} errors={errors} />;
    case 'seo_defaults':
      return (
        <>
          <Field label="Título" name="value[title]" defaultValue={str(value.title)} maxLength={70} error={errors['value.title']}
            info="Até 70 caracteres." />
          <TextAreaField label="Descrição" name="description" initial={str(value.description)} errors={errors} maxLength={160} />
        </>
      );
    default:
      return null;
  }
}

// Edição de uma seção do perfil: modo (herdar, personalizar ou desativar) e, ao personalizar, o conteúdo.
export default function InstitutionalSectionModal({ owner, section, fonts, networks, onClose }: {
  owner: ProfileOwner; section: ProfileSection; fonts: ProfileFont[]; networks: ProfileNetwork[]; onClose: () => void;
}) {
  const establishment = owner.type === 'establishment';
  const initialMode = section.draft.mode === 'inherit' || section.draft.mode === 'disabled' ? section.draft.mode : 'override';
  const [mode, setMode] = useState(establishment ? initialMode : 'override');
  // Ao personalizar a partir de uma seção herdada, o ponto de partida é o valor herdado.
  const start = (section.draft.value ?? section.empty_value) as Value;
  const modes = [
    ...(section.establishment_only ? [] : [{ value: 'inherit', label: 'Herdar da empresa' }]),
    { value: 'override', label: 'Personalizar neste estabelecimento' },
    ...(section.can_disable ? [{ value: 'disabled', label: 'Desativar neste estabelecimento' }] : []),
  ];

  return (
    <Modal open bootstrap manageFocus size="large" title={`Editar ${section.label.toLowerCase()}`} onClose={onClose}>
      <BusinessForm className="business-form--wide-labels" action={`${ownerBase(owner)}/secoes/${section.key}`} method="patch"
        options={{ preserveScroll: true }} onSuccess={onClose}>
        {({ errors, processing }) => (
          <>
            <BusinessFormSection title={section.label}>
              <p className="institutional__note">
                {section.kind === 'operational'
                  ? 'Esta alteração vale ao salvar e já aparece nos sites publicados.'
                  : 'Esta alteração fica como rascunho até a publicação explícita.'}
              </p>
              {establishment ? (
                <Select label="Origem do conteúdo" name="mode" value={mode} onChange={(value) => setMode(value as typeof mode)}
                  error={errors.mode} options={modes} />
              ) : <input type="hidden" name="mode" value="override" />}
              {errors.section && <p className="formField__error" role="alert">{errors.section}</p>}
              {mode === 'override' && (
                <Fields section={section} value={start} errors={errors} fonts={fonts} networks={networks} />
              )}
              {mode === 'inherit' && (
                <FormHelp className="business-form__control-start">
                  O conteúdo da empresa passa a valer aqui e acompanha as mudanças dela
                  {section.kind === 'editorial' ? ', depois de publicado' : ''}.
                </FormHelp>
              )}
              {mode === 'disabled' && (
                <FormHelp className="business-form__control-start">A seção não aparece neste estabelecimento.</FormHelp>
              )}
            </BusinessFormSection>
            <BusinessFormActions inline processing={processing} onCancel={onClose} />
          </>
        )}
      </BusinessForm>
    </Modal>
  );
}
