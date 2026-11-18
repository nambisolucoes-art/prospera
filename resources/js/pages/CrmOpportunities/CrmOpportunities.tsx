import CrmToolbar from "@/components/CrmToolbar/CrmToolbar";
import OpportunityCard from "@/components/OpportunityCard/OpportunityCard";
import CardBox from "@/components/CardBox/CardBox";
import C3Chart from "@/components/C3Chart/C3Chart";
import { opportunities } from "@/pages/Crm/Crm.data";
import { opportunityChartOptions } from "./CrmOpportunities.data";
import "./CrmOpportunities.css";
export default function CrmOpportunities(): JSX.Element {
  return (
    <>
      <CrmToolbar
        kind="opportunity"
        addLabel="Adicionar novo"
        searchLabel="Pesquisar oportunidades"
      />
      <div className="crmOpportunities">
        <div>
          {opportunities.map((record, index) => (
            <OpportunityCard
              key={record.id}
              record={record}
              last={index === opportunities.length - 1}
            />
          ))}
        </div>
        <aside>
          <CardBox>
            <h4 className="crmOpportunities__title">Gráfico de status</h4>
            <C3Chart
              options={opportunityChartOptions}
              label="Distribuição de oportunidades por status"
            />
          </CardBox>
        </aside>
      </div>
    </>
  );
}
