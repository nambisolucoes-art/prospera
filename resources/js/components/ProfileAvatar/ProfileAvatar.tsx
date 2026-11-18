import { useState } from 'react';
import './ProfileAvatar.css';

export default function ProfileAvatar({ name, photoUrl, small = false, lazy = false }: {
  name: string;
  photoUrl?: string | null;
  small?: boolean;
  lazy?: boolean;
}) {
  const [unavailablePhoto, setUnavailablePhoto] = useState<string>();
  const initials = name.trim().split(/\s+/).slice(0, 2)
    .map((word) => Array.from(word)[0]).join('').toLocaleUpperCase('pt-BR');

  return (
    <span className={small ? 'profile-avatar profile-avatar--small' : 'profile-avatar'} aria-hidden="true">
      {photoUrl && photoUrl !== unavailablePhoto
        ? <img src={photoUrl} alt="" loading={lazy ? 'lazy' : undefined} onError={() => setUnavailablePhoto(photoUrl)} />
        : initials || <i className="fa fa-user" />}
    </span>
  );
}
