import type {
  TourStep,
  TourDemo,
} from '@/components/GuidedTour/GuidedTour.types';
export function tagFormTourSteps(): TourStep[] {
  return [
    {
      id: 'tag-fields',
      target: '[data-tour="tag-fields"]',
      title: 'Dados da etiqueta',
      text: [
        'Informe nome, módulo e situação (Ativa ou Inativa). Etiquetas inativas mantêm os vínculos existentes e deixam de aparecer para novas seleções. A cor cadastrada será usada nos marcadores e seletores de pessoas.',
        'Digite o hexadecimal ou clique na amostra para abrir o seletor de cor.',
      ],
      group: 'Cadastro de etiqueta',
    },
    {
      id: 'tag-modal-actions',
      target: '[data-tour="tag-modal-actions"]',
      title: 'Salvar ou cancelar',
      text: 'Salvar valida os dados e atualiza a lista. Cancelar fecha a janela sem gravar. O tutorial não salva dados.',
      group: 'Cadastro de etiqueta',
    },
  ];
}
export function tagListFormTourSteps(): TourStep[] {
  let owned = false;
  let timer: number | undefined;
  const open = (): TourDemo => {
    window.clearTimeout(timer);
    if (!document.querySelector('[data-tour="tag-editor"]')) {
      const button = document.querySelector<HTMLButtonElement>(
        '[data-tour="page-actions"] button',
      );
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
          if (owned)
            document
              .querySelector('[data-tour="tag-editor"]')
              ?.dispatchEvent(new Event('tag-editor:close-demo'));
          owned = false;
        }, 80);
      },
    };
  };
  return tagFormTourSteps().map((step) => ({
    ...step,
    availableWith: '[data-tour="page-actions"] button',
    onEnter: open,
  }));
}
