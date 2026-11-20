import Tooltip from '@/components/Tooltip/Tooltip';
import './TagLabel.css';
import { tagColors } from './TagLabel.colors';
export default function TagLabel({
  name,
  color,
  special = false,
}: {
  name: string;
  color: string;
  special?: boolean;
}) {
  return (
    <span className="tag-label">
      <span className="tag-label__name" style={tagColors(color)}>
        {name}
      </span>
      {special && (
        <Tooltip text="Especial">
          <span
            className="tag-label__special"
            tabIndex={0}
            role="img"
            aria-label="Especial"
            title="Especial"
          >
            <i className="fa fa-star" aria-hidden="true" />
          </span>
        </Tooltip>
      )}
    </span>
  );
}
