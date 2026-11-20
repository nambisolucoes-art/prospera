import type {
  TourDemo,
  TourStep,
} from '@/components/GuidedTour/GuidedTour.types';
/** Share the preview between consecutive demo steps and close only the preview we opened. */
export function auditDetailTourSteps(technical: boolean): TourStep[] {
  let owned = false;
  let releaseTimer: number | undefined;
  const open = (): TourDemo => {
    window.clearTimeout(releaseTimer);
    if (!document.querySelector('.audit-record-modal')) {
      const button = document.querySelector<HTMLButtonElement>(
        '[data-tour="audit-list"] [data-tour="business-row-actions"] button',
      );
      if (button) {
        button.click();
        owned = true;
      }
    }
    return {
      note: owned
        ? 'Abrimos a primeira operação para você conhecer o detalhe. A janela fecha ao terminar esta parte do tutorial.'
        : undefined,
      undo: () => {
        releaseTimer = window.setTimeout(() => {
          if (owned)
            document
              .querySelector<HTMLButtonElement>(
                '[data-tour="audit-modal-footer"] button',
              )
              ?.click();
          owned = false;
        }, 80);
      },
    };
  };
  const make = (
    target: string,
    title: string,
    text: string | string[],
  ): TourStep => ({
    id: target,
    target: `[data-tour="${target}"]`,
    availableWith:
      '[data-tour="audit-list"] [data-tour="business-row-actions"] button',
    title,
    text,
    group: 'Detalhes da operação',
    onEnter: open,
  });
  return [
    make(
      'audit-modal-summary',
      'Resumo da operação',
      'O título resume o registro. Os marcadores identificam a ação e o tipo; ao lado aparece a data da operação.',
    ),
    make('audit-modal-content', 'Campos registrados', [
      'Nas alterações, compare Antes e Depois. Em cadastros, confira Valor registrado; em exclusões, Valor ao excluir.',
      'Nas listas, o sinal de mais indica os itens adicionados e o de menos, os removidos.',
    ]),
    ...(technical
      ? [
          {
            ...make('audit-modal-views', 'Formatado ou Log técnico', [
              'Formatado apresenta o registro para leitura. Log técnico ajuda o suporte a investigar a operação.',
              'As duas formas respeitam a proteção dos dados. Senhas, segredos e imagens continuam ocultos.',
            ]),
            onEnter: () => {
              const base = open();
              let switched = false;
              let observer: MutationObserver | undefined;
              const show = () => {
                const button = document.querySelector<HTMLButtonElement>(
                  '[data-audit-view="technical"]',
                );
                if (button && button.getAttribute('aria-selected') !== 'true') {
                  button.click();
                  switched = true;
                  observer?.disconnect();
                }
              };
              observer = new MutationObserver(show);
              observer.observe(document.body, {
                childList: true,
                subtree: true,
              });
              show();
              return {
                note: 'Mostramos o Log técnico. A aba Formatado volta ao sair deste passo.',
                undo: () => {
                  observer?.disconnect();
                  if (switched)
                    document
                      .querySelector<HTMLButtonElement>(
                        '[data-audit-view="plain"]',
                      )
                      ?.click();
                  base.undo?.();
                },
              };
            },
          },
        ]
      : []),
    make(
      'audit-modal-footer',
      'Fechar os detalhes',
      'Use Fechar para voltar à lista. Ao sair deste passo, a janela aberta pelo tutorial fecha automaticamente.',
    ),
  ];
}
