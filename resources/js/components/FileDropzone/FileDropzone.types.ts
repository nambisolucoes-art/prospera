export interface FileDropzoneProps {
  /** Endpoint accepting multipart/form-data with the field `file`. */
  uploadUrl?: string;
  maxFileSizeMb?: number;
  /** Campos extras enviados junto com o arquivo (por exemplo, o uso da imagem). */
  params?: Record<string, string>;
  /** Falso para enviar só quando `submit()` for chamado. Padrão: envia ao soltar o arquivo. */
  autoProcess?: boolean;
  /** Remove da fila o arquivo recusado, para escolher outro. Padrão: mantém, como antes. */
  removeOnError?: boolean;
  /** Quantidade máxima de arquivos na fila. */
  maxFiles?: number;
  acceptedFiles?: string;
  onFilesChange?: (files: File[]) => void;
  onSuccess?: (file: File, response: unknown) => void;
  onError?: (file: File, message: string) => void;
}
export interface FileDropzoneHandle { submit: () => void; }
export interface DropzoneInstance {
  files: File[];
  on: (event: string, callback: (...args: unknown[]) => void) => void;
  processQueue: () => void;
  removeFile: (file: File) => void;
  destroy: () => void;
}
export interface DropzoneConstructor { new(element: HTMLElement, options: Record<string, unknown>): DropzoneInstance; }
