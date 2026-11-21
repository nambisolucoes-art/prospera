import Label from '@/components/Label/Label';
import './Access.css';

export default function PermissionChips({ permissions }: { permissions: string[] }) {
  return (
    <ul className="access-preview__permissions">
      {Array.from(new Set(permissions), (permission) => (
        <li key={permission}>
          <Label className="access-preview__permission">{permission}</Label>
        </li>
      ))}
    </ul>
  );
}
