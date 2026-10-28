/*
 * What each status track may move to, and what a move needs. Pure, with no
 * imports, for tests/js. The server applies the same rules
 * (App\Services\InquiryStatusRules) and has the last word: the dialog only
 * saves a round trip.
 */

export type StatusTrack = 'sales' | 'payment' | 'process';

export const TRACK_TARGETS: Record<StatusTrack, readonly string[]> = {
    sales: ['negotiation', 'site_visited', 'on_hold', 'quoted', 'rejected'],
    payment: ['awaiting', 'partial', 'fully_paid'],
    process: [
        'order_received',
        'measure',
        'sourcing',
        'production',
        'quality_check',
        'ready',
        'dispatched',
        'installation',
        'completed',
        'on_hold',
        'delayed',
    ],
};

/** Values a track never moves on from. */
const FINAL: Record<StatusTrack, readonly string[]> = {
    sales: ['rejected'],
    payment: ['fully_paid'],
    process: ['completed'],
};

/** Payment only moves forward. */
const PAYMENT_NEXT: Record<string, readonly string[]> = {
    '': ['awaiting'],
    awaiting: ['partial', 'fully_paid'],
    partial: ['fully_paid'],
    fully_paid: [],
};

/**
 * Where a track may move from `current`: never to where it already is, and
 * nowhere from a final value. Payment goes forward only, starting at awaiting.
 * Sales and logistics are started by the flow, not by hand (a won lead starts
 * sales, quoting starts logistics), so nothing is offered while they are null.
 */
export const allowedTargets = (
    track: StatusTrack,
    current: string | null,
): string[] => {
    if (current !== null && FINAL[track].includes(current)) {
        return [];
    }

    if (track === 'payment') {
        return [...(PAYMENT_NEXT[current ?? ''] ?? [])];
    }

    if (current === null) {
        return [];
    }

    return TRACK_TARGETS[track].filter((target) => target !== current);
};

export type DocumentSlot = {
    type:
        | 'quotation'
        | 'job_order'
        | 'billing_statement'
        | 'receipt'
        | 'invoice';
    label: string;
    /** More than one file allowed (receipts). */
    multiple: boolean;
    /** Whether the move needs it: the job order may come later. */
    required: boolean;
    /** Whether the client sees it on their inquiry. */
    clientVisible: boolean;
};

export type FileGroup = { label: string; slots: DocumentSlot[] };

const RECEIPTS: FileGroup = {
    label: 'Collection of receipts',
    slots: [
        {
            type: 'receipt',
            label: 'Receipts',
            multiple: true,
            required: true,
            clientVisible: true,
        },
        {
            type: 'invoice',
            label: 'Invoice',
            multiple: false,
            required: true,
            clientVisible: true,
        },
    ],
};

/**
 * The job order on its own, when sales uploads it after the quote: it starts
 * logistics at order_received.
 */
export const JOB_ORDER_GROUP: FileGroup = {
    label: 'Job order',
    slots: [
        {
            type: 'job_order',
            label: 'Job order (internal)',
            multiple: false,
            required: true,
            clientVisible: false,
        },
    ],
};

/** The files a move takes, one labelled group per update. */
export const requiredFileGroups = (
    track: StatusTrack,
    to: string,
): FileGroup[] => {
    if (track === 'sales' && to === 'quoted') {
        return [
            {
                label: 'Quotation',
                slots: [
                    {
                        type: 'quotation',
                        label: 'Quotation',
                        multiple: false,
                        required: true,
                        clientVisible: true,
                    },
                    {
                        type: 'job_order',
                        label: 'Job order (internal, optional)',
                        multiple: false,
                        required: false,
                        clientVisible: false,
                    },
                ],
            },
        ];
    }

    if (track === 'payment' && to === 'awaiting') {
        return [
            {
                label: 'Billing statement',
                slots: [
                    {
                        type: 'billing_statement',
                        label: 'Billing statement',
                        multiple: false,
                        required: true,
                        clientVisible: true,
                    },
                ],
            },
        ];
    }

    if (track === 'payment' && (to === 'partial' || to === 'fully_paid')) {
        return [RECEIPTS];
    }

    return [];
};

export type ExtraField = 'quotedTotal' | 'amountReceived';

/** The amount a move has to carry, if any. */
export const extraField = (
    track: StatusTrack,
    to: string,
): ExtraField | null => {
    if (track === 'sales' && to === 'quoted') {
        return 'quotedTotal';
    }

    if (track === 'payment' && (to === 'partial' || to === 'fully_paid')) {
        return 'amountReceived';
    }

    return null;
};

export const NOTE_MAX_LENGTH = 500;

/** A note is required: 1 to 500 characters once trimmed. */
export const isValidNote = (note: string): boolean => {
    const length = note.trim().length;

    return length >= 1 && length <= NOTE_MAX_LENGTH;
};

/** An amount above zero with at most two decimals. */
export const isValidAmount = (value: string): boolean =>
    /^\d+(\.\d{1,2})?$/.test(value.trim()) && Number(value) > 0;
