import type { DataTableFilters } from '@/components/data-table/data-table';
import type { TableMeta } from '@/lib/data-table';

/** How a service's work reaches the client (`App\Enums\Fulfillment`). */
export type Fulfillment = 'delivery_or_pickup' | 'installed';

/** What kind of answer a question takes (`App\Enums\DetailAnswerType`). */
export type AnswerType = 'text' | 'number' | 'choice' | 'boolean';

/** A question is answered once for the project, or on each stone row. */
export type QuestionScope = 'project' | 'stone';

/** One question a service asks, in its order, as `AdminServiceResource` sends it. */
export type ServiceQuestion = {
    id: number;
    name: string;
    scope: QuestionScope;
    required: boolean;
};

/** One service as a row of the admin's All services page. */
export type ServiceRow = {
    id: number;
    slug: string;
    name: string;
    serviceTypeId: number;
    description: string;
    requiresStones: boolean;
    fulfillment: Fulfillment;
    /** Visible on the Inquire page, as the admin set it. */
    isActive: boolean;
    /** Whether the Inquire page offers it now: visible, its type visible, and made from a stone type when it needs stones. */
    isOffered: boolean;
    sortOrder: number;
    features: string[];
    stoneTypes: { id: number; name: string }[];
    questions: ServiceQuestion[];
    inquiriesCount: number;
    createdAt: string;
    updatedAt: string;
    /** The actions this reader has on this service, decided by the server. */
    actions: string[];
    /** Whether this reader may flip its switch. */
    canToggle: boolean;
};

/** A service type a service can be under, hidden ones too. */
export type ServiceTypeOption = {
    id: number;
    slug: string;
    name: string;
    isActive: boolean;
};

/** A question a service can ask, from the catalog, with its listed answers. */
export type QuestionOption = {
    id: number;
    name: string;
    scope: QuestionScope;
    answerType: AnswerType;
    options: string[];
};

export type ServiceLimits = {
    descriptionMax: number;
    featuresMax: number;
    featureMax: number;
};

export type ServiceFilters = DataTableFilters & {
    /** A service type's slug, or `all`. */
    type: string;
    status: 'all' | 'active' | 'inactive';
};

export type ServicesPageProps = {
    services: { data: ServiceRow[]; meta: TableMeta };
    filters: ServiceFilters;
    serviceTypes: ServiceTypeOption[];
    stoneTypes: { id: number; name: string }[];
    questions: QuestionOption[];
    canCreate: boolean;
    limits: ServiceLimits;
};
