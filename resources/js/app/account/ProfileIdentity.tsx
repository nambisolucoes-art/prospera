import { useApiForm } from '@/hooks/useApiForm';
import { useApiPage as usePage } from '@/hooks/useApiPage';
import { useRef, useState } from 'react';
import Button from '@/components/Button/Button';
import ProfileAvatar from '@/components/ProfileAvatar/ProfileAvatar';
import type { SessionProps } from '@/types/Session.types';

const acceptedTypes = ['image/jpeg', 'image/png', 'image/webp'];
const maximumUploadSize = 5 * 1024 * 1024;

async function preparePhoto(file: File): Promise<File> {
  try {
    const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    const canvas = document.createElement('canvas');
    const crop = Math.min(bitmap.width, bitmap.height);
    const size = Math.min(crop, 320);
    canvas.width = size;
    canvas.height = size;

    try {
      const context = canvas.getContext('2d');
      if (!context) return file;
      context.imageSmoothingQuality = 'high';
      context.drawImage(bitmap, (bitmap.width - crop) / 2, (bitmap.height - crop) / 2, crop, crop, 0, 0, size, size);
    } finally {
      bitmap.close();
    }

    const resized = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/png'));
    return resized ? new File([resized], 'perfil.png', { type: resized.type }) : file;
  } catch {
    return file;
  }
}

export default function ProfileIdentity({ editable }: { editable: boolean }) {
  const uploadForm = useApiForm({});
  const { auth } = usePage<SessionProps>().props;
  const fileInput = useRef<HTMLInputElement>(null);
  const [error, setError] = useState('');
  const [uploading, setUploading] = useState(false);
  const user = auth.user;

  if (!user) return null;

  async function upload(file: File) {
    setError('');
    if (file.type && !acceptedTypes.includes(file.type)) {
      setError('Use uma foto JPG, PNG ou WebP.');
      return;
    }
    if (file.size > maximumUploadSize) {
      setError('A foto deve ter até 5 MB.');
      return;
    }

    setUploading(true);
    const photo = await preparePhoto(file);
    const body = new FormData();
    body.set('photo', photo);
    await uploadForm.submit('post', '/settings/profile/photo', {
      preserveScroll: true,
      onError: (errors) => setError(errors.photo ?? errors._form ?? 'Não foi possível enviar a foto.'),
    }, body);
    setUploading(false);
  }

  return (
    <header className="settings-identity" data-tour="profile-identity">
      <ProfileAvatar name={user.name} photoUrl={user.photo_url} />
      <h2>{user.name}</h2>
      <p>{user.email}</p>
      {editable && (
        <>
          <input ref={fileInput} type="file" accept={acceptedTypes.join(',')} hidden onChange={(event) => {
            const file = event.currentTarget.files?.[0];
            event.currentTarget.value = '';
            if (file) void upload(file);
          }} />
          <Button type="button" tone="white" leftIconClass="fa fa-camera" disabled={uploading}
            onClick={() => fileInput.current?.click()}>
            {uploading ? 'Enviando foto…' : user.photo_url ? 'Alterar foto' : 'Adicionar foto'}
          </Button>
          {error && <p className="formField__error" role="alert">{error}</p>}
        </>
      )}
    </header>
  );
}
