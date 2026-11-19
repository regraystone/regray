/*
 * The staff Inquire flow's first question: does the client have a Regray
 * account? Pure, with no imports, for tests/js.
 *
 * Staff only. A client sends their own inquiry and is never asked.
 */

export type StaffInquireKind = 'platform' | 'direct';

export type StaffInquireOption = {
    kind: StaffInquireKind;
    label: string;
    description: string;
};

/** The modal's two options, in the order they are shown. */
export const STAFF_INQUIRE_OPTIONS: readonly StaffInquireOption[] = [
    {
        kind: 'platform',
        label: 'Platform',
        description: 'The client has a Regray account.',
    },
    {
        kind: 'direct',
        label: 'Direct (walk-in)',
        description: 'The client has no account.',
    },
];

/** The branch a `?kind=` names, or null when it names none. */
export const staffInquireKindFrom = (
    value: string | null | undefined,
): StaffInquireKind | null =>
    value === 'platform' || value === 'direct' ? value : null;

/**
 * The branch staff land on: what they asked for, except that staff who may
 * not use Platform (sales, without `inquiries.create.platform`) are always on
 * Direct.
 */
export const staffInquireKindFor = (
    requested: StaffInquireKind | null,
    canUsePlatform: boolean,
): StaffInquireKind | null => (canUsePlatform ? requested : 'direct');

/** The ways a direct client can be reached, as `client_contacts.kind`. */
export type ContactKind = 'phone' | 'email';

export const CONTACT_KIND_LABELS: Record<ContactKind, string> = {
    phone: 'Phone',
    email: 'Email',
};

export const CONTACT_KINDS = Object.keys(CONTACT_KIND_LABELS) as ContactKind[];
