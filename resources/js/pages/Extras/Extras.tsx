import PageBreadcrumb from "@/components/PageBreadcrumb/PageBreadcrumb";
import ProfilePage from "@/pages/ProfilePage/ProfilePage";
import EventTimeline from "@/components/EventTimeline/EventTimeline";
import Sitemap from "@/components/Sitemap/Sitemap";
import Invoice from "@/components/Invoice/Invoice";
import EmailTemplates from "@/components/EmailTemplates/EmailTemplates";
import StatusPage from "@/components/StatusPage/StatusPage";
import FaqList from "@/components/FaqList/FaqList";
import SearchResults from "@/components/SearchResults/SearchResults";
import FilteredGallery from "@/components/FilteredGallery/FilteredGallery";
import PricingPlans from "@/components/PricingPlans/PricingPlans";
import type { ExtrasDefinition } from "./Extras.types";
import "./Extras.css";
export default function Extras({
  page,
}: {
  page: ExtrasDefinition;
}): JSX.Element {
  if (page.kind === "maintenance" || page.kind === "coming-soon")
    return <StatusPage kind={page.kind} />;
  return (
    <section className="extrasPage" aria-label={page.title}>
      {page.kind !== "profile" && (
        <>
          <h4 className="extrasPage__title">{page.title}</h4>
          <PageBreadcrumb
            items={[
              { label: "Prospera", href: "#" },
              { label: "Extras", href: "#" },
              { label: page.title },
            ]}
          />
        </>
      )}
      {page.kind === "profile" && <ProfilePage />}
      {page.kind === "timeline" && <EventTimeline />}
      {page.kind === "sitemap" && <Sitemap />}
      {page.kind === "invoice" && <Invoice />}
      {page.kind === "email-template" && <EmailTemplates />}
      {page.kind === "faq" && <FaqList />}
      {page.kind === "search-result" && <SearchResults />}
      {page.kind === "gallery" && <FilteredGallery />}
      {page.kind === "pricing" && <PricingPlans />}
    </section>
  );
}
