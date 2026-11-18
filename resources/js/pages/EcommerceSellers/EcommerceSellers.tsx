import CardBox from "@/components/CardBox/CardBox";
import CrmToolbar from "@/components/CrmToolbar/CrmToolbar";
import PeopleTable from "@/components/PeopleTable/PeopleTable";
import { sellers } from "./EcommerceSellers.data";
export default function EcommerceSellers(): JSX.Element {
  return (
    <CardBox>
      <CrmToolbar
        kind="person"
        addLabel="Adicionar vendedor"
        searchLabel="Pesquisar vendedores"
      />
      <PeopleTable records={sellers} variant="sellers" />
    </CardBox>
  );
}
