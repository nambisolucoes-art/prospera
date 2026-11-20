import type { TourStep } from '@/components/GuidedTour/GuidedTour.types';
export function pageHeaderTourSteps(title: string): TourStep[] {
  return [
    {
      id: 'page-title',
      target: '[data-tour="page-title"]',
      group: 'Conheça a tela',
      title,
      text: 'Este tutorial apresenta os controles disponíveis e demonstra como usá-los sem gravar alterações.',
    },
    {
      id: 'page-breadcrumb',
      target: '[data-tour="page-breadcrumb"]',
      group: 'Conheça a tela',
      title: 'Onde você está',
      text: 'A trilha mostra a sua localização no sistema. Use os links para retornar às telas anteriores.',
    },
    {
      id: 'page-actions',
      target: '[data-tour="page-actions"]',
      group: 'Ações da tela',
      title: 'Cadastrar e outras ações',
      text: 'Use o botão de cadastro para incluir um registro. Os botões disponíveis dependem das suas permissões.',
    },
  ];
}
