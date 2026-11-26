import type {
    ActivityEntry,
    InquiryStatus,
    PaymentStatus,
    ProcessStatus,
    SalesStatus,
    SharedDocumentType,
} from '@/lib/inquiry-statuses';
import type { PhAddressPayload } from '@/lib/ph-address';
import type { QuoteActions } from '@/lib/quote-actions';
import type { QuoteFilters } from '@/lib/quote-filters';

/** One inquiry card, as `ClientInquiryResource` sends it. */
export type QuoteInquiry = {
    reference: string;
    /** The display status: the one status shown for the inquiry. */
    status: InquiryStatus;
    /** Null until accounting's stage starts, when the client approves the quotation. */
    paymentStatus: PaymentStatus | null;
    createdAt: string;
    title: string;
    stones: Array<{ slug: string; name: string; thumbnail: string }>;
    /** The service it asks for, when it has one. */
    services: Array<{ id: string; name: string }>;
    /** Which buttons the card shows, and which can be pressed. */
    actions: QuoteActions;
};

export type QuotePageProps = {
    /** Absent only before the first response has arrived. */
    inquiries?: { data: QuoteInquiry[] };
    hasMore: boolean;
    filters: QuoteFilters;
    statusOptions: string[];
};

/**
 * One stored file on the details page: a client attachment or a document the
 * team shared. `url` previews it; with `?download=1` it is saved instead.
 */
export type StoredInquiryFile = {
    id: string;
    /** The uploader's own file name, for display only. */
    name: string;
    sizeBytes: number;
    mimeType: string;
    url: string;
    /** Whether the browser may show it in a tab; otherwise it is download-only. */
    previewable: boolean;
    /** When the file was uploaded, not when its status update was. */
    uploadedAt: string;
    /** Such as "PDF" or "JPG". */
    format: string;
};

/** One inquiry on the details page, as `InquiryDetailsResource` sends it. */
export type QuoteInquiryDetails = {
    reference: string;
    /** The display status: the one status shown for the inquiry. */
    status: InquiryStatus;
    /** Null until the inquiry's lead is won. */
    salesStatus: SalesStatus | null;
    /** Null, with `processStatus`, until the client approves the quotation. */
    paymentStatus: PaymentStatus | null;
    processStatus: ProcessStatus | null;
    createdAt: string;
    /** The total contract amount, such as "1250.00", once accounting has billed it. */
    contractTotal: string | null;
    /**
     * The client's activity log, oldest first: every change to sales, payment
     * and logistics, a stage starting included, and `inquiry` `cancelled` when
     * the inquiry was cancelled with its lead. Nothing about the lead itself.
     */
    history: Array<ActivityEntry & { at: string }>;
    /**
     * The payment records, oldest first: the total contract amount billed when
     * payment moved to awaiting, then each payment (or refund): its amount,
     * the balance owed before it and the balance remaining after it. Empty
     * until payment has started. Amounts are strings such as "1250.00".
     */
    payments: Array<{
        id: string;
        kind: 'contract' | 'payment' | 'refund';
        amount: string;
        /** What was owed before this payment (for a refund, what was held); null on the contract entry. */
        currentBalance: string | null;
        /** What is owed after it (for a refund, what is still held); null on the contract entry. */
        remainingBalance: string | null;
        at: string;
    }>;
    /**
     * The documents the team has shared, one group per status update, oldest
     * first: each quotation, the billing statement, and each payment's
     * receipts and invoice. Staff are also sent the job order, which is
     * internal and never sent to the client.
     */
    documents: Array<{
        id: string;
        /** The update the files came with; null for files with none. */
        status: string | null;
        revision: number | null;
        at: string;
        files: Array<StoredInquiryFile & { type: SharedDocumentType }>;
    }>;
    /**
     * The staff handling the inquiry now, first assigned first, then by name.
     * Empty until anyone is assigned. A name and a role only.
     */
    assignedStaff: Array<{
        name: string;
        /** Such as "Sales Representative"; null for a role with no label. */
        roleLabel: string | null;
    }>;
    /** The inquiry's group chat, only when the viewer is in it. */
    conversation: { id: number; name: string } | null;
    /**
     * A walk-in's inquiry, for staff: the walk-in's name, phone and email, its
     * sales owner, and what this viewer may do: whom they may assign, by role
     * (`technical`, `accounting`, `logistics`; a role is absent where they
     * may not), and whether they may decide it. Null on a client's inquiry.
     */
    walkIn?: WalkInDetails | null;
    /** Which buttons the page shows, and which can be pressed. */
    actions: QuoteActions;
    /** The one project the inquiry is. */
    project: {
        name: string;
        stoneAdviceRequested: boolean;
        /** The brands the client would like, in the brands' order; empty with no preference. */
        brands: Array<{ id: string; name: string }>;
        brandNoPreference: boolean;
        serviceType: string | null;
        service: string | null;
        /** False for a service that asks for no stones, which has no Stones section. */
        requiresStones: boolean;
        stones: Array<{
            id: string;
            name: string;
            thumbnail: string;
            /** Null where the service does not ask how many pieces. */
            quantity: number | null;
            /** "160 x 320 cm"; null where the service does not ask it. */
            slabSize: string | null;
            /** "20 mm"; null where the service does not ask it. */
            thickness: string | null;
            /** Whether the size and thickness are one of the stone's slabs; null where no thickness is asked. */
            isStandardSlab: boolean | null;
            edge: string | null;
            finish: string | null;
            /** The stone-scope answers its service asks, labelled, in order. */
            details: Array<{ label: string; value: string }>;
        }>;
        /** The project-scope answers, labelled and already worded; a picture choice with its picture. */
        answers: Array<{ label: string; value: string; image?: string | null }>;
        requirements: {
            /** Null for an installed service, which asks no method. */
            deliveryMethod: string | null;
            /** The copy kept with the inquiry; null on a pickup. */
            address: PhAddressPayload | null;
            /** "Pickup", "Delivery to {address}" or "Installation at {address}". */
            fulfillment: string;
            neededBy: string | null;
            notes: string | null;
            attachments: StoredInquiryFile[];
        };
    };
};

export type WalkInRole = 'technical' | 'accounting' | 'logistics';

export type WalkInDetails = {
    client: { name: string; phone: string | null; email: string | null };
    owner: string | null;
    marketingStatus: 'won' | 'lost' | null;
    assignable: Partial<
        Record<WalkInRole, Array<{ id: number; name: string }>>
    >;
    canDecide: boolean;
};
