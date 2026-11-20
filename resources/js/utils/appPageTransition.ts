export const appPageTransitionStartEvent = 'bold:page-transition-start';
export const appPageTransitionReadyEvent = 'bold:page-transition-ready';
// Prévia temporária: false restaura a exibição somente quando a navegação demora.
export const appLoaderPreviewEnabled = false;
export type AppPageTransition = { id: number };
let nextTransitionId = 0;

export function startAppPageTransition(): number {
  const id = ++nextTransitionId;
  window.dispatchEvent(new CustomEvent<AppPageTransition>(appPageTransitionStartEvent, { detail: { id } }));
  return id;
}

export function markAppPageTransitionReady(id: number | undefined): void {
  if (id === undefined) return;
  window.dispatchEvent(new CustomEvent<AppPageTransition>(appPageTransitionReadyEvent, { detail: { id } }));
}
