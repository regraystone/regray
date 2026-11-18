import type {
    ClientInformationPageProps,
    ClientInformationValues,
} from '@/lib/client-information';
import type {
    InquiryEditProject,
    InquiryMode,
    ServiceTypeOption,
    StoneFilter,
} from '@/lib/inquiry-flow';
import type { LeadTally } from '@/lib/lead-statuses';
import type { StoneCardData } from '@/types/stone';

/** A stone the Inquire page offers. */
export type InquiryStoneOption = StoneCardData;

/** Which page of stones the server answered with. */
export type InquiryStoneFilters = {
    /** The service the stones are for; null sends none. */
    service: string | null;
    tab: StoneFilter;
    type: string | null;
    search: string | null;
    perPage: number;
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
    serviceTypes: ServiceTypeOption[];
    /** One page of stones at a time, added to as the reader scrolls. */
    stones: { data: InquiryStoneOption[] };
    stoneTypes: Array<{ value: string; label: string }>;
    stoneFilters: InquiryStoneFilters;
    /** The stones of a restored draft, when asked for by slug. */
    pickedStones?: InquiryStoneOption[];
    edgeProfiles: InquiryFinishingOption[];
    finishes: InquiryFinishingOption[];
    locations: string[];
    /**
     * A client sending their own inquiry: their saved details and what a new
     * form starts from. On edit, the flat snapshot of what was sent instead.
     * Absent on staff flows, which have no new required fields and never
     * prefill from a Profile.
     */
    clientInformation?:
        | ClientInformationPageProps
        | ClientInformationValues
        | null;
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
    serviceTypes: ServiceTypeOption[];
    /** The stones the page has been sent so far. */
    stones: InquiryStoneOption[];
    /**
     * The picked stones this service does not offer, by the rule the server
     * uses: a stone fits when its primary type is one the service is made from.
     * Restored drafts, held stones and service changes all ask this.
     */
    stonesNotOfferedFor: (
        stoneIds: readonly string[],
        serviceId: string,
    ) => InquiryStoneOption[];
    edgeProfiles: InquiryFinishingOption[];
    finishes: InquiryFinishingOption[];
    locations: string[];
    /** The "Not sure yet, advise me" answers, or null if none is offered. */
    advisoryEdge: string | null;
    advisoryFinish: string | null;
};
