export type ErrorCode = '400' | '403' | '404' | '419' | '429' | '500' | '503';
export interface ErrorDescription { title: string; paragraphs: string[]; }
export interface ErrorAction { label: string; href?: string; onClick?: () => void; }
