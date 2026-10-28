/*
 * A lead's status and what it is called on screen. Pure, with no imports, for
 * tests/js.
 *
 * A lead is marketing's record of one client: idle until marketing has won or
 * lost the client, and final after. It is never an inquiry status and never
 * reaches a client.
 */

export type LeadStatus = 'idle' | 'won' | 'lost';

/** The Leads page's tabs, in order. */
export const LEAD_STATUSES: readonly LeadStatus[] = ['idle', 'won', 'lost'];

export const LEAD_STATUS_LABELS: Record<LeadStatus, string> = {
    idle: 'Idle',
    won: 'Won',
    lost: 'Lost',
};

export const leadStatusLabel = (status: LeadStatus): string =>
    LEAD_STATUS_LABELS[status] ?? status;

/** Whether marketing may still mark it won or lost. */
export const isLeadOpenForDecision = (status: LeadStatus): boolean =>
    status === 'idle';
