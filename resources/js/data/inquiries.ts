/**
 * Where an inquiry is in the quote flow.
 *
 * `'draft'` is no longer a status an inquiry can sit at — a draft now lives in
 * the reader's own browser while they build it, and is gone once sent. It is
 * kept in the type only because it is a real past stage: every timeline starts
 * with one, so `statusHistory` entries and the labels they are read through
 * still have somewhere to put it.
 */

export type InquiryStatus =
    | 'draft'
    | 'pending'
    | 'quoted'
    | 'paid'
    | 'completed'
    | 'cancelled';
/** Limits the details form holds its free text fields to. */
export const PROJECT_NAME_MAX = 100;
export const LANDMARK_MAX = 150;
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
    location: string;
    landmark?: string;
    areaSqm?: number;
    neededBy?: string;
    /**
     * The hour of the day, asked for alongside the date by the three services the
     * team attends at one. A bare `HH:mm` rather than a full timestamp, so it
     * reads with whatever date sits beside it and cannot disagree with it.
     */
    neededByTime?: string;
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
 * One piece of work: the stones, the services wanted for them, how each is cut,
 * and where and when it has to happen.
 *
 * `services` are names rather than ids, as `Inquiry.services` already is: the
 * inquiry list puts them straight onto badges and never looks them up. The
 * inquiry form works in ids, because that is what the picker hands it.
 *
 * Everything here is the project's own answer, and everything the reader enters
 * in the requirements step lands here. A project with no stones is a real shape:
 * the three services the team attends at an hour are booked on their own, with
 * nothing to supply or install, so a project is not defined by having stones.
 */
type InquiryProject = {
    id: string;
    /**
     * What the client called this piece of work — "Kitchen counters", "Pool deck".
     *
     * Every project carries one of these because the reader names them as they go,
     * and because the first one is what the whole request is known by.
     */
    name: string;
    services: string[];
    /** In the order the client picked them. */
    stones: InquiryStoneSelection[];
    /** Application names, in the order the client picked them. Optional for standalone services. */
    applications?: string[];
    /** Where the work is, when it is wanted, and what else came with the request. */
    requirements: InquiryRequirementsRecord;
};

type InquiryStatusChange = {
    status: InquiryStatus;
    /** When the status moved to this one, which is all a timeline entry says. */
    at: string;
};

export type Inquiry = {
    id: string;
    reference: string;
    status: InquiryStatus;
    createdAt: string;
    /** When the status last moved, which is the last entry of `statusHistory`. */
    updatedAt: string;
    clientId: string;
    /**
     * The team members it sits with. More than one where the work needs more
     * than one pair of hands — a sales officer who raised it and the technical
     * officer who will cut it — and empty while nobody has picked it up.
     */
    handledByIds: string[];
    /** One per piece of work, in the order the client added them. */
    projects: InquiryProject[];
    /**
     * Every service across every project, deduplicated. Kept rather than read off
     * `projects` so the inquiry list's badges have one answer to show and do not
     * each have to walk the projects to count them.
     */
    services: string[];
    /** Oldest first, so the newest change is the last entry. */
    statusHistory: InquiryStatusChange[];
};

/**
 * The name a request is known by, everywhere it is listed or announced.
 *
 * Read off the projects rather than stored beside them, because the reader names
 * each project as they build it and there is no separate moment at which the
 * request gets a name of its own. The first project stands in for the request,
 * which is how a client talks about it too — the main job, then the rest — and
 * the reference does when there is no project to take a name from.
 */
export const inquiryTitle = ({ projects, reference }: Inquiry): string =>
    projects.find((project) => project.name.trim() !== '')?.name.trim() ??
    `Inquiry ${reference}`;
/**
 * One sample inquiry per status, so every stage in the quote flow is visible
 * in the list without the statuses crowding each other out. Between them the
 * five cover eight of the nine stones in `stones`. Listed newest first by
 * `createdAt`.
 *
 * The optional answers are left out in different places across the five so that
 * every state the details page can render for them is reachable from the sample
 * data: the cancelled one carries a time with no date, the completed one a
 * landmark, notes several paragraphs long and four attachments, and the rest
 * sit in between.
 *
 * Two of the five carry a second project, and one of those uses the same stone in
 * both with a different edge and finish, so a page that reads the projects
 * rather than the inquiry's stones has something to show.
 */
