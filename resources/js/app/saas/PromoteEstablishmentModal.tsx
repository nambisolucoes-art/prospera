import ApiForm from '@/components/ApiForm/ApiForm';
import Button from '@/components/Button/Button';
import Modal from '@/components/Modal/Modal';
import { companyName, type EstablishmentRow } from './Organization.types';
export default function PromoteEstablishmentModal({
  establishment,
  onClose,
  actionSuffix = '',
}: {
  establishment: EstablishmentRow;
  onClose: () => void;
  actionSuffix?: string;
}) {
  return (
    <Modal open bootstrap manageFocus size="small" title="Tornar estabelecimento principal" onClose={onClose}>
      <ApiForm action={`/conta/estabelecimentos/${establishment.id}/principal${actionSuffix}`} method="post" onSuccess={onClose}
        options={{ preserveScroll: true }}>
        {({ processing, errors }) => (
          <>
            <p>
              Tornar {establishment.name} o estabelecimento principal de {companyName(establishment.company)}?
              O principal atual continua ativo e passa a ser uma unidade comum.
            </p>
            {errors.establishment && <p className="formField__error" role="alert">{errors.establishment}</p>}
            <div className="business-form__actions-inline">
              <Button tone="white" disabled={processing} onClick={onClose}>Cancelar</Button>
              <Button type="submit" tone="success" disabled={processing}>Salvar</Button>
            </div>
          </>
        )}
      </ApiForm>
    </Modal>
  );
}
