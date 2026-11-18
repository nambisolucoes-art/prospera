import type { Contact } from "@/components/ContactCard/ContactCard.types";
export const contacts: Contact[] = Array.from({ length: 8 }, (_, index) => ({
  id: String(index + 1),
  name: "Bill Bertz",
  position: "Gerente de filial",
  company: "ABC company Pvt Ltd.",
  avatar: `/assets/images/users/avatar-${index + 2}.jpg`,
}));
