import { useRef, useState } from 'react';
import '@/components/BusinessForm/BusinessForm.css';
import { Field, Select } from '@/components/BusinessForm/BusinessFormFields';
import Button from '@/components/Button/Button';
import FileDropzone from '@/components/FileDropzone/FileDropzone';
import type { FileDropzoneHandle } from '@/components/FileDropzone/FileDropzone.types';
import FormHelp from '@/components/FormHelp/FormHelp';
import Modal from '@/components/Modal/Modal';
import { useApiPage } from '@/hooks/useApiPage';
import { useContextUrl } from '@/hooks/useContextUrl';
import { mediaBase, type MediaLimits, type MediaOwner, type MediaPurposeOption } from './Media.types';
import './Media.css';

// Envio de uma imagem. O processamento é síncrono: ao terminar o envio a imagem já está otimizada e pronta, sem estados
// intermediários. O progresso é o do próprio envio do arquivo.
export default function MediaUploadModal({ owner, purposes, limits, onClose }: {
  owner: MediaOwner; purposes: MediaPurposeOption[]; limits: MediaLimits; onClose: () => void;
}) {
  const contextUrl = useContextUrl();
  const { navigate, url } = useApiPage();
  const dropzone = useRef<FileDropzoneHandle>(null);
  const [purpose, setPurpose] = useState(purposes[0]?.value ?? 'gallery');
  const [altText, setAltText] = useState('');
  const [license, setLicense] = useState('');
  const [hasFile, setHasFile] = useState(false);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<string>();

  return (
    <Modal open bootstrap manageFocus size="large" title="Enviar imagem" onClose={onClose}>
      <div className="media__upload business-form">
        <p className="institutional__note">
          Tipos aceitos: {limits.types.join(', ')}. Até {limits.max_mb} MB e {limits.max_megapixels} megapixels. A imagem é otimizada
          ao enviar e fica na biblioteca de {owner.name}; enviar não publica nada.
        </p>
        <Select label="Uso" name="purpose" value={purpose} onChange={setPurpose} options={purposes} />
        <Field label="Texto alternativo" value={altText} maxLength={300} onChange={(event) => setAltText(event.target.value)}
          info="Descreve a imagem para quem não a enxerga. Será obrigatório quando a imagem for usada como conteúdo de página." />
        <Field label="Licença ou crédito" value={license} maxLength={300} onChange={(event) => setLicense(event.target.value)} />
        <div data-tour="media-dropzone">
          <FileDropzone ref={dropzone} uploadUrl={contextUrl(mediaBase(owner))} maxFileSizeMb={limits.max_mb} maxFiles={1}
            acceptedFiles="image/jpeg,image/png,image/webp" autoProcess={false} removeOnError
            params={{ purpose, alt_text: altText, license_note: license }}
            onFilesChange={(files) => { setHasFile(files.length > 0); if (files.length > 0) setError(undefined); }}
            onSuccess={() => { setSending(false); onClose(); void navigate(url, { preserveScroll: true }); }}
            onError={(_file, message) => { setSending(false); setError(message); }} />
        </div>
        {error && <p className="formField__error" role="alert">{error}</p>}
        <FormHelp>O texto alternativo e a licença podem ser editados depois, em Detalhes.</FormHelp>
        <div className="business-form__actions-inline business-form__control-start">
          <Button tone="white" onClick={onClose}>Cancelar</Button>
          <Button tone="success" disabled={!hasFile || sending} onClick={() => { setSending(true); setError(undefined); dropzone.current?.submit(); }}>Salvar</Button>
        </div>
      </div>
    </Modal>
  );
}
