import PageBreadcrumb from "@/components/PageBreadcrumb/PageBreadcrumb";
import Mailbox from "@/components/Mailbox/Mailbox";
import ContactDirectory from "@/components/ContactDirectory/ContactDirectory";
import CalendarPage from "@/pages/CalendarPage/CalendarPage";
import type { ApplicationDefinition } from "./Applications.types";
import "./Applications.css";
export default function Applications({
  page,
}: {
  page: ApplicationDefinition;
}): JSX.Element {
  return (
    <section className="applicationsPage" aria-label={page.title}>
      <h4 className="applicationsPage__title">{page.title}</h4>
      <PageBreadcrumb
        items={[
          { label: "Prospera", href: "#" },
          { label: "Aplicativos", href: "#" },
          { label: page.title },
        ]}
      />
      {page.kind === "contact" ? (
        <ContactDirectory />
      ) : page.kind === "calendar" ? (
        <CalendarPage />
      ) : (
        <Mailbox kind={page.kind} />
      )}
    </section>
  );
}
