export type Address = Partial<Record<'postal_code' | 'street' | 'number' | 'complement' | 'neighborhood' | 'city' | 'state', string | null>>;

export type ClientData = {
  id: number;
  name: string;
  email: string | null;
  person_type: 'company' | 'individual' | null;
  document: string | null;
  active: boolean;
  plan: string | null;
  details: {
    trade_name?: string | null;
    address?: Address;
    phone?: string | null;
    whatsapp?: string | null;
    additional_phones?: string[];
    additional_emails?: string[];
    phones?: { type: string; number: string | null }[];
  } | null;
};

export function contactNumber(details: ClientData['details'] | undefined, field: 'phone' | 'whatsapp'): string {
  if (details && field in details) return details[field] ?? '';
  return details?.phones?.find((phone) => field === 'phone' || phone.type === 'whatsapp')?.number ?? '';
}
