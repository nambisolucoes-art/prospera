import type { Address } from './ClientForm.types';

export type TagRow = {
    active: boolean;
    people_count?: number;
    commercial_assigned?: boolean | number;
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
};
