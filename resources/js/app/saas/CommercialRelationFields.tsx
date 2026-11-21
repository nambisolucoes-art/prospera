import { useState } from 'react';
import { BusinessFormSection } from '@/components/BusinessForm/BusinessForm';
import type { Errors } from '@/components/BusinessForm/BusinessFormFields';
import ChoiceControl from '@/components/ChoiceControl/ChoiceControl';
import StatusLabel from '@/components/StatusLabel/StatusLabel';
import Table from '@/components/Table/Table';
import { RelationStatusLabel } from './CommercialRelations';
import type { CommercialCompanyOption, CommercialOptions, CommercialRoleOption } from './Directory.types';
import './CommercialRelations.css';

type Role = 'customer' | 'supplier';

const roles: Array<{ key: Role; label: string }> = [
  { key: 'customer', label: 'Cliente' },
  { key: 'supplier', label: 'Fornecedor' },
];

// Só envia o papel que o operador pode alterar; o servidor ignora empresas e papéis não enviados.
function editable(company: CommercialCompanyOption, option: CommercialRoleOption): boolean {
  if (!company.active) return false;
  if (option.status === 'active') return option.can_deactivate;
  if (option.status === 'inactive') return option.can_activate;
  return option.can_create;
}

/**
 * Relação comercial de cada empresa autorizada (E07). A escolha é sempre por empresa explícita;
 * marcar numa empresa nunca cria a relação nas demais.
 */
export default function CommercialRelationFields({ commercial, errors }: { commercial: CommercialOptions; errors: Errors }) {
  const initial = Object.fromEntries(commercial.companies.flatMap((company) => roles.map(({ key }) => [
    `${company.id}.${key}`,
    company[key].status === 'active',
  ])));
  const [checked, setChecked] = useState<Record<string, boolean>>(initial);
  const single = commercial.single_company && commercial.companies.length === 1;
  const error = Object.entries(errors).find(([key]) => key === 'relations' || key.startsWith('relations.'))?.[1];
  const leaving = commercial.companies.flatMap((company) => roles
    .filter(({ key }) => company[key].status === 'active' && !checked[`${company.id}.${key}`])
    .map(({ key, label }) => ({ company: company.name, label })));

  const control = (company: CommercialCompanyOption, role: Role, label: string) => {
    const option = company[role];
    const key = `${company.id}.${role}`;
    if (option.status === null && !option.can_create) return <RelationStatusLabel status="restricted" />;
    const enabled = editable(company, option);
    return (
      <>
        {enabled && <input type="hidden" name={`relations[${company.id}][${role}]`} value="0" />}
        <ChoiceControl
          type="checkbox"
          tone="primary"
          id={`relation-${company.id}-${role}`}
          name={enabled ? `relations[${company.id}][${role}]` : undefined}
          value="1"
          label={single ? label : <span className="commercial-relations__caption">{label} em {company.name}</span>}
          checked={checked[key]}
          disabled={!enabled}
          onChange={(value) => setChecked((current) => ({ ...current, [key]: value }))}
        />
        {option.status === 'inactive' && !checked[key] && <RelationStatusLabel status="inactive" />}
      </>
    );
  };

  return (
    <BusinessFormSection
      title={single ? 'Relação comercial' : 'Relações comerciais'}
      tourTarget="person-relations"
      note={single
        ? 'Marque o papel desta pessoa na empresa. A relação comercial não concede acesso ao sistema.'
        : 'Dados de cada empresa: valem somente para a empresa da linha. As outras empresas não veem estes dados.'}
    >
      {single ? (
        <div className="commercial-relations__choices business-form__control-start">
          {roles.map(({ key, label }) => <div key={key}>{control(commercial.companies[0], key, label)}</div>)}
        </div>
      ) : (
        <div className="commercial-relations__scroll business-form__control-start">
          <Table hover headerTone="primary" className="commercial-relations__table">
            <caption className="commercial-relations__caption">Relações comerciais por empresa</caption>
            <thead>
              <tr>
                <th scope="col">Empresa</th>
                {roles.map(({ key, label }) => <th scope="col" key={key}>{label}</th>)}
              </tr>
            </thead>
            <tbody>
              {commercial.companies.map((company) => (
                <tr key={company.id}>
                  <th scope="row">
                    {company.name}
                    {!company.active && <> <StatusLabel active={false} /></>}
                  </th>
                  {roles.map(({ key, label }) => <td key={key}>{control(company, key, label)}</td>)}
                </tr>
              ))}
            </tbody>
          </Table>
        </div>
      )}
      {leaving.length > 0 && (
        <div className="business-form__control-start" role="status">
          {leaving.map(({ company, label }) => (
            <p key={company + label}>
              Ao salvar, a relação de {label.toLowerCase()} com {company} será inativada. A pessoa, as outras relações e o histórico serão preservados.
            </p>
          ))}
        </div>
      )}
      {error && <p className="formField__error business-form__control-start" role="alert">{error}</p>}
    </BusinessFormSection>
  );
}
