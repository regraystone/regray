/**
 * The inquiry's statuses live in `@/lib/inquiry-statuses`, with what each is
 * called on screen. They are re-exported here so a record and its statuses
 * come from one place.
 */
import type {
    InquiryStatus,
    PaymentStatus,
    ProcessStatus,
    SalesStatus,
} from '@/lib/inquiry-statuses';

export type { InquiryStatus, PaymentStatus, ProcessStatus, SalesStatus };

/** Limits the details form holds its free text fields to. */
export const PROJECT_NAME_MAX = 100;
export const NOTES_MAX = 1000;
/**
 * An attachment as the details step holds it.
 *
 * The `File` is kept rather than a path because nothing has been sent anywhere
 * yet: there is no upload endpoint, so the bytes live in the browser until the
 * Review step decides what to do with them.
 */
export type InquiryUploadAttachment = {
    id: string;
    file: File;
    status: 'uploading' | 'done';
    /** 0-100, meaningful only while the status is `uploading`. */
    progress: number;
};

/**
 * A file already stored with the inquiry, when an inquiry is being edited.
 *
 * There are no bytes in the browser, only what the server said about the file.
 * Sending the edit keeps it by `fileId`; taking it off the list removes it.
 */
export type InquirySavedAttachment = {
    id: string;
    status: 'saved';
    /** The `inquiry_files` id. */
    fileId: number;
    name: string;
    sizeBytes: number;
    mimeType: string;
};

export type InquiryAttachment =
    | InquiryUploadAttachment
    | InquirySavedAttachment;

/** The name, size and type of either kind of attachment. */
export const attachmentMeta = (
    attachment: InquiryAttachment,
): { name: string; size: number; type: string } =>
    attachment.status === 'saved'
        ? {
              name: attachment.name,
              size: attachment.sizeBytes,
              type: attachment.mimeType,
          }
        : {
              name: attachment.file.name,
              size: attachment.file.size,
              type: attachment.file.type,
          };

/**
 * A file an inquiry carries, reduced to what a record can hold.
 *
 * The details form keeps the live `File` so it can show a thumbnail while the
 * browser still holds the bytes. A stored inquiry has neither: there is no
 * upload endpoint yet, so a record keeps the name, the size and the type, and
 * there is nothing left to download or preview from.
 */
export type InquiryFile = {
    id: string;
    name: string;
    sizeBytes: number;
    mimeType: string;
};
/**
 * The answers one project's requirements step collected, one field per question.
 *
 * Belongs to a project rather than to the inquiry for the same reason the stones
 * do: these are answers about a piece of work. A countertop wanted in Makati by
 * November and a floor wanted in Cebu by March are one request and two different
 * answers to the same four questions, and a record that kept one set of them
 * would have had to invent a site for half the work.
 *
 * There was an inquiry-wide Details step that asked all of this once, over every
 * project at the same time. It asked the right questions in the wrong place.
 */
export type InquiryRequirementsRecord = {
    deliveryMethod?: string;
    /** "Pickup", "Delivery to {address}" or "Installation at {address}". */
    fulfillment?: string;
    neededBy?: string;
    /**
     * The hour of the day, asked for alongside the date by the three services the
     * team attends at one. A bare `HH:mm` rather than a full timestamp, so it
     * reads with whatever date sits beside it and cannot disagree with it.
     */
    notes?: string;
    attachments: InquiryFile[];
};

/**
 * One stone inside a project: which stone, how many of it, and how it is cut.
 *
 * The edge and the finish travel with the stone rather than sitting on the
 * inquiry, because the same stone is worked differently in two parts of the same
 * building and a client who wants a honed floor and a polished counter is saying
 * two real things, not contradicting themselves.
 */
export type InquiryStoneSelection = {
    stoneId: string;
    /** Pieces of this stone the client asked for. Always at least one. */
    quantity: number;
    edge: string;
    finish: string;
};

/**
 * One inquiry, which is one project: the stones, the services wanted for them,
 * how each is cut, where and when it has to happen, and the statuses it has
 * reached. A request with several projects is sent as one inquiry each.
 *
 * `services` are names rather than ids: the inquiry list puts them straight
 * onto badges and never looks them up. The inquiry form works in ids, because
 * that is what the picker hands it.
 *
 * A project with no stones is a real shape: the services the team attends at
 * an hour are booked on their own, with nothing to supply or install.
 */
export type Inquiry = {
    id: string;
    reference: string;
    /** What the client called the work: "Kitchen counters", "Pool deck". */
    name: string;
    /** The one status shown for the inquiry, worked out from its lead and the three below. */
    status: InquiryStatus;
    /** Null until the inquiry's lead is won. */
    salesStatus: SalesStatus | null;
    /** Null, with `processStatus`, until the client approves the quotation. */
    paymentStatus: PaymentStatus | null;
    processStatus: ProcessStatus | null;
    createdAt: string;
    clientId: string;
    /**
     * The team members it sits with. More than one where the work needs more
     * than one pair of hands, and empty while nobody has picked it up.
     */
    handledByIds: string[];
    services: string[];
    /** In the order the client picked them. */
    stones: InquiryStoneSelection[];
    /** Where the work is, when it is wanted, and what else came with the request. */
    requirements: InquiryRequirementsRecord;
};

/** The name an inquiry is known by: its project's, or the reference when that is blank. */
export const inquiryTitle = ({ name, reference }: Inquiry): string =>
    name.trim() !== '' ? name.trim() : `Inquiry ${reference}`;

/**
 * Sample inquiries, one per stage worth seeing: one pending, one in
 * negotiation, one approved, one in production, one closed and one cancelled.
 * Listed newest first by `createdAt`.
 */
