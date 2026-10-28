import type {
    ActivityEntry,
    InquiryStatus,
    PaymentStatus,
    ProcessStatus,
    SalesStatus,
    SharedDocumentType,
} from '@/lib/inquiry-statuses';
import type { LeadStatus } from '@/lib/lead-statuses';
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
    /**
     * The client's activity log, oldest first: every change to sales, payment
     * and logistics, a stage starting included, and `inquiry` `cancelled` when
     * the inquiry was cancelled with its lead. Nothing about the lead itself.
     */
    history: Array<ActivityEntry & { at: string }>;
    /**
     * The documents the team has shared, one group per status update, oldest
     * first: each quotation, the billing statement, and each payment's
     * receipts and invoice. The job order is internal and never sent.
     */
    documents: Array<{
        id: string;
        /** The update the files came with; null for files with none. */
        status: string | null;
        revision: number | null;
        at: string;
        files: Array<{
            id: string;
            type: SharedDocumentType;
            name: string;
            sizeBytes: number;
            mimeType: string;
        }>;
    }>;
    /** The inquiry's group chat, only when the viewer is in it. */
    conversation: { id: number; name: string } | null;
    /** Its lead's reference and status: staff only, never sent to a client. */
    lead?: { reference: string; status: LeadStatus } | null;
    /** The one project the inquiry is. */
    project: {
        name: string;
        services: Array<{ name: string; image: string }>;
        applications: string[];
        stones: Array<{
            id: string;
            name: string;
            thumbnail: string;
            quantity: number;
            edge: string;
            finish: string;
        }>;
        requirements: {
            location: string;
            landmark: string | null;
            areaSqm: number | null;
            neededBy: string | null;
            neededByTime: string | null;
            notes: string | null;
            attachments: Array<{
                id: string;
                name: string;
                sizeBytes: number;
                mimeType: string;
            }>;
        };
    };
};
