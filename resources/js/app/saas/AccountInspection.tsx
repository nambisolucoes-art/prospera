import { useApiForm as useForm } from '@/hooks/useApiForm';
import { useEffect, useId, useState } from 'react';
import { Select } from '@/components/BusinessForm/BusinessFormFields';
import Button from '@/components/Button/Button';
import Modal from '@/components/Modal/Modal';
import ProfileAvatar from '@/components/ProfileAvatar/ProfileAvatar';
import '@/components/BusinessForm/BusinessForm.css';
import './AccountInspection.css';

type InspectionUser = { role_name: string; id: number; name: string; email: string; photo_url: string | null };

const modes = [
  { value: 'read-only', label: 'Somente leitura' },
  { value: 'edit', label: 'Leitura e edição' },
];

export default function AccountInspection({ id, name, onClose }: { id: number; name: string; onClose: () => void }) {
  const url = `/saas/contas/${id}/inspecao`;
  const formId = useId();
  const form = useForm({ user_id: '', mode: '' });
  const [users, setUsers] = useState<InspectionUser[]>();
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    const request = new AbortController();
    fetch(`${url}/usuarios`, { signal: request.signal, headers: { Accept: 'application/json' } })
      .then((response) => {
        if (!response.ok) throw new Error('Não foi possível consultar os usuários.');
        return response.json() as Promise<InspectionUser[]>;
      })
      .then(setUsers)
      .catch(() => { if (!request.signal.aborted) setFailed(true); });

    return () => request.abort();
  }, [url]);

  const options = users?.map((user) => {
    const role = user.role_name;
    return { value: String(user.id), label: user.name, searchText: `${user.email} ${role}`, role, photoUrl: user.photo_url };
  });

  function close() {
    form.resetAndClearErrors();
    onClose();
  }

  return (
    <Modal open title="Inspecionar conta" size="small" bootstrap manageFocus closeOnBackdrop={!form.processing}
      layerClassName="account-inspection-layer" onClose={close}
      footer={(closeModal) => <>
        <Button type="button" tone="white" disabled={form.processing} onClick={closeModal}>Cancelar</Button>
        {!!options?.length && <Button type="submit" form={formId} tone="primary" disabled={form.processing}>Inspecionar</Button>}
      </>}>
      {(closeModal) => <>
        <p className="account-inspection__name">{name}</p>
        {failed ? <p role="alert">Não foi possível carregar os usuários.</p> : !options ? (
          <p role="status">Carregando usuários…</p>
        ) : options.length === 0 ? <p>Nenhum usuário com acesso disponível.</p> : (
          <form id={formId} className="business-form" noValidate onSubmit={(event) => {
            event.preventDefault();
            if (!form.processing) form.post(url, { beforeNavigate: closeModal });
          }}>
            <Select label="Usuário" layout="stacked" name="user_id" required searchable options={options} value={form.data.user_id} error={form.errors.user_id}
              renderOption={({ label, role, photoUrl }) => (
                <span className="account-inspection__user">
                  <ProfileAvatar name={label} photoUrl={photoUrl} small lazy />
                  <span className="account-inspection__user-text">
                    <span className="account-inspection__user-name">{label}</span>
                    <span className="account-inspection__user-role">{role}</span>
                  </span>
                </span>
              )}
              onChange={(value) => { form.setData('user_id', value); form.clearErrors('user_id'); }} />
            <Select label="Modo de inspeção" layout="stacked" name="mode" required options={modes} value={form.data.mode} error={form.errors.mode}
              onChange={(value) => { form.setData('mode', value); form.clearErrors('mode'); }} />
            {form.errors._form && <p className="formField__error" role="alert">{form.errors._form}</p>}
          </form>
        )}
      </>}
    </Modal>
  );
}
