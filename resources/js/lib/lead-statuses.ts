/*
 * Marketing's decisions and what they are called on screen. Pure, with no
 * imports, for tests/js.
 *
 * Marketing decides each inquiry on a lead won or lost; idle is one still
 * waiting. A lead has no status of its own: it shows the tally of its
 * inquiries' decisions. None of it is an inquiry status, and none of it ever
 * reaches a client.
 */

export type LeadStatus = 'idle' | 'won' | 'lost';

/** How many of a lead's inquiries marketing has won and lost, and how many wait. */
export type LeadTally = Record<LeadStatus, number>;

/** The Leads page's filter, in order: leads with an inquiry at that decision. */
export const LEAD_STATUSES: readonly LeadStatus[] = ['idle', 'won', 'lost'];

export const LEAD_STATUS_LABELS: Record<LeadStatus, string> = {
    idle: 'Idle',
    won: 'Won',
    lost: 'Lost',
};

export const leadStatusLabel = (status: LeadStatus): string =>
    LEAD_STATUS_LABELS[status] ?? status;

/**
 * The parts a lead's tally is shown as. One inquiry (or none) shows just its
 * status; several show each decision with its count, won first, leaving out
 * the ones at zero.
 */
export const leadTallyParts = (
    tally: LeadTally,
): Array<{ status: LeadStatus; count: number | null }> => {
    const total = tally.won + tally.lost + tally.idle;

    if (total <= 1) {
        const only =
            LEAD_STATUSES.find((status) => tally[status] === 1) ?? 'idle';

        return [{ status: only, count: null }];
    }

    return (['won', 'lost', 'idle'] as const)
        .filter((status) => tally[status] > 0)
        .map((status) => ({ status, count: tally[status] }));
};

/** "Won 2 · Lost 1", or "Lost" for a lead with one inquiry. */
export const leadTallyLabel = (tally: LeadTally): string =>
    leadTallyParts(tally)
        .map(({ status, count }) =>
            count === null
                ? leadStatusLabel(status)
                : `${leadStatusLabel(status)} ${count}`,
        )
        .join(' · ');
