export type TourDemo = { undo?: () => void; note?: string };
export type TourStep = {
  id?: string;
  target: string | string[];
  availableWith?: string;
  title: string;
  text: string | string[];
  group?: string;
  onEnter?: (target: HTMLElement | null) => TourDemo | void;
};
export type TourDefinition = {
  id: string;
  title: string;
  steps: TourStep[];
  help?: string;
};
