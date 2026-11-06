import type {
    InquiryEditProject,
    InquiryMode,
    WorkService,
} from '@/lib/inquiry-flow';
import type { LeadTally } from '@/lib/lead-statuses';
import type { StoneCardData } from '@/types/stone';

/** A furniture type the Inquire page offers. `id` is its slug. */
export type InquiryFurnitureType = {
    id: string;
    name: string;
};

/** A stone the Inquire page offers, with what its search runs over. */
export type InquiryStoneOption = StoneCardData & {
    bestSeller: boolean;
    description: string;
    specifications: {
        stoneType: string;
        origin: string;
        size: string;
        thicknessMm: number;
    };
};

/** An edge profile or a finish. `isAdvisory` is the "advise me" answer. */
export type InquiryFinishingOption = {
    id: number;
    name: string;
    isAdvisory: boolean;
};

/**
 * The props `ViewController::inquire` sends, and `InquiryEditController::edit`
 * when the page edits a sent inquiry (`mode: 'edit'`, with its reference and
 * its projects as `InquiryEditResource` describes them).
 */
export type InquirePageProps = {
    services: WorkService[];
    furnitureTypes: InquiryFurnitureType[];
    stones: InquiryStoneOption[];
    edgeProfiles: InquiryFinishingOption[];
    finishes: InquiryFinishingOption[];
    locations: string[];
    mode?: InquiryMode;
    reference?: string;
    projects?: InquiryEditProject[];
    /**
     * Who is editing: the client from Quote, or staff from the Inquiry list,
     * who change the project only and attach no files.
     */
    editor?: 'client' | 'staff';
    /**
     * Staff only: the leads they may open an inquiry for (sales those with a
     * won inquiry, marketing its own). Null for a client, who sends their own.
     */
    leads?: InquiryLeadOption[] | null;
    /** The lead the URL names, when it is one of `leads`. */
    lead?: string | null;
};

/**
 * Who staff are opening the inquiry for, once the branch is answered: a
 * Platform client's lead, or a Direct (walk-in) client. The stepper posts to
 * `storeUrl` and takes no attachments.
 */
export type StaffInquireTarget = {
    kind: 'platform' | 'direct';
    /** The client's name. */
    name: string;
    /** The lead's reference, or "Direct (walk-in)". */
    detail: string;
    storeUrl: string;
};

/** A lead staff may open an inquiry for, as `LeadOptionResource` sends it. */
export type InquiryLeadOption = {
    reference: string;
    /** The tally of marketing's decisions on its inquiries. */
    tally: LeadTally;
    /** The client's account id. */
    clientId: number;
    /** The client's name. */
    client: string;
};

/**
 * What the Inquire flow reads, derived once from the page props.
 *
 * Everything is known by its slug, as the draft holds it.
 */
export type InquiryCatalog = {
    /** What the page offers, and any an inquiry being edited already has. */
    services: WorkService[];
    furnitureTypes: InquiryFurnitureType[];
    stones: InquiryStoneOption[];
    edgeProfiles: InquiryFinishingOption[];
    finishes: InquiryFinishingOption[];
    locations: string[];
    /** The "Not sure yet, advise me" answers, or null if none is offered. */
    advisoryEdge: string | null;
    advisoryFinish: string | null;
};
