import type { QuoteActions } from '@/lib/quote-actions';
import type { QuoteFilters, QuoteStatus } from '@/lib/quote-filters';

/** One inquiry card, as `ClientInquiryResource` sends it. */
export type QuoteInquiry = {
    reference: string;
    status: QuoteStatus;
    createdAt: string;
    title: string;
    projectCount: number;
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
    status: QuoteStatus;
    createdAt: string;
    updatedAt: string;
    services: string[];
    statusHistory: Array<{ status: QuoteStatus; at: string }>;
    /** The inquiry's group chat, only when the viewer is in it. */
    conversation: { id: number; name: string } | null;
    projects: Array<{
        id: string;
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
    }>;
};
