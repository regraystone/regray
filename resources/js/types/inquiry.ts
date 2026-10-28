import type { InquiryEditProject, InquiryMode } from '@/lib/inquiry-flow';
import type { LeadStatus } from '@/lib/lead-statuses';
import type { StoneCardData } from '@/types/stone';

/** A service the Inquire page offers. `id` is its slug. */
export type InquiryServiceOption = {
    id: string;
    name: string;
    features: string[];
    image: string;
    standalone: boolean;
    /** Services share a project only when they share a group. */
    group: string;
};

/** A stone the Inquire page offers, with what its search runs over. */
export type InquiryStoneOption = StoneCardData & {
    description: string;
    specifications: {
        stoneType: string;
        origin: string;
        size: string;
        thicknessMm: number;
    };
};

/** An option of the Application step: a project category, `id` being its slug. */
export type InquiryApplicationOption = {
    id: string;
    name: string;
    image: string;
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
    services: InquiryServiceOption[];
    stones: InquiryStoneOption[];
    applications: InquiryApplicationOption[];
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
     * Staff only: the leads they may open an inquiry for (sales won leads,
     * marketing its own idle or won leads). Null for a client, who sends their
     * own.
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
    status: LeadStatus;
    /** The client's account id. */
    clientId: number;
    /** The client's name. */
    client: string;
};

/**
 * What the Inquire flow reads, derived once from the page props.
 *
 * Applications' ids are strings here because the draft holds them as strings,
 * the same as service and stone ids.
 */
export type InquiryCatalog = {
    services: InquiryServiceOption[];
    stones: InquiryStoneOption[];
    applications: InquiryApplicationOption[];
    edgeProfiles: InquiryFinishingOption[];
    finishes: InquiryFinishingOption[];
    locations: string[];
    standaloneServiceIds: ReadonlySet<string>;
    /** The "Not sure yet, advise me" answers, or null if none is offered. */
    advisoryEdge: string | null;
    advisoryFinish: string | null;
};
