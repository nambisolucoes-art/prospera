import { useApiPage as usePage } from '@/hooks/useApiPage';
import { AccountForm } from '@/app/account/AccountForm';
import { Select } from '@/components/BusinessForm/BusinessFormFields';
import Button from '@/components/Button/Button';
import CardBox from '@/components/CardBox/CardBox';
import type { SessionProps } from '@/types/Session.types';
import SaasLayout from './SaasLayout';

type Point = { id: number; name: string; active: boolean };

function PointForm({ point }: { point?: Point }) {
  return (
    <AccountForm business action={point ? `/conta/pontos/${point.id}` : '/conta/pontos'}
      method={point ? 'patch' : 'post'} submit={point ? 'Salvar ponto' : 'Cadastrar ponto'}
     
      fields={[{ name: 'name', label: 'Nome do ponto', value: point?.name }]}>
      <Select label="Situação" name="active" defaultValue={point?.active === false ? '0' : '1'}
        options={[{ value: '1', label: 'Ativo' }, { value: '0', label: 'Inativo' }]} />
    </AccountForm>
  );
}

export default function Tenant({ account, points }: { account: { name: string }; points: Point[] }) {
  const { auth } = usePage<SessionProps>().props;
  const mayManage = auth.user?.is_account_admin === true && auth.inspection_mode !== 'read-only';
  return (
    <SaasLayout tenant title={account.name}
      actions={mayManage && <Button href="/conta/editar" tone="primary" leftIconClass="fa fa-pencil">Editar estabelecimento</Button>}>
      <CardBox>
        <h2>Pontos do estabelecimento</h2>
        {!points.length && <p>Nenhum ponto cadastrado.</p>}
        {points.map((point) => (
          <div className="saas-point" key={point.id}>
            <strong>{point.name}</strong> · {point.active ? 'Ativo' : 'Inativo'}
            {mayManage && <details><summary>Editar ponto</summary><PointForm key={`${point.name}-${point.active}`} point={point} /></details>}
          </div>
        ))}
        {mayManage && <details><summary>Novo ponto</summary><PointForm /></details>}
      </CardBox>
    </SaasLayout>
  );
}
