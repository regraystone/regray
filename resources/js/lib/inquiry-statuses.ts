/*
 * An inquiry's statuses and what they are called on screen. Pure, with no
 * imports, for tests/js.
 *
 * Every inquiry has three role statuses (sales, accounting's payment and
 * logistics' process) and one display status, the inquiry status, which is the
 * single status shown for it everywhere. The server works the display status
 * out (App\Services\InquiryStatusCalculator); the page never does. Marketing's
 * status is the inquiry's lead's (lib/lead-statuses.ts) and never shown to a
 * client.
 *
 * A role's status exists only once its stage has started: sales from the lead
 * being won, payment and process from the client's approval. Until then it is
 * null, and nothing is shown for it: never a placeholder such as "pending".
 */

/**
 * The stage an inquiry has reached: one sales or logistics value, or pending,
 * closed, cancelled or for refund. A full refund cancels it; the payment badge
 * beside it then reads refunded.
 */
export type InquiryStatus =
    | 'pending'
    | 'cancelled'
    | 'closed'
    | 'negotiation'
    | 'site_visited'
    | 'on_hold'
    | 'quoted'
    | 'revision'
    | 'won'
    | 'order_received'
    | 'measure'
    | 'sourcing'
    | 'production'
    | 'quality_check'
    | 'ready'
    | 'dispatched'
    | 'installation'
    | 'delayed'
    | 'for_refund';

export type SalesStatus =
    | 'negotiation'
    | 'site_visited'
    | 'on_hold'
    | 'quoted'
    | 'revision'
    | 'won'
    | 'rejected'
    | 'for_refund';

/** Payments in, then, once sales asks for a refund, refunds out. */
export type PaymentStatus =
    | 'awaiting'
    | 'partial'
    | 'fully_paid'
    | 'partially_refunded'
    | 'refunded';

export type ProcessStatus =
    | 'order_received'
    | 'measure'
    | 'sourcing'
    | 'production'
    | 'quality_check'
    | 'ready'
    | 'dispatched'
    | 'installation'
    | 'completed'
    | 'on_hold'
    | 'delayed';

/** Which of the four a status belongs to, so the same word (on hold) is read in its own group. */
export type StatusGroup = 'inquiry' | 'sales' | 'payment' | 'process';

export type StatusOf<G extends StatusGroup> = {
    inquiry: InquiryStatus;
    sales: SalesStatus;
    payment: PaymentStatus;
    process: ProcessStatus;
}[G];

export const INQUIRY_STATUS_LABELS: Record<InquiryStatus, string> = {
    pending: 'Pending',
    cancelled: 'Cancelled',
    closed: 'Closed',
    negotiation: 'Negotiation',
    site_visited: 'Site visited',
    on_hold: 'On hold',
    quoted: 'Quoted',
    revision: 'Revision',
    won: 'Won',
    order_received: 'Order received',
    measure: 'Measure',
    sourcing: 'Sourcing',
    production: 'Production',
    quality_check: 'Quality check',
    ready: 'Ready',
    dispatched: 'Dispatched',
    installation: 'Installation',
    delayed: 'Delayed',
    for_refund: 'For refund',
};

export const SALES_STATUS_LABELS: Record<SalesStatus, string> = {
    negotiation: 'Negotiation',
    site_visited: 'Site visited',
    on_hold: 'On hold',
    quoted: 'Quoted',
    revision: 'Revision',
    won: 'Won',
    rejected: 'Rejected',
    for_refund: 'For refund',
};

export const PAYMENT_STATUS_LABELS: Record<PaymentStatus, string> = {
    awaiting: 'Awaiting',
    partial: 'Partially paid',
    fully_paid: 'Fully paid',
    partially_refunded: 'Partially refunded',
    refunded: 'Refunded',
};

export const PROCESS_STATUS_LABELS: Record<ProcessStatus, string> = {
    order_received: 'Order received',
    measure: 'Measure',
    sourcing: 'Sourcing',
    production: 'Production',
    quality_check: 'Quality check',
    ready: 'Ready',
    dispatched: 'Dispatched',
    installation: 'Installation',
    completed: 'Completed',
    on_hold: 'On hold',
    delayed: 'Delayed',
};