export const inquiries: Inquiry[] = [
    {
        id: 'inq-1050',
        reference: 'INQ-1050',
        name: 'Maple Lane — Kitchen Island',
        status: 'pending',
        salesStatus: null,
        paymentStatus: null,
        processStatus: null,
        createdAt: '2026-10-26T02:00:00.000Z',
        clientId: 'cl-grace',
        handledByIds: [],
        services: ['Custom Stone Fabrication', 'Natural Stone Installation'],
        stones: [
            {
                stoneId: 'cosmic-black',
                quantity: 3,
                edge: 'Half Bullnose',
                finish: 'Polished',
            },
        ],
        requirements: {
            fulfillment: 'Delivery to Quezon City',
            neededBy: '2026-12-05',
            notes: 'The island has a waterfall end on the side facing the dining room.',
            attachments: [],
        },
    },
    {
        id: 'inq-1053',
        reference: 'INQ-1053',
        name: 'The Grove — Guest Bath Vanity',
        status: 'quoted',
        salesStatus: 'quoted',
        paymentStatus: 'awaiting',
        processStatus: 'order_received',
        createdAt: '2026-10-14T02:00:00.000Z',
        clientId: 'cl-haruki',
        handledByIds: ['sofia'],
        services: ['Custom Stone Fabrication', 'Natural Stone Installation'],
        stones: [
            {
                stoneId: 'avocado-green-iii',
                quantity: 2,
                edge: 'Reverse Chamfer',
                finish: 'Honed',
            },
        ],
        requirements: {
            fulfillment: 'Delivery to Muntinlupa',
            neededBy: '2026-11-28',
            attachments: [],
        },
    },
    {
        id: 'inq-1048',
        reference: 'INQ-1048',
        name: 'Northgate Cafe — Counters and Flooring',
        status: 'negotiation',
        salesStatus: 'negotiation',
        paymentStatus: null,
        processStatus: null,
        createdAt: '2026-09-27T03:40:00.000Z',
        clientId: 'cl-rafael',
        handledByIds: ['daniel', 'maria'],
        services: ['Custom Stone Fabrication', 'Natural Stone Installation'],
        stones: [
            {
                stoneId: 'avocado-green-iii',
                quantity: 7,
                edge: 'Half Bevel',
                finish: 'Leather',
            },
            {
                stoneId: 'avocado-green-iii',
                quantity: 3,
                edge: 'Straight Edge',
                finish: 'Polished',
            },
        ],
        requirements: {
            fulfillment: 'Delivery to Makati',
            neededBy: '2026-11-14',
            notes: 'The bar top has to clear the service window by 60 mm.',
            attachments: [
                {
                    id: 'file-1',
                    name: 'northgate-bar-layout.pdf',
                    sizeBytes: 1284096,
                    mimeType: 'application/pdf',
                },
            ],
        },
    },
    {
        id: 'inq-1046',
        reference: 'INQ-1046',
        name: 'Brightleaf Studio — Reception Desk',
        status: 'production',
        salesStatus: 'quoted',
        paymentStatus: 'fully_paid',
        processStatus: 'production',
        createdAt: '2026-09-20T02:30:00.000Z',
        clientId: 'cl-elena',
        handledByIds: ['daniel'],
        services: ['Custom Stone Fabrication', 'Natural Stone Installation'],
        stones: [
            {
                stoneId: 'cosmic-black',
                quantity: 2,
                edge: 'Half Bevel',
                finish: 'Polished',
            },
        ],
        requirements: {
            fulfillment: 'Delivery to Pasig',
            neededBy: '2026-10-30',
            notes: 'The desk front is one slab, so please keep the veining running across the full width.',
            attachments: [
                {
                    id: 'file-1',
                    name: 'brightleaf-reception-desk.pdf',
                    sizeBytes: 812544,
                    mimeType: 'application/pdf',
                },
            ],
        },
    },
    {
        id: 'inq-1041',
        reference: 'INQ-1041',
        name: 'The Grove — Outdoor Kitchen and Deck',
        status: 'closed',
        salesStatus: 'quoted',
        paymentStatus: 'fully_paid',
        processStatus: 'completed',
        createdAt: '2026-08-28T05:45:00.000Z',
        clientId: 'cl-haruki',
        handledByIds: ['daniel', 'sofia'],
        services: [
            'Natural Stone Supply',
            'Custom Stone Fabrication',
            'Natural Stone Installation',
        ],
        stones: [
            {
                stoneId: 'cosmic-black',
                quantity: 6,
                edge: 'Full Bevel',
                finish: 'Leather',
            },
        ],
        requirements: {
            fulfillment: 'Delivery to Muntinlupa',
            neededBy: '2026-09-05',
            attachments: [
                {
                    id: 'file-1',
                    name: 'grove-outdoor-kitchen-plan.pdf',
                    sizeBytes: 2411724,
                    mimeType: 'application/pdf',
                },
            ],
        },
    },
    {
        id: 'inq-1039',
        reference: 'INQ-1039',
        name: 'Maple Lane - Laundry',
        status: 'cancelled',
        salesStatus: 'rejected',
        paymentStatus: null,
        processStatus: null,
        createdAt: '2026-08-12T06:55:00.000Z',
        clientId: 'cl-grace',
        handledByIds: ['sofia'],
        services: ['Stone Relocation, Dismantling & Reinstallation'],
        stones: [],
        requirements: {
            fulfillment: 'Delivery to Quezon City',
            neededBy: '2026-08-20',
            attachments: [],
        },
    },
];
