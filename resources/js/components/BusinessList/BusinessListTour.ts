import type { TourStep } from '@/components/GuidedTour/GuidedTour.types';
import { openDisclosure } from '@/components/GuidedTour/TourDemos';
const at = (
  target: string,
  title: string,
  text: string | string[],
  group: string,
): TourStep => ({
  id: target,
  target: `[data-tour="${target}"]`,
  title,
  text,
  group,
});
/** Mantém a linha de redimensionamento visível enquanto o passo do tour está ativo. */
function showResizeLine(target: HTMLElement | null | undefined): () => void {
  target?.setAttribute('data-tour-focus', 'true');

  return () => target?.removeAttribute('data-tour-focus');
}

/** Shared by every business list. Demonstrations never navigate or save preferences. */
export function businessListTourSteps({
  filters,
  table,
}: { filters?: string; table?: string } = {}): TourStep[] {
  return [
    at(
      'business-filters',
      'Buscar e filtrar',
      filters ??
        'Digite sua busca ou selecione os filtros. A lista se atualiza automaticamente; a lupa à direita também executa a pesquisa.',
      'Filtros',
    ),
    at(
      'business-columns',
      'Colunas',
      [
        'Escolha quais colunas aparecem na tabela. Algumas, como Ações, ficam sempre visíveis.',
        'No próximo passo, vamos abrir as opções.',
      ],
      'Tabela',
    ),
    {
      ...at(
        'business-columns',
        'Escolher colunas',
        [
          'Marque para mostrar uma coluna e desmarque para ocultar.',
          'Suas preferências ficam salvas. Esta demonstração apenas abre o menu.',
        ],
        'Tabela',
      ),
      id: 'business-columns-demo',
      target: [
        '[data-tour="business-columns"]',
        '[data-tour="business-columns-options"]',
      ],
      onEnter: openDisclosure,
    },
    at(
      'business-table',
      'Consultar registros',
      table ??
        'Cada linha representa um registro. Os títulos identificam os dados de cada coluna.',
      'Tabela',
    ),
    {
      ...at(
        'business-resize',
        'Largura das colunas',
        [
          'Arraste a linha entre os títulos para ajustar a largura de duas colunas.',
          'A primeira e a última coluna têm largura fixa. A coluna Ações fica sempre no final da tabela.',
          'Pelo teclado, selecione a linha e use as setas. No próximo passo, vamos demonstrar.',
        ],
        'Tabela',
      ),
      // A linha só aparece ao passar o mouse; no tour ela fica à vista enquanto o passo está ativo.
      onEnter: (target) => ({ undo: showResizeLine(target) }),
    },
    {
      ...at(
        'business-resize',
        'Veja o redimensionamento',
        'A largura muda aos poucos. Ao sair deste passo, a tabela volta exatamente ao tamanho anterior.',
        'Tabela',
      ),
      id: 'business-resize-demo',
      onEnter: (target) => {
        const hideLine = showResizeLine(target);
        target?.dispatchEvent(
          new CustomEvent('business-list:resize-demo', {
            bubbles: true,
            detail: { active: true },
          }),
        );
        return {
          note: 'A demonstração é temporária e não altera suas preferências salvas.',
          undo: () => {
            hideLine();
            target?.dispatchEvent(
              new CustomEvent('business-list:resize-demo', {
                bubbles: true,
                detail: { active: false },
              }),
            );
          },
        };
      },
    },
    {
      ...at(
        'business-row-actions',
        'Ações de cada registro',
        'Na última coluna, use os botões para visualizar ou editar o registro. As ações disponíveis dependem das suas permissões.',
        'Tabela',
      ),
      availableWith:
        '[data-tour="business-row-actions"] button, [data-tour="business-row-actions"] a',
    },
    at(
      'business-page-size',
      'Registros por página',
      'Escolha a quantidade de registros exibida em cada página. O tutorial não muda a seleção.',
      'Páginas',
    ),
    {
      ...at(
        'business-page-size',
        'Opções de quantidade',
        'Estas são as quantidades disponíveis. Ao sair, fechamos as opções sem alterar a sua escolha.',
        'Páginas',
      ),
      id: 'business-page-size-demo',
      target: [
        '[data-tour="business-page-size"]',
        '[data-tour="business-page-size-options"]',
      ],
      onEnter: openDisclosure,
    },
    at(
      'business-pagination',
      'Paginação',
      'Use as setas para abrir a primeira, anterior, próxima ou última página. As setas indisponíveis ficam desativadas.',
      'Páginas',
    ),
  ];
}