const LABELS: { [G in StatusGroup]: Record<StatusOf<G>, string> } = {
    inquiry: INQUIRY_STATUS_LABELS,
    sales: SALES_STATUS_LABELS,
    payment: PAYMENT_STATUS_LABELS,
    process: PROCESS_STATUS_LABELS,
};

/** What a badge of each group is, for its tooltip and screen readers. */
export const STATUS_GROUP_NAMES: Record<StatusGroup, string> = {
    inquiry: 'Inquiry status',
    sales: 'Sales',
    payment: 'Payment',
    process: 'Logistics',
};

/** One badge to draw: a status read in its group. */
export type StatusBadgeEntry = {
    [G in StatusGroup]: { group: G; status: StatusOf<G> };
}[StatusGroup];

/**
 * The badges to draw, in the order given, leaving out every stage that has not
 * started (a null status), so an unstarted stage shows nothing at all.
 */
export const startedStatuses = (
    entries: ReadonlyArray<
        {
            [G in StatusGroup]: { group: G; status: StatusOf<G> | null };
        }[StatusGroup]
    >,
): StatusBadgeEntry[] =>
    entries.filter((entry): entry is StatusBadgeEntry => entry.status !== null);

export const statusLabel = <G extends StatusGroup>(
    group: G,
    status: StatusOf<G>,
): string => (LABELS[group] as Record<string, string>)[status] ?? status;

/**
 * How a status is drawn:
 * - `solid` for a positive or reached state: won, quoted, closed, fully paid,
 *   and the logistics steps from measure to completed;
 * - `secondary` for a stopped one: cancelled, rejected, lost, on hold, delayed;
 * - `outline` for one still open: pending, idle, negotiation, order received and the
 *   rest.
 *
 * `won` and `lost` are a lead's (lib/lead-statuses.ts), whose badge is drawn
 * the same way.
 */
export type StatusTreatment = 'solid' | 'outline' | 'secondary';

const SOLID = new Set([
    'won',
    'quoted',
    'closed',
    'fully_paid',
    'measure',
    'sourcing',
    'production',
    'quality_check',
    'ready',
    'dispatched',
    'installation',
    'completed',
]);

const STOPPED = new Set([
    'cancelled',
    'rejected',
    'lost',
    'on_hold',
    'delayed',
    'for_refund',
    'partially_refunded',
    'refunded',
]);

/** The values that end a road for good, which the badge strikes through. */
const ENDED = new Set(['cancelled', 'rejected', 'lost']);

/** The values that finish one, which the badge ticks. */
const FINISHED = new Set(['closed', 'fully_paid', 'completed']);

export const statusTreatment = (status: string): StatusTreatment =>
    SOLID.has(status) ? 'solid' : STOPPED.has(status) ? 'secondary' : 'outline';

/**
 * The colour a status is drawn in, the same on every page and for every role.
 * Each stage has its own hue so a list can be read at a glance: blue-violet
 * for sales, cool blues and greens for logistics as it moves on, amber for a
 * hold or an amount still owed, red for a stop, green for done.
 */
export type StatusTone =
    | 'slate'
    | 'blue'
    | 'indigo'
    | 'violet'
    | 'amber'
    | 'yellow'
    | 'red'
    | 'rose'
    | 'sky'
    | 'cyan'
    | 'teal'
    | 'orange'
    | 'lime'
    | 'emerald'
    | 'fuchsia'
    | 'purple'
    | 'green';

const TONES: Record<string, StatusTone> = {
    pending: 'slate',
    negotiation: 'blue',
    site_visited: 'indigo',
    quoted: 'violet',
    revision: 'purple',
    won: 'green',
    on_hold: 'amber',
    rejected: 'red',
    cancelled: 'red',
    order_received: 'sky',
    measure: 'cyan',
    sourcing: 'teal',
    production: 'orange',
    quality_check: 'lime',
    ready: 'emerald',
    dispatched: 'fuchsia',
    installation: 'purple',
    completed: 'green',
    closed: 'green',
    delayed: 'rose',
    awaiting: 'amber',
    partial: 'yellow',
    fully_paid: 'green',
    for_refund: 'orange',
    partially_refunded: 'orange',
    refunded: 'slate',
};

