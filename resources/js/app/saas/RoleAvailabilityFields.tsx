import { useState } from 'react';
import { BusinessFormSection } from '@/components/BusinessForm/BusinessForm';
import { Select, type Errors } from '@/components/BusinessForm/BusinessFormFields';
import Button from '@/components/Button/Button';
import ChoiceControl from '@/components/ChoiceControl/ChoiceControl';
import FormHelp from '@/components/FormHelp/FormHelp';
import { apiRequest } from '@/utils/apiRequest';
import type { RoleRow } from './Access.types';

type Company = { id: number; name: string; active: boolean };
type Unit = Company & { company_id: number };
export type AvailabilityOptions = { companies: Company[]; establishments: Unit[] };
type Impact = { id: number; name: string; company_id: number | null; establishment_id: number | null };

export default function RoleAvailabilityFields({ role, organization, errors }: {
  role: RoleRow | null;
  organization: AvailabilityOptions;
  errors: Errors;
}) {
  const [mode, setMode] = useState(role?.availability ?? 'tenant');
  const [companies, setCompanies] = useState(() => (role?.availability_nodes ?? [])
    .filter((node) => node.establishment_id === null).map((node) => node.company_id));
  const [units, setUnits] = useState(() => (role?.availability_nodes ?? [])
    .flatMap((node) => node.establishment_id === null ? [] : [node.establishment_id]));
  const [impact, setImpact] = useState<Impact[]>();
  const [confirmed, setConfirmed] = useState(false);
  const [failure, setFailure] = useState('');
  const [reviewing, setReviewing] = useState(false);

  function clearReview() {
    setImpact(undefined);
    setConfirmed(false);
    setFailure('');
  }

  async function review(roleId: number) {
    clearReview();
    setReviewing(true);
    const query = new URLSearchParams({ availability: mode });
    companies.forEach((id) => query.append('available_companies[]', String(id)));
    units.forEach((id) => query.append('available_establishments[]', String(id)));

    try {
      const response = await apiRequest<{ assignments: Impact[] }>(`/conta/cargos/${roleId}/impacto-abrangencia?${query}`);
      setImpact(response.assignments);
    } catch (error) {
      setFailure(error instanceof Error ? error.message : 'Não foi possível consultar o impacto.');
    } finally {
      setReviewing(false);
    }
  }

  function location(item: Impact) {
    if (item.establishment_id !== null) {
      return organization.establishments.find((unit) => unit.id === item.establishment_id)?.name ?? 'Estabelecimento';
    }

    return item.company_id === null ? 'Toda a conta'
      : organization.companies.find((company) => company.id === item.company_id)?.name ?? 'Empresa';
  }

  return (
    <BusinessFormSection title="Abrangência" tourTarget="role-availability">
      <FormHelp className="business-form__control-start">
        A abrangência define onde o cargo pode ser atribuído. Para conceder acesso, atribua o cargo a um vínculo.
      </FormHelp>
      {role?.system_key ? <FormHelp className="business-form__control-start">Cargos de sistema abrangem toda a conta.</FormHelp> : (
        <fieldset disabled={reviewing} style={{ border: 0, padding: 0, margin: 0, minWidth: 0 }}>
          <Select label="Disponibilidade" info="Define onde o cargo pode ser atribuído. Selecionar toda a conta inclui empresas e estabelecimentos futuros. Para conceder acesso, atribua o cargo a um vínculo." name="availability" value={mode} error={errors.availability}
            onChange={(value) => { setMode(value as 'tenant' | 'restricted'); clearReview(); }}
            options={[
              { value: 'tenant', label: 'Toda a conta (inclui empresas e estabelecimentos futuros)' },
              { value: 'restricted', label: 'Restrita a empresas ou estabelecimentos selecionados' },
            ]} />
          {mode === 'restricted' && (
            <div className="business-form__control-start">
              {organization.companies.map((company) => (
                <fieldset key={company.id}>
                  <legend>{company.name}</legend>
                  <ChoiceControl type="checkbox" id={`availability-company-${company.id}`} name="available_companies[]"
                    value={String(company.id)} label="Toda a empresa" checked={companies.includes(company.id)}
                    onChange={(checked) => {
                      setCompanies((current) => checked ? [...current, company.id] : current.filter((id) => id !== company.id));
                      clearReview();
                    }} />
                  {organization.establishments.filter((unit) => unit.company_id === company.id).map((unit) => (
                    <ChoiceControl key={unit.id} type="checkbox" id={`availability-unit-${unit.id}`}
                      name="available_establishments[]" value={String(unit.id)} label={unit.name}
                      checked={companies.includes(company.id) || units.includes(unit.id)} disabled={companies.includes(company.id)}
                      onChange={(checked) => {
                        setUnits((current) => checked ? [...current, unit.id] : current.filter((id) => id !== unit.id));
                        clearReview();
                      }} />
                  ))}
                </fieldset>
              ))}
              {role && (
                <Button type="button" tone="white" leftIconClass="fa fa-search"
                  disabled={reviewing} onClick={() => void review(role.id)}>Verificar impacto</Button>
              )}
              {failure && <p role="alert">{failure}</p>}
              {impact && (impact.length === 0 ? <p role="status">Nenhuma atribuição será removida.</p> : (
                <>
                  <p>Ao salvar, estas atribuições serão removidas:</p>
                  <ul>{impact.map((item) => <li key={item.id}>{item.name} ({location(item)})</li>)}</ul>
                  <ChoiceControl type="checkbox" id="confirm-role-impact" label="Confirmo a remoção dessas atribuições"
                    checked={confirmed} onChange={setConfirmed} />
                  {confirmed && impact.map((item) => (
                    <input key={item.id} type="hidden" name="confirmed_revocations[]" value={item.id} />
                  ))}
                </>
              ))}
            </div>
          )}
        </fieldset>
      )}
    </BusinessFormSection>
  );
}
