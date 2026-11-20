import type { CSSProperties } from 'react';
/** Shared readable colors for labels, selected chips and their options. */
export function tagColors(color: string): CSSProperties {
  const background = /^#[0-9a-f]{6}$/i.test(color) ? color : '#4c5667';
  const rgb = background
    .slice(1)
    .match(/../g)!
    .map((value) => {
      const channel = parseInt(value, 16) / 255;
      return channel <= 0.04045
        ? channel / 12.92
        : ((channel + 0.055) / 1.055) ** 2.4;
    });
  const light = 0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2] > 0.35;
  return { backgroundColor: background, color: light ? '#111' : '#fff' };
}
