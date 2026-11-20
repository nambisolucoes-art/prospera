import type { Address } from './ClientForm.types';

export type SupplierProfile = { active: boolean };

export type TagRow = {
    active: boolean;
    people_count?: number;
    id: number;
    name: string;
    module: string;
    color: string;
    system_key: string | null;
};
export type PersonRow = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    tags: TagRow[];
    person_type?: 'individual' | 'company';
    document?: string | null;
    trade_name?: string | null;
    state_registration?: string | null;
    municipal_registration?: string | null;
    contact_name?: string | null;
    whatsapp?: string | null;
    additional_phones?: string[] | null;
    additional_emails?: string[] | null;
    website?: string | null;
    address?: Address | null;
    has_user?: boolean;
    has_customer?: boolean;
    customer?: { active: boolean } | null;
    has_supplier?: boolean;
    supplier?: SupplierProfile | null;
    membership?: {
      id: number;
      email: string | null;
      status: string;
      can_manage: boolean;
      assignments: { id: number; role: string; scope: string; location: string; coverage: string }[];
    } | null;
};
