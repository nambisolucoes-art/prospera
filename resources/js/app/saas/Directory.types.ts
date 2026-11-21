import type { Address } from './ClientForm.types';

/** Situação de um papel comercial numa empresa, como o leitor pode vê-la. */
export type RelationStatus = 'active' | 'inactive' | 'none' | 'restricted';

export type PersonRelation = {
    company_id: number;
    company_name: string;
    company_active: boolean;
    customer: RelationStatus;
    supplier: RelationStatus;
};

export type CommercialRoleOption = {
    status: Exclude<RelationStatus, 'restricted'> | null;
    can_create: boolean;
    can_activate: boolean;
    can_deactivate: boolean;
};

export type CommercialCompanyOption = {
    id: number;
    name: string;
    active: boolean;
    customer: CommercialRoleOption;
    supplier: CommercialRoleOption;
};

export type CommercialOptions = {
    single_company: boolean;
    companies: CommercialCompanyOption[];
};

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
    has_user?: boolean;
    relations?: PersonRelation[];
    membership?: {
      id: number;
      email: string | null;
      status: string;
      can_manage: boolean;
      assignments: { id: number; role: string; scope: string; location: string; coverage: string }[];
    } | null;
};