export const inquiries: Inquiry[] = [
    {
        id: 'inq-1048',
        reference: 'INQ-1048',
        status: 'pending',
        createdAt: '2026-09-27T03:40:00.000Z',
        updatedAt: '2026-09-28T06:15:00.000Z',
        clientId: 'cl-rafael',
        handledByIds: ['daniel', 'maria'],
        projects: [
            {
                /*
                 * The same stone in two projects, cut two different ways: the bar
                 * top is eased and leathered, the dining floor is squared off and
                 * polished. Under one flat list of stones there would have been no
                 * way to say so.
                 */
                id: 'prj-1048-1',
                name: 'Northgate Cafe — Counters and Flooring',
                applications: ['Countertops & Backsplash'],
                services: [
                    'Site Ocular & Technical Assessment',
                    'Custom Stone Fabrication',
                    'Natural Stone Installation',
                ],
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
                    {
                        stoneId: 'cosmic-black',
                        quantity: 4,
                        edge: 'Half Bevel',
                        finish: 'Leather',
                    },
                    {
                        stoneId: 'bronze-sand-granite',
                        quantity: 2,
                        edge: 'Half Bevel',
                        finish: 'Leather',
                    },
                ],
                /*
                 * The bar layout and the note about clearing the service window
                 * are about the counters, so they sit with the counters. The
                 * floor is a separate booking with its own date — it has to be
                 * down before anything is set on top of it.
                 */
                requirements: {
                    location: 'Makati',
                    landmark: 'Second floor, beside the mezzanine staircase',
                    areaSqm: 18.5,
                    neededBy: '2026-11-14',
                    notes: 'The bar top has to clear the service window by 60 mm.\nPlease keep the seam out of the sight line from the entrance.',
                    attachments: [
                        {
                            id: 'file-inq-1048-1',
                            name: 'northgate-bar-layout.pdf',
                            sizeBytes: 1284096,
                            mimeType: 'application/pdf',
                        },
                        {
                            id: 'file-inq-1048-2',
                            name: 'exposed-concrete-photo.png',
                            sizeBytes: 486400,
                            mimeType: 'image/png',
                        },
                    ],
                },
            },
            {
                id: 'prj-1048-2',
                name: 'Northgate Cafe — Dining Floor',
                applications: ['Dining Tables'],
                services: [
                    'Custom Stone Fabrication',
                    'Natural Stone Installation',
                ],
                stones: [
                    {
                        stoneId: 'avocado-green-iii',
                        quantity: 9,
                        edge: 'Straight Edge',
                        finish: 'Polished',
                    },
                ],
                requirements: {
                    location: 'Makati',
                    landmark: 'Ground floor, under the window wall',
                    areaSqm: 26,
                    neededBy: '2026-10-24',
                    notes: 'Wanted square to the window wall so the joints run with it.\nPlease do the floor before the counters are set.',
                    attachments: [],
                },
            },
        ],
        services: [
            'Site Ocular & Technical Assessment',
            'Custom Stone Fabrication',
            'Natural Stone Installation',
        ],
        statusHistory: [
            { status: 'draft', at: '2026-09-27T03:40:00.000Z' },
            {
                status: 'pending',
                at: '2026-09-28T06:15:00.000Z',
            },
        ],
    },
    {
        id: 'inq-1044',
        reference: 'INQ-1044',
        status: 'quoted',
        createdAt: '2026-09-15T04:50:00.000Z',
        updatedAt: '2026-09-19T09:30:00.000Z',
        clientId: 'cl-nadine',
        handledByIds: ['maria'],
        projects: [
            {
                id: 'prj-1044-1',
                name: 'Garden Court — Vanity Tops',
                applications: ['Lavatories'],
                services: [
                    'Design & Project Consultation',
                    'Post-Installation Quality Care',
                ],
                stones: [
                    {
                        stoneId: 'beige',
                        quantity: 8,
                        edge: 'Reverse Chamfer',
                        finish: 'Honed',
                    },
                    {
                        stoneId: 'arabescato-white',
                        quantity: 5,
                        edge: 'Reverse Chamfer',
                        finish: 'Honed',
                    },
                ],
                requirements: {
                    location: 'San Juan',
                    landmark: 'Ground floor, beside the garden court entrance',
                    areaSqm: 9.75,
                    neededBy: '2026-10-30',
                    /*
                     * A time as well as a day, because both of this project's
                     * services are ones the team attends at an hour rather than
                     * delivers against a date.
                     */
                    neededByTime: '09:00',
                    attachments: [],
                },
            },
        ],
        services: [
            'Design & Project Consultation',
            'Post-Installation Quality Care',
        ],
        statusHistory: [
            { status: 'draft', at: '2026-09-15T04:50:00.000Z' },
            { status: 'pending', at: '2026-09-16T01:05:00.000Z' },
            {
                status: 'quoted',
                at: '2026-09-19T09:30:00.000Z',
            },
        ],
    },
    {
        id: 'inq-1043',
        reference: 'INQ-1043',
        status: 'paid',
        createdAt: '2026-09-11T08:25:00.000Z',
        updatedAt: '2026-09-14T07:40:00.000Z',
        clientId: 'cl-ivan',
        handledByIds: ['priya'],
        projects: [
            {
                id: 'prj-1043-1',
                name: 'Hillcrest Showroom — Display Units',
                applications: ['Center & Side Tables'],
                services: [
                    'Custom Stone Furniture',
                    'Custom Stone Fabrication',
                    'Natural Stone Installation',
                ],
                stones: [
                    {
                        stoneId: 'cosmic-black',
                        quantity: 5,
                        edge: 'Straight Edge',
                        finish: 'Polished',
                    },
                    {
                        stoneId: 'flamed-brushed-black-monumental',
                        quantity: 2,
                        edge: 'Straight Edge',
                        finish: 'Polished',
                    },
                ],
                requirements: {
                    location: 'San Juan',
                    areaSqm: 12,
                    neededBy: '2026-09-26',
                    attachments: [
                        {
                            id: 'file-inq-1043-1',
                            name: 'hillcrest-display-units.png',
                            sizeBytes: 655360,
                            mimeType: 'image/png',
                        },
                    ],
                },
            },
        ],
        services: [
            'Custom Stone Furniture',
            'Custom Stone Fabrication',
            'Natural Stone Installation',
        ],
        statusHistory: [
            { status: 'draft', at: '2026-09-11T08:25:00.000Z' },
            { status: 'pending', at: '2026-09-12T02:10:00.000Z' },
            { status: 'quoted', at: '2026-09-12T10:45:00.000Z' },
            {
                status: 'paid',
                at: '2026-09-14T07:40:00.000Z',
            },
        ],
    },
    {
        id: 'inq-1041',
        reference: 'INQ-1041',
        status: 'completed',
        createdAt: '2026-08-28T05:45:00.000Z',
        updatedAt: '2026-09-08T04:15:00.000Z',
        clientId: 'cl-haruki',
        handledByIds: ['daniel', 'sofia'],
        projects: [
            {
                id: 'prj-1041-1',
                name: 'The Grove — Outdoor Kitchen and Deck',
                applications: ['Kitchen Islands'],
                services: [
                    'Natural Stone Supply',
                    'Custom Stone Fabrication',
                    'Natural Stone Installation',
                ],
                stones: [
                    {
                        stoneId: 'avocado-green-iii',
                        quantity: 12,
                        edge: 'Full Bevel',
                        finish: 'Leather',
                    },
                    {
                        stoneId: 'cosmic-black',
                        quantity: 6,
                        edge: 'Full Bevel',
                        finish: 'Leather',
                    },
                    {
                        stoneId: 'arabescato-white',
                        quantity: 4,
                        edge: 'Full Bevel',
                        finish: 'Leather',
                    },
                ],
                /*
                 * The counter half of the notes and the kitchen's own drawings.
                 * The deck half — the sun, and the drainage channel the drawings
                 * disagree with — sits with the deck, because that is the piece of
                 * work it is about.
                 */
                requirements: {
                    location: 'Muntinlupa',
                    landmark: 'Under the tree, at the far end of the deck',
                    areaSqm: 11.5,
                    neededBy: '2026-09-05',
                    notes: [
                        'The counter runs along the pool deck, so it is in full sun for the',
                        'afternoon and under the tree for the morning.',
                        '',
                        'The grill sits at the left-hand end, so we would cut a recess for it',
                        'rather than run the top past it.',
                        '',
                        'If that is already decided, please say so here so the workshop is not',
                        'asked to move the cut once it has started.',
                    ].join('\n'),
                    attachments: [
                        {
                            id: 'file-inq-1041-1',
                            name: 'grove-outdoor-kitchen-plan.pdf',
                            sizeBytes: 2411724,
                            mimeType: 'application/pdf',
                        },
                        {
                            id: 'file-inq-1041-2',
                            name: 'grove-bar-reference.jpg',
                            sizeBytes: 921600,
                            mimeType: 'image/jpeg',
                        },
                        {
                            id: 'file-inq-1041-3',
                            name: 'quartzite-slabsite-mockup.png',
                            sizeBytes: 1572864,
                            mimeType: 'image/png',
                        },
                    ],
                },
            },
            {
                id: 'prj-1041-2',
                name: 'The Grove - Pool Deck',
                applications: ['Exterior Walls'],
                services: [
                    'Natural Stone Supply',
                    'Natural Stone Installation',
                ],
                stones: [
                    {
                        stoneId: 'beige',
                        quantity: 20,
                        edge: 'Straight Edge',
                        finish: 'Honed',
                    },
                ],
                requirements: {
                    location: 'Muntinlupa',
                    landmark: 'Pool deck, beside the barbecue counter',
                    areaSqm: 24.25,
                    neededBy: '2026-08-28',
                    notes: [
                        'The drainage channel the drawings show does not line up with the',
                        'fall of the deck, and we would move it to the far end.',
                        '',
                        'The deck goes down before the counter is set, so the earlier date on',
                        'this one is deliberate.',
                    ].join('\n'),
                    attachments: [
                        {
                            id: 'file-inq-1041-4',
                            name: 'grove-drainage-detail.pdf',
                            sizeBytes: 384512,
                            mimeType: 'application/pdf',
                        },
                    ],
                },
            },
        ],
        services: [
            'Natural Stone Supply',
            'Custom Stone Fabrication',
            'Natural Stone Installation',
        ],
        statusHistory: [
            { status: 'draft', at: '2026-08-28T05:45:00.000Z' },
            { status: 'pending', at: '2026-08-29T03:20:00.000Z' },
            { status: 'quoted', at: '2026-08-31T08:55:00.000Z' },
            { status: 'paid', at: '2026-09-01T06:30:00.000Z' },
            {
                status: 'completed',
                at: '2026-09-08T04:15:00.000Z',
            },
        ],
    },
    {
        id: 'inq-1039',
        reference: 'INQ-1039',
        status: 'cancelled',
        createdAt: '2026-08-12T06:55:00.000Z',
        updatedAt: '2026-08-14T01:10:00.000Z',
        clientId: 'cl-grace',
        handledByIds: ['sofia'],
        projects: [
            {
                id: 'prj-1039-1',
                name: 'Maple Lane - Laundry',
                applications: ['Wall Accents'],
                services: ['Stone Relocation, Dismantling & Reinstallation'],
                stones: [
                    {
                        stoneId: 'arancia',
                        quantity: 3,
                        edge: 'Half Bullnose',
                        finish: 'Honed',
                    },
                    {
                        stoneId: 'flamed-brushed-black',
                        quantity: 10,
                        edge: 'Half Bullnose',
                        finish: 'Honed',
                    },
                ],
                requirements: {
                    location: 'Quezon City',
                    /*
                     * A time with no date, because the inquiry was cancelled
                     * before a day was ever agreed. Left as it stands so the
                     * details page still has a reason to say "not provided"
                     * rather than every answer being filled in.
                     */
                    neededByTime: '08:00',
                    attachments: [],
                },
            },
        ],
        services: ['Stone Relocation, Dismantling & Reinstallation'],
        statusHistory: [
            { status: 'draft', at: '2026-08-12T06:55:00.000Z' },
            { status: 'pending', at: '2026-08-12T09:30:00.000Z' },
            {
                status: 'cancelled',
                at: '2026-08-14T01:10:00.000Z',
            },
        ],
    },
];
