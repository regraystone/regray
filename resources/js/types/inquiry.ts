import type { InquiryEditProject, InquiryMode } from '@/lib/inquiry-flow';
import type { StoneCardData } from '@/types/stone';

/** A service the Inquire page offers. `id` is its slug. */
export type InquiryServiceOption = {
    id: string;
    name: string;
    features: string[];
    image: string;
    standalone: boolean;
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
