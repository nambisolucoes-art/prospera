import type { TourDemo, TourStep } from '@/components/GuidedTour/GuidedTour.types';
import { article, catalogEditorCloseDemo, type CatalogConfig } from './CompanyCatalog.types';

/** Passos da janela de cadastro de departamentos e funções. O tour pertence à tela, nunca ao modal. */
export function catalogFormTourSteps(config: CatalogConfig): TourStep[] {
  const the = article(config);
  const inactive = config.masculine ? 'inativos' : 'inativas';

  return [
    {
      id: `${config.tour}-companies`,
      target: `[data-tour="${config.tour}-companies"]`,
      title: 'Empresas',
      text: [
        `Escolha em quais empresas ${the} ${config.noun} vale. O cadastro cria um registro em cada empresa marcada, e cada empresa administra o seu.`,
        'Só aparecem as empresas em que o seu cargo permite cadastrar. Com uma única empresa, a escolha não é pedida.',
      ],
      group: `Cadastro d${the} ${config.noun}`,
    },
    {
      id: `${config.tour}-fields`,
      target: `[data-tour="${config.tour}-fields"]`,
      title: `Dados d${the} ${config.noun}`,
      text: [
        `Informe o nome. Ele é único na empresa, sem diferenciar maiúsculas, minúsculas nem acentos.`,
        `A situação define se ${the} ${config.noun} pode ser escolhid${the} em novos cadastros. Registros ${inactive} permanecem no histórico. A situação só oferece as opções que o seu cargo permite aplicar.`,
      ],
      group: `Cadastro d${the} ${config.noun}`,
    },
    {
      id: `${config.tour}-modal-actions`,
      target: `[data-tour="${config.tour}-modal-actions"]`,
      title: 'Salvar ou cancelar',
      text: 'Salvar valida os dados e atualiza a lista. Cancelar fecha a janela sem gravar. O tutorial não salva dados.',
      group: `Cadastro d${the} ${config.noun}`,
    },
  ];
}

/** Abre a janela de cadastro pelo botão Cadastrar e fecha somente a janela que a demonstração abriu. */
export function catalogListFormTourSteps(config: CatalogConfig): TourStep[] {
  let owned = false;
  let timer: number | undefined;
  const open = (): TourDemo => {
    window.clearTimeout(timer);
    if (!document.querySelector(`[data-tour="${config.tour}-editor"]`)) {
      const button = document.querySelector<HTMLButtonElement>('[data-tour="page-actions"] button');
      if (button) {
        button.click();
        owned = true;
      }
    }
    return {
      note: owned
        ? 'Abrimos o cadastro para você conhecer os campos. A janela fecha ao terminar esta parte do tutorial.'
        : undefined,
      undo: () => {
        timer = window.setTimeout(() => {
          if (owned) {
            document
              .querySelector(`[data-tour="${config.tour}-editor"]`)
              ?.dispatchEvent(new Event(catalogEditorCloseDemo));
          }
          owned = false;
        }, 80);
      },
    };
  };

  return catalogFormTourSteps(config).map((step) => ({
    ...step,
    availableWith: '[data-tour="page-actions"] button',
    onEnter: open,
  }));
}
