import { useEffect, useId, useRef, useState } from 'react';
import FormField from '@/components/FormField/FormField';
import InputGroup from '@/components/InputGroup/InputGroup';
import TextInput from '@/components/TextInput/TextInput';
import { loadExternalScript } from '@/utils/loadExternalScript';
import { loadExternalStylesheet } from '@/utils/loadExternalStylesheet';
import './ColorPicker.css';
interface Picker {
  colorpicker: (options: Record<string, unknown> | string) => Picker;
  on: (event: string, callback: () => void) => Picker;
  off: (event: string) => Picker;
}
const hexColor = /^#[0-9a-f]{6}$/i;

/**
 * Seletor de cor hexadecimal do tema Bold. Aceita o campo vazio quando `required` é falso. O nome enviado ao servidor é
 * `name` (padrão `color`) e o valor é sempre o texto do campo, no formato `#rrggbb`.
 */
export default function ColorPicker({
  defaultValue,
  error,
  label = 'Cor',
  name = 'color',
  required = true,
  id: givenId,
}: {
  defaultValue: string;
  error?: string;
  label?: string;
  name?: string;
  required?: boolean;
  id?: string;
}) {
  const generatedId = useId();
  const id = givenId ?? generatedId;
  const root = useRef<HTMLDivElement>(null);
  const input = useRef<HTMLInputElement>(null);
  const instance = useRef<Picker | null>(null);
  const [color, setColor] = useState(defaultValue);
  const [loadError, setLoadError] = useState(false);
  useEffect(() => {
    let disposed = false;
    function positionPalette() {
      const node = root.current;
      const palette = node?.querySelector<HTMLElement>('.colorpicker-visible');
      if (!node || !palette) return;
      const boundary = Math.min(window.innerHeight, node.closest('.modalLayer__content')?.getBoundingClientRect().bottom ?? window.innerHeight);
      const rect = node.getBoundingClientRect();
      node.classList.toggle('color-picker--above', boundary - rect.bottom < palette.offsetHeight + 8 && rect.top > palette.offsetHeight + 8);
    }
    window.addEventListener('resize', positionPalette);
    async function initialize() {
      await loadExternalStylesheet(
        '/assets/plugins/mjolnic-bootstrap-colorpicker/css/bootstrap-colorpicker.min.css',
      );
      await loadExternalScript('/assets/js/jquery.min.js');
      await loadExternalScript('/assets/js/bootstrap.min.js');
      await loadExternalScript(
        '/assets/plugins/mjolnic-bootstrap-colorpicker/js/bootstrap-colorpicker.min.js',
      );
      if (disposed || !root.current) return;
      const $ = (
        window as unknown as { jQuery: (element: HTMLElement) => Picker }
      ).jQuery;
      instance.current = $(root.current);
      instance.current.colorpicker({
        format: 'hex',
        ...(hexColor.test(defaultValue) ? { color: defaultValue } : {}),
        container: root.current,
        component: '.color-picker__button',
      });
      const palette = root.current.querySelector('.colorpicker');
      palette?.setAttribute('role', 'dialog');
      palette?.setAttribute('aria-label', 'Selecionar cor');
      instance.current.on('showPicker.react', positionPalette);
      instance.current.on('changeColor.react', () =>
        setColor(input.current?.value ?? ''),
      );
    }
    void initialize().catch(() => {
      if (!disposed) setLoadError(true);
    });
    return () => {
      disposed = true;
      window.removeEventListener('resize', positionPalette);
      instance.current?.off('.react');
      instance.current?.colorpicker('destroy');
      instance.current = null;
    };
  }, [defaultValue]);
  return (
    <FormField
      id={id}
      label={label}
      layout="horizontal"
      error={error}
      requiredIndicator={required}
    >
      <div
        ref={root}
        className="color-picker"
        onKeyDown={(event) => {
          if (
            event.key === 'Escape' &&
            root.current?.querySelector('.colorpicker-visible')
          ) {
            event.preventDefault();
            event.stopPropagation();
            instance.current?.colorpicker('hide');
          }
        }}
      >
        <InputGroup
          action={
            <button
              type="button"
              className="inputGroup__button color-picker__button"
              aria-label={`Selecionar cor: ${label}`}
              title="Selecionar cor"
              aria-haspopup="dialog"
            >
              <i
                style={{
                  backgroundColor: hexColor.test(color)
                    ? color
                    : hexColor.test(defaultValue) ? defaultValue : 'transparent',
                }}
              />
            </button>
          }
        >
          <TextInput
            ref={input}
            id={id}
            name={name}
            defaultValue={defaultValue}
            maxLength={7}
            placeholder="#rrggbb"
            required={required}
            invalid={Boolean(error)}
            aria-describedby={error ? `${id}-error` : undefined}
            onChange={(event) => setColor(event.target.value)}
          />
        </InputGroup>
        {loadError && (
          <p role="status">
            O seletor não carregou. Informe a cor hexadecimal no campo.
          </p>
        )}
      </div>
    </FormField>
  );
}
