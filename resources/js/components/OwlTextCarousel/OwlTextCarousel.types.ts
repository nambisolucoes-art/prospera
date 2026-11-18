import type { ButtonTone } from '@/components/Button/Button';

export interface OwlTextCarouselSlide {
  buttonTone: ButtonTone;
  date: string;
  text: string;
  title: string;
  titleTone: 'custom' | 'primary' | 'pink' | 'white';
}
