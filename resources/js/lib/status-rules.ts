/*
 * What each status track may move to, and what a move needs. Pure, with no
 * imports, for tests/js. The server applies the same rules
 * (App\Services\InquiryStatusRules) and has the last word: the dialog only
 * saves a round trip.
 */

export type StatusTrack = 'sales' | 'payment' | 'process';

export const TRACK_TARGETS: Record<StatusTrack, readonly string[]> = {
    sales: [
        'negotiation',
        'site_visited',
        'on_hold',
        'quoted',
        'revision',
        'won',
        'rejected',
        'for_refund',
    ],
    payment: [
        'awaiting',
        'partial',
        'fully_paid',
        'partially_refunded',
        'refunded',
    ],
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
    sales: ['rejected', 'for_refund'],
    payment: ['fully_paid', 'refunded'],
    process: ['completed'],
};

/** Payment only moves forward. */
const PAYMENT_NEXT: Record<string, readonly string[]> = {
    '': ['awaiting'],
    awaiting: ['partial', 'fully_paid'],
    partial: ['fully_paid'],
    fully_paid: [],
};

/** Payment's moves once sales has asked for a refund. */
const REFUND_NEXT: Record<string, readonly string[]> = {
    '': ['partially_refunded', 'refunded'],
    awaiting: ['partially_refunded', 'refunded'],
    partial: ['partially_refunded', 'refunded'],
    fully_paid: ['partially_refunded', 'refunded'],
    partially_refunded: ['refunded'],
    refunded: [],
};

/**
 * Where a track may move from `current`: never to where it already is (but for
 * a revision after a revision), and nowhere from a final value. Payment goes
 * forward only, starting at awaiting. Sales and logistics are started by the
 * flow, not by hand (a won lead starts sales; the first payment, with the job
 * order in, starts logistics), so nothing is offered while they are null.
 * Sales' revision and won wait for a quotation to have been sent
 * (`hasBeenQuoted`). Once won, sales only moves to for refund, and only while
 * one can be made (`refundable`: a payment is in and the project is not done);
 * payment then only moves to refunds (`sales` is the inquiry's sales status).
 */
export const allowedTargets = (
    track: StatusTrack,
    current: string | null,
    hasBeenQuoted = false,
    sales: string | null = null,
    refundable = true,
): string[] => {
    if (track === 'payment' && sales === 'for_refund') {
        return [...(REFUND_NEXT[current ?? ''] ?? [])];
    }

    if (current !== null && FINAL[track].includes(current)) {
        return [];
    }

    if (track === 'payment') {
        return [...(PAYMENT_NEXT[current ?? ''] ?? [])];
    }

    if (current === null) {
        return [];
    }

    if (track !== 'sales') {
        return TRACK_TARGETS[track].filter((target) => target !== current);
    }

    // Once won, the only way on is a refund, and only while one can be made.
    if (current === 'won') {
        return refundable ? ['for_refund'] : [];
    }

    return TRACK_TARGETS.sales.filter(
        (target) =>
            (target !== current || target === 'revision') &&
            target !== 'for_refund' &&
            // A revision revises, and the client approves, a quotation they have been sent.
            (hasBeenQuoted || (target !== 'revision' && target !== 'won')),
    );
};

/**
 * Whether sales may ask for a refund: a partial or full payment is in, and
 * logistics has not completed the project.
 */
export const canRequestRefund = (
    payment: string | null,
    process: string | null,
): boolean =>
    (payment === 'partial' || payment === 'fully_paid') &&
    process !== 'completed';

export type DocumentSlot = {
    type:
        | 'quotation'
        | 'job_order'
        | 'billing_statement'
        | 'receipt'
        | 'invoice'
        | 'refund_request'
        | 'refund_settlement';
    label: string;
    /** More than one file allowed (receipts). */
    multiple: boolean;
    /** Whether the move needs it. */
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
 * The job order on its own, when sales uploads it after the win: it starts
 * logistics at order_received.
 */
export const JOB_ORDER_GROUP: FileGroup = {
    label: 'Job order',
    slots: [
        {
            type: 'job_order',
            label: 'Job order',
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
    if (track === 'sales' && (to === 'quoted' || to === 'revision')) {
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
                ],
            },
        ];
    }

    if (track === 'sales' && to === 'won') {
        return [
            {
                label: 'Job order',
                slots: [
                    {
                        type: 'job_order',
                        label: 'Job order',
                        multiple: false,
                        required: true,
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

    if (track === 'sales' && to === 'for_refund') {
        return [
            {
                label: 'Refund request',
                slots: [
                    {
                        type: 'refund_request',
                        label: 'Refund request form',
                        multiple: false,
                        required: true,
                        clientVisible: true,
                    },
                ],
            },
        ];
    }

    if (
        track === 'payment' &&
        (to === 'partially_refunded' || to === 'refunded')
    ) {
        return [
            {
                label: 'Refund settled',
                slots: [
                    {
                        type: 'refund_settlement',
                        label: 'Refund settled',
                        multiple: false,
                        required: true,
                        clientVisible: true,
                    },
                ],
            },
        ];
    }

    return [];
};

export type ExtraField =
    | 'quotedTotal'
    | 'contractTotal'
    | 'amountReceived'
    | 'amountRefunded';

/** The amount a move has to carry, if any. */
export const extraField = (
    track: StatusTrack,
    to: string,
): ExtraField | null => {
    if (track === 'sales' && (to === 'quoted' || to === 'revision')) {
        return 'quotedTotal';
    }

    if (track === 'payment' && to === 'awaiting') {
        return 'contractTotal';
    }

    if (track === 'payment' && (to === 'partial' || to === 'fully_paid')) {
        return 'amountReceived';
    }

    if (
        track === 'payment' &&
        (to === 'partially_refunded' || to === 'refunded')
    ) {
        return 'amountRefunded';
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
