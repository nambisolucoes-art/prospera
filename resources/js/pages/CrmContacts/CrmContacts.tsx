import CardBox from "@/components/CardBox/CardBox";
import CrmToolbar from "@/components/CrmToolbar/CrmToolbar";
import PeopleTable from "@/components/PeopleTable/PeopleTable";
import ContactActivityCard from "@/components/ContactActivityCard/ContactActivityCard";
import { crmPeople } from "@/pages/Crm/Crm.data";
import "./CrmContacts.css";
export default function CrmContacts({
  customers = false,
}: {
  customers?: boolean;
}): JSX.Element {
  return (
    <div className={`crmContacts${customers ? " crmContacts--customers" : ""}`}>
      <div className="crmContacts__main">
        <CardBox>
          <CrmToolbar
            kind="person"
            addLabel={customers ? "Adicionar cliente" : "Adicionar contato"}
            searchLabel={
              customers ? "Pesquisar clientes" : "Pesquisar contatos"
            }
          />
          <PeopleTable records={crmPeople} />
        </CardBox>
      </div>
      {!customers && (
        <aside className="crmContacts__aside">
          <ContactActivityCard />
        </aside>
      )}
    </div>
  );
}
