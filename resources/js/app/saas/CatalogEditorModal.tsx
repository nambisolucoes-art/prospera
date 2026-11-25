import { useEffect, useId, useRef } from 'react';
import BusinessForm, { BusinessFormActions, BusinessFormSection } from '@/components/BusinessForm/BusinessForm';
import { Field, Select } from '@/components/BusinessForm/BusinessFormFields';
import Button from '@/components/Button/Button';
import ChoiceControl from '@/components/ChoiceControl/ChoiceControl';
import FormField from '@/components/FormField/FormField';
import Modal from '@/components/Modal/Modal';
import { useApiPage } from '@/hooks/useApiPage';
import type { SessionProps } from '@/types/Session.types';
import { article, catalogEditorCloseDemo, type CatalogConfig, type CatalogRow, type CompanyOption } from './CompanyCatalog.types';

export default function CatalogEditorModal({ config, row, creatable, onClose }: {
  config: CatalogConfig;
  row: CatalogRow | null;
  creatable: CompanyOption[];
  onClose: (restoreFocus?: boolean) => void;
}) {
  const { auth } = useApiPage<SessionProps>().props;
  const root = useRef<HTMLDivElement>(null);
  const companiesId = useId();
  useEffect(() => {
    const node = root.current;
    const close = () => onClose(false);
    node?.addEventListener(catalogEditorCloseDemo, close);
    return () => node?.removeEventListener(catalogEditorCloseDemo, close);
  }, [onClose]);

  const can = (action: string) => auth.permissions.includes(`account.${config.module}.${action}`);
  const readOnly = auth.inspection_mode === 'read-only';
  const active = config.masculine ? 'Ativo' : 'Ativa';
  const inactive = config.masculine ? 'Inativo' : 'Inativa';
  // A situação só oferece o que a pessoa pode aplicar; a situação atual sempre aparece.
  const situation = [
    ...(row?.active === true || can('activate') || (!row && can('create')) ? [{ value: '1', label: active }] : []),
    ...(row?.active === false || can('deactivate') ? [{ value: '0', label: inactive }] : []),
  ];

  return (
    <Modal
      open
      bootstrap
      manageFocus
      size="large"
      title={row ? `Editar ${config.noun}` : `Cadastrar ${config.noun}`}
      onClose={() => onClose(true)}
    >
      <div ref={root} data-tour={`${config.tour}-editor`}>
        <BusinessForm
          className="business-form--wide-labels"
          action={row ? `@/${config.segment}/${row.id}` : `@/${config.segment}`}
          method={row ? 'patch' : 'post'}
          options={{ preserveScroll: true }}
          onSuccess={() => onClose(true)}
        >
          {({ errors, processing }) => (
            <>
              <fieldset disabled={readOnly} style={{ border: 0, padding: 0, margin: 0, minWidth: 0 }}>
                <BusinessFormSection title={`Dados d${article(config)} ${config.noun}`} tourTarget={`${config.tour}-fields`}>
                  {row ? (
                    <Field label="Empresa" value={row.company} readOnly
                      info="A empresa de um registro não muda. Para usar o mesmo nome em outra empresa, cadastre novamente." />
                  ) : creatable.length > 1 && (
                    <FormField id={companiesId} label="Empresas" requiredIndicator passiveLabel layout="horizontal" error={errors.companies}
                      info={`Escolha em quais empresas ${config.masculine ? 'o' : 'a'} ${config.noun} vale. O cadastro cria um registro em cada empresa escolhida.`}>
                      <div data-tour={`${config.tour}-companies`}>
                        {creatable.map((company) => (
                          <ChoiceControl key={company.code} type="checkbox" tone="primary" id={`${config.tour}-company-${company.code}`}
                            name="companies[]" value={company.code} label={company.name} />
                        ))}
                      </div>
                    </FormField>
                  )}
                  <Field label="Nome" name="name" defaultValue={row?.name} error={errors.name} maxLength={100} required />
                  <Select
                    label="Situação"
                    info={`${config.masculine ? 'Departamentos inativos' : 'Funções inativas'} permanecem no histórico, mas não podem ser escolhid${config.masculine ? 'o' : 'a'}s em novos cadastros.`}
                    name="active"
                    required
                    defaultValue={row?.active === false ? '0' : '1'}
                    options={situation}
                    error={errors.active}
                  />
                </BusinessFormSection>
              </fieldset>
              <div data-tour={`${config.tour}-modal-actions`}>
                {readOnly ? (
                  <Button tone="white" onClick={() => onClose(true)}>Fechar</Button>
                ) : (
                  <BusinessFormActions inline processing={processing} onCancel={() => onClose(true)} />
                )}
              </div>
            </>
          )}
        </BusinessForm>
      </div>
    </Modal>
  );
}
