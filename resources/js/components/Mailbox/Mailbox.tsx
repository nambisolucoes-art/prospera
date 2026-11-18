import MailboxSidebar from "@/components/MailboxSidebar/MailboxSidebar";
import MailToolbar from "@/components/MailToolbar/MailToolbar";
import MailList from "@/components/MailList/MailList";
import MailMessage from "@/components/MailMessage/MailMessage";
import MailCompose from "@/components/MailCompose/MailCompose";
import "./Mailbox.css";
export default function Mailbox({
  kind,
}: {
  kind: "inbox" | "email-read" | "email-compose";
}): JSX.Element {
  return (
    <div className="mailbox">
      <aside className="mailbox__sidebar">
        <MailboxSidebar
          composing={kind === "email-compose"}
          inboxPage={kind === "inbox"}
        />
      </aside>
      <div className="mailbox__content">
        {kind !== "email-compose" && <MailToolbar />}
        {kind === "inbox" ? (
          <MailList />
        ) : kind === "email-read" ? (
          <MailMessage />
        ) : (
          <MailCompose />
        )}
      </div>
    </div>
  );
}