export const statusTone = (status: string): StatusTone =>
    TONES[status] ?? 'slate';

export const isEndedStatus = (status: string): boolean => ENDED.has(status);

export const isFinishedStatus = (status: string): boolean =>
    FINISHED.has(status);

/**
 * A change in the client's activity log: a role status moving, or the inquiry
 * being cancelled when its lead was lost (`inquiry`, `cancelled`), which the
 * client sees without ever hearing about the lead.
 */
export type ActivityEntry =
    | { track: 'inquiry'; status: 'cancelled' }
    | { track: 'sales'; status: SalesStatus }
    | { track: 'payment'; status: PaymentStatus }
    | { track: 'process'; status: ProcessStatus };

/** The client's words for each change: short, and never naming a team. */
const ACTIVITY_LABELS: {
    [T in ActivityEntry['track']]: Partial<
        Record<Extract<ActivityEntry, { track: T }>['status'], string>
    >;
} = {
    inquiry: { cancelled: 'Cancelled' },
    sales: {
        negotiation: 'In discussion',
        site_visited: 'Site visited',
        on_hold: 'On hold',
        quoted: 'Quotation sent',
        revision: 'Quotation revised',
        won: 'Quote approved',
        rejected: 'Cancelled',
        for_refund: 'Refund requested',
    },
    payment: {
        awaiting: 'Awaiting payment',
        partial: 'Partially paid',
        fully_paid: 'Fully paid',
        partially_refunded: 'Partially refunded',
        refunded: 'Refunded',
    },
    process: {
        order_received: 'Order received',
        measure: 'Measuring',
        sourcing: 'Sourcing stone',
        production: 'In production',
        quality_check: 'Quality check',
        ready: 'Ready',
        dispatched: 'Dispatched',
        installation: 'Installing',
        completed: 'Completed',
        on_hold: 'On hold',
        delayed: 'Delayed',
    },
};

/** "Quote approved", "Partially paid", "In production". */
export const activityLabel = ({ track, status }: ActivityEntry): string =>
    (ACTIVITY_LABELS[track] as Record<string, string | undefined>)[status] ??
    status;

/** What the client calls the first entry of every log. */
export const INQUIRY_SUBMITTED_LABEL = 'Submitted';

/**
 * The documents the team shares on an inquiry. The job order is internal: it is
 * sent to staff only, never to the client.
 */
export type SharedDocumentType =
    | 'quotation'
    | 'job_order'
    | 'billing_statement'
    | 'receipt'
    | 'invoice'
    | 'refund_request'
    | 'refund_settlement';

/** One file of a shared group. */
export const DOCUMENT_TYPE_LABELS: Record<SharedDocumentType, string> = {
    quotation: 'Quotation',
    job_order: 'Job order',
    billing_statement: 'Billing statement',
    receipt: 'Receipt',
    invoice: 'Invoice',
    refund_request: 'Refund request form',
    refund_settlement: 'Refund settled',
};

/**
 * What a group of shared files is called: the status update it came with.
 * "Quotation · Rev 2", "Job order", "Billing statement", "Partial payment",
 * "Full payment", "Refund request", "Refund settled". The job order comes with
 * the win, or on its own (with logistics' order received when a payment is
 * already in). Files that came with no update are "Documents".
 */
export const documentGroupLabel = (
    status: string | null,
    revision: number | null,
): string => {
    switch (status) {
        case 'quoted':
        case 'revision':
            return revision === null
                ? 'Quotation'
                : `Quotation · Rev ${revision}`;
        case 'won':
        case 'order_received':
            return 'Job order';
        case 'awaiting':
            return 'Billing statement';
        case 'partial':
            return 'Partial payment';
        case 'fully_paid':
            return 'Full payment';
        case 'for_refund':
            return 'Refund request';
        case 'partially_refunded':
        case 'refunded':
            return 'Refund settled';
        default:
            return 'Documents';
    }
};
