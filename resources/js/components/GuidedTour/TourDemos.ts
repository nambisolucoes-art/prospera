import type { TourDemo } from './GuidedTour.types';
/** Open only for the demonstration, preserving a disclosure that was already open. */
export function openDisclosure(target: HTMLElement | null): TourDemo {
  const button = target?.matches('[aria-expanded]')
    ? target
    : target?.querySelector<HTMLElement>('[aria-expanded]');
  if (!button || button.getAttribute('aria-expanded') === 'true') return {};
  button.click();
  return {
    note: 'Abrimos as opções para você ver. Elas voltam ao estado anterior ao sair deste passo.',
    undo: () => {
      button.dispatchEvent(
        new CustomEvent('prospera:tour-disclosure', {
          bubbles: true,
          detail: { open: false },
        }),
      );
    },
  };
}
