export type SessionProps = {
  [key: string]: unknown;
  name: string;
  auth: {
    permissions: string[];
    inspection_mode: 'read-only' | 'edit' | null;
    user: { id: number; name: string; email: string; email_verified_at: string | null; photo_url?: string | null; is_saas_admin: boolean } | null;
  };
};
