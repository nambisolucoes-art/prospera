import CrmToolbar from "@/components/CrmToolbar/CrmToolbar";
import LeadCard from "@/components/LeadCard/LeadCard";
import CardBox from "@/components/CardBox/CardBox";
import MorrisChart from "@/components/MorrisChart/MorrisChart";
import ScrollArea from "@/components/ScrollArea/ScrollArea";
import ActivityTimeline from "@/components/ActivityTimeline/ActivityTimeline";
import { leads } from "@/pages/Crm/Crm.data";
import { crmActivity } from "@/components/ContactActivityCard/ContactActivityCard.data";
import { leadsChartOptions } from "./CrmLeads.data";
import "./CrmLeads.css";
export default function CrmLeads(): JSX.Element {
  return (
    <>
      <CrmToolbar
        kind="lead"
        addLabel="Adicionar novo"
        modalTitle="Adicionar leads"
        searchLabel="Pesquisar leads"
      />
      <div className="crmLeads">
        <div>
          {leads.map((record) => (
            <LeadCard key={record.id} record={record} />
          ))}
        </div>
        <aside>
          <CardBox className="crmLeads__statistics">
            <h4 className="crmLeads__title">Estatísticas de leads</h4>
            <MorrisChart
              type="bar"
              options={leadsChartOptions}
              height={260}
              label="Estatísticas de leads por dia da semana"
            />
            <div className="crmLeads__activity">
              <h4 className="crmLeads__title">
                <b>Atividades</b>
              </h4>
              <ScrollArea
                maxHeight={535}
                className="crmLeads__scroll"
                label="Atividades dos leads"
              >
                <ActivityTimeline items={crmActivity} compactTime />
              </ScrollArea>
            </div>
          </CardBox>
        </aside>
      </div>
    </>
  );
}
