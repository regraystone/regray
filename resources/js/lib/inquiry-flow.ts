/*
 * The Inquire flow's pure logic: where each step leads, where a restored draft
 * may land, what a committed project is made of, how a request is put on the
 * wire and how the backend's complaints are put back on the page.
 *
 * No imports, on purpose. The tests under tests/js run this file straight under
 * Node, where the `@/` alias does not exist, so everything it needs is declared
 * here or handed in as an argument.
 */

/*
 * The reader sees two steps: Your request and Review and send. The first is
 * questions on one page, one open at a time: what they need, the stones, then the
 * details. Stored drafts hold the same numbers.
 */
export const SERVICE_STEP = 1;
export const STONES_STEP = 2;
export const REQUIREMENTS_STEP = 3;

/** Review is the last step, and owns no fields of its own to validate. */
export const REVIEW_STEP = REQUIREMENTS_STEP + 1;

export const INQUIRY_STEP_LABELS = ['Your request', 'Review and send'] as const;

/** Which of the two steps the reader sees this question or Review belongs to. */
export const stepperPosition = (step: number): number =>
    step >= REVIEW_STEP ? 2 : 1;

export type InquiryMode = 'create' | 'edit';

/** How the project reaches the client. A pickup has no location to ask for. */
export const DELIVERY = 'delivery';
export const PICKUP = 'pickup';

export const DELIVERY_METHODS = [
    { value: DELIVERY, title: 'Delivery' },
    { value: PICKUP, title: 'Pickup' },
];

/** The question after this one. */
export const nextQuestion = (step: number): number =>
    Math.min(step + 1, REVIEW_STEP);

/**
 * A project's name, made from the service the reader picked, capped at the name's
 * own limit, which the caller passes because this file imports nothing. It can be
 * changed on Review.
 */
export const autoProjectName = (
    serviceName: string,
    maxLength: number,
): string => serviceName.slice(0, maxLength).trim();

/**
 * Where a restored draft is allowed to land.
 *
 * The stored step is a number the draft behind it may no longer justify: a
 * Review with no projects left to review, a Stones question with no service
 * chosen, details with no stones. So the stored step is held to the furthest step
 * the draft still justifies, and What do you need? is the floor.
 */
export const clampRestoredStep = (
    stored: number,
    projectCount: number,
    draft: {
        serviceId: string;
        stoneIds: string[];
        stoneAdvice?: boolean;
    },
): number => {
    if (stored >= REVIEW_STEP && projectCount > 0) {
        return REVIEW_STEP;
    }

    const hasStones = draft.stoneIds.length > 0 || draft.stoneAdvice === true;
    const furthest =
        draft.serviceId === ''
            ? SERVICE_STEP
            : hasStones
              ? REQUIREMENTS_STEP
              : STONES_STEP;
    const requested = Number.isFinite(stored)
        ? Math.trunc(stored)
        : SERVICE_STEP;

    return Math.min(Math.max(requested, SERVICE_STEP), furthest);
};

/* ------------------------------------------------------------------ */
/* What the reader needs                                                */
/* ------------------------------------------------------------------ */

export const STONE_ADVICE_LABEL = 'Stone to be recommended by Regray';

/** One entry of the second dropdown, as InquiryServiceResource sends it. */
export type ServiceOption = {
    id: string;
    name: string;
    description: string;
    features: string[];
};

/** One entry of the first dropdown, as InquiryServiceTypeResource sends it. */
export type ServiceTypeOption = {
    id: string;
    name: string;
    services: ServiceOption[];
};

export const CUSTOM_STONE_FURNITURE = 'custom-stone-furniture';

/** What the second dropdown asks, which depends on the type chosen in the first. */
export const serviceChoiceLabel = (serviceTypeId: string): string =>
    serviceTypeId === CUSTOM_STONE_FURNITURE
        ? 'Which furniture?'
        : 'Which application?';

/* ------------------------------------------------------------------ */
/* Finding a stone                                                      */
/* ------------------------------------------------------------------ */

export type StoneFilter = 'all' | 'bestSellers' | 'saved';

/** The value of "All Type", which no stone type's slug can be mistaken for. */
export const ALL_STONE_TYPES = '__all__';

/** "All Type", then the types the server says the stones on offer have. */
export const stoneTypeOptions = (
    types: ReadonlyArray<{ value: string; label: string }>,
): Array<{ value: string; label: string }> => [
    { value: ALL_STONE_TYPES, label: 'All Type' },
    ...types,
];

export type StoneQuery = {
    tab: StoneFilter;
    stoneType: string;
    search: string;
};

/** The filters as the query the server reads, leaving out what is no filter. */
export const stoneFilterQuery = ({
    tab,
    stoneType,
    search,
}: StoneQuery): Record<string, string> => ({
    ...(tab === 'all' ? {} : { stone_tab: tab }),
    ...(stoneType === ALL_STONE_TYPES ? {} : { stone_type: stoneType }),
    ...(search.trim() === '' ? {} : { stone_search: search.trim() }),
});

/**
 * The stone rows for the picks.
 *
 * Keeps every row of a stone that is still picked, drops the rows of one that
 * is not, and gives each newly picked stone one fresh row. Blank edges and
 * finishes take the "advise me" answer, so the reader only has to say otherwise
 * if they know what they want.
 */
export type StoneRow = {
    stoneId: string;
    quantity: number;
    edge: string;
    finish: string;
};

export const seedStoneRows = (
    picked: string[],
    current: StoneRow[],
    advisory: { edge: string; finish: string } | null,
): StoneRow[] => {
    const pickedSet = new Set(picked);
    const kept = current
        .filter((row) => pickedSet.has(row.stoneId))
        .map((row) =>
            advisory === null
                ? row
                : {
                      ...row,
                      edge: row.edge === '' ? advisory.edge : row.edge,
                      finish: row.finish === '' ? advisory.finish : row.finish,
                  },
        );

    return [...new Set(picked)].flatMap((id) => {
        const existing = kept.filter((row) => row.stoneId === id);

        return existing.length > 0
            ? existing
            : [
                  {
                      stoneId: id,
                      quantity: 1,
                      edge: advisory?.edge ?? '',
                      finish: advisory?.finish ?? '',
                  },
              ];
    });
};

/** The draft, as far as committing it goes. */
export type CommitDraft = {
    name: string;
    serviceTypeId: string;
    serviceId: string;
    stoneIds: string[];
    /** The reader asked Regray to recommend a stone instead of choosing one. */
    stoneAdvice: boolean;
    stones: StoneRow[];
    deliveryMethod: string;
    location: string;
    landmark: string;
    areaSqm: string;
    neededBy: string;
    notes: string;
    attachments: unknown[];
};

/**
 * One committed project. A pickup keeps no location: what was typed before changing
 * to it stays on the draft, in case they change back.
 */
export const buildCommittedProject = <T extends CommitDraft>(
    draft: T,
    context: { id: string },
) => {
    const isPickup = draft.deliveryMethod === PICKUP;

    return {
        id: context.id,
        name: draft.name,
        serviceTypeId: draft.serviceTypeId,
        serviceId: draft.serviceId,
        stoneAdvice: draft.stoneAdvice,
        stones: draft.stones.map((row) => ({ ...row })),
        deliveryMethod: draft.deliveryMethod,
        location: isPickup ? '' : draft.location,
        landmark: draft.landmark,
        areaSqm: draft.areaSqm,
        neededBy: draft.neededBy,
        notes: draft.notes,
        attachments: draft.attachments.map((attachment) =>
            typeof attachment === 'object' && attachment !== null
                ? { ...attachment }
                : attachment,
        ) as T['attachments'],
    };
};

/* ------------------------------------------------------------------ */
/* The request                                                          */
/* ------------------------------------------------------------------ */

/**
 * A file on its way out: a new upload carries its bytes, a file the inquiry
 * already holds (when editing) is only named by its id.
 */
export type RequestAttachment =
    | { status: 'uploading' | 'done'; file: File }
    | { status: 'saved'; fileId: number };

export type RequestProject = {
    /** The project's id; sent only when editing, where it is the database id. */
    id?: string;
    name: string;
    serviceId: string;
    stoneAdvice: boolean;
    stones: StoneRow[];
    deliveryMethod: string;
    location: string;
    landmark: string;
    areaSqm: string;
    neededBy: string;
    notes: string;
    attachments: RequestAttachment[];
};

/**
 * The inquiry as multipart form data, in the field names the backend's form
 * request validates: `projects[i][name]`, `projects[i][serviceId]`,
 * `projects[i][stones][k][quantity]` and, for each file in the order it was attached,
 * `projects[i][attachments][j]`.
 *
 * Multipart rather than JSON because the attachments are `File` objects.
 *
 * When editing (`withProjectIds`), each project also carries its database id
 * as `projects[i][id]`, and the stored files it keeps as
 * `projects[i][keepFileIds][k]`; `attachments` are then the new uploads only.
 */
export const buildInquiryFormData = (
    values: { projects: RequestProject[] },
    { withProjectIds = false }: { withProjectIds?: boolean } = {},
): FormData => {
    const body = new FormData();

    values.projects.forEach((project, i) => {
        const field = (name: string): string => `projects[${i}][${name}]`;

        if (withProjectIds && project.id !== undefined) {
            body.append(field('id'), project.id);
        }

        body.append(field('name'), project.name);
        body.append(field('serviceId'), project.serviceId);
        body.append(field('stoneAdvice'), project.stoneAdvice ? '1' : '0');

        project.stones.forEach((row, k) => {
            const stone = (name: string): string =>
                `${field('stones')}[${k}][${name}]`;

            body.append(stone('stoneId'), row.stoneId);
            body.append(stone('quantity'), String(row.quantity));
            body.append(stone('edge'), row.edge);
            body.append(stone('finish'), row.finish);
        });

        body.append(field('deliveryMethod'), project.deliveryMethod);
        body.append(field('location'), project.location);
        body.append(field('landmark'), project.landmark);
        body.append(field('areaSqm'), project.areaSqm);
        body.append(field('neededBy'), project.neededBy);
        body.append(field('notes'), project.notes);

        let kept = 0;
        let uploaded = 0;

        for (const attachment of project.attachments) {
            if (attachment.status === 'saved') {
                body.append(
                    `${field('keepFileIds')}[${kept}]`,
                    String(attachment.fileId),
                );
                kept += 1;
            } else {
                body.append(
                    `${field('attachments')}[${uploaded}]`,
                    attachment.file,
                );
                uploaded += 1;
            }
        }
    });

    return body;
};

/* ------------------------------------------------------------------ */
/* The answer                                                           */
/* ------------------------------------------------------------------ */

export type MappedInquiryErrors = {
    /** One complaint per project, keyed by project id. */
    projectMessages: Record<string, string>;
    /** One complaint about the request as a whole, keyed by the owning step. */
    sectionMessages: Partial<Record<number, string>>;
    /** Every complaint, once each, for the alert above the page. */
    alertErrors: string[];
};

/**
 * Put the backend's errors back where the page shows them.
 *
 * The keys are the form's own paths — `projects.0.stones.1.quantity` — so the
 * project is found by the index in the key, read against the same list that was
 * sent. A key about the list itself (`projects`) is the request's, not any one
 * project's. A key naming a project that is no longer there is dropped from the
 * per-project messages but still said in the alert.
 */
export const mapInquiryErrors = (
    errors: Record<string, string>,
    projects: Array<{ id: string }>,
): MappedInquiryErrors => {
    const projectMessages: Record<string, string> = {};
    const sectionMessages: Partial<Record<number, string>> = {};
    const alertErrors: string[] = [];

    for (const [key, message] of Object.entries(errors)) {
        if (!alertErrors.includes(message)) {
            alertErrors.push(message);
        }

        const match = /^projects\.(\d+)(?:\.|$)/.exec(key);

        if (match === null) {
            if (
                key === 'projects' &&
                sectionMessages[REQUIREMENTS_STEP] === undefined
            ) {
                sectionMessages[REQUIREMENTS_STEP] = message;
            }

            continue;
        }

        const project = projects[Number(match[1])];

        if (
            project !== undefined &&
            projectMessages[project.id] === undefined
        ) {
            projectMessages[project.id] = message;
        }
    }

    return { projectMessages, sectionMessages, alertErrors };
};

/** What the backend flashes after a send. */
export type InquiryFlash =
    | { status: 'success'; title: string; reference: string }
    | { status: 'error'; message?: string };

/** Read the flash off an Inertia page, or null when it carries none. */
export const readInquiryFlash = (flash: unknown): InquiryFlash | null => {
    const value = (flash as { inquiry?: unknown } | null | undefined)?.inquiry;

    if (typeof value !== 'object' || value === null) {
        return null;
    }

    const { status, title, reference } = value as Record<string, unknown>;

    if (status === 'success') {
        return {
            status,
            title: typeof title === 'string' ? title : '',
            reference: typeof reference === 'string' ? reference : '',
        };
    }

    if (status === 'error') {
        const { message } = value as Record<string, unknown>;

        return typeof message === 'string' ? { status, message } : { status };
    }

    return null;
};

/* ------------------------------------------------------------------ */
/* Editing a sent inquiry                                               */
/* ------------------------------------------------------------------ */

/** One project as InquiryEditResource sends it. */
export type InquiryEditProject = {
    id: number;
    name: string;
    serviceTypeId: string | null;
    serviceId: string | null;
    /** The service it already has, which may no longer be offered to new inquiries. */
    heldServiceType: ServiceTypeOption | null;
    /** Its stones as cards, which may be on no page of stones reached yet. */
    heldStones: Array<{ slug: string; name: string; thumbnail: string }>;
    stoneAdviceRequested: boolean;
    stones: StoneRow[];
    deliveryMethod: string;
    location: string | null;
    landmark: string | null;
    areaSqm: number | null;
    notes: string | null;
    neededBy: string | null;
    deadlineLocked: boolean;
    deadlineLockedReason: string | null;
    attachments: Array<{
        id: number;
        name: string;
        sizeBytes: number;
        mimeType: string;
        status: 'saved';
    }>;
};

/** A committed project as the form holds it, filled from an edit payload. */
export type EditFormProject = {
    id: string;
    name: string;
    serviceTypeId: string;
    serviceId: string;
    stoneAdvice: boolean;
    stones: StoneRow[];
    deliveryMethod: string;
    location: string;
    landmark: string;
    areaSqm: string;
    neededBy: string;
    notes: string;
    attachments: Array<{
        id: string;
        status: 'saved';
        fileId: number;
        name: string;
        sizeBytes: number;
        mimeType: string;
    }>;
};

/**
 * The edit payload in the shape the form commits a project in, so Review and
 * Edit read a stored project exactly as they read one built on the page.
 *
 * The project's id becomes the form id, as a string, which is also what is
 * sent back as `projects[i][id]`. Missing answers become the form's blank `''`.
 */
export const toEditFormProjects = (
    projects: InquiryEditProject[],
): EditFormProject[] =>
    projects.map((project) => ({
        id: String(project.id),
        name: project.name,
        serviceTypeId: project.serviceTypeId ?? '',
        serviceId: project.serviceId ?? '',
        stoneAdvice: project.stoneAdviceRequested,
        stones: project.stones.map((row) => ({ ...row })),
        deliveryMethod: project.deliveryMethod,
        location: project.location ?? '',
        landmark: project.landmark ?? '',
        areaSqm: project.areaSqm === null ? '' : String(project.areaSqm),
        neededBy: project.neededBy ?? '',
        notes: project.notes ?? '',
        attachments: project.attachments.map((file) => ({
            id: `saved-${file.id}`,
            status: 'saved' as const,
            fileId: file.id,
            name: file.name,
            sizeBytes: file.sizeBytes,
            mimeType: file.mimeType,
        })),
    }));

/**
 * Why each project's date and time can no longer be changed, keyed by the form
 * id. A project whose visit is still pending is not in it.
 */
export const deadlineLocks = (
    projects: InquiryEditProject[],
): Record<string, string> =>
    Object.fromEntries(
        projects
            .filter((project) => project.deadlineLocked)
            .map((project) => [
                String(project.id),
                project.deadlineLockedReason ??
                    'This date and time can no longer be changed.',
            ]),
    );

/**
 * What the Review sheet says in each mode. The send is a save when editing.
 */
export const reviewActions = (
    mode: InquiryMode,
): {
    submitLabel: string;
    footerNote: string;
} =>
    mode === 'edit'
        ? {
              submitLabel: 'Save changes',
              footerNote: 'Nothing is saved until you save changes',
          }
        : {
              submitLabel: 'Send my request',
              footerNote: 'Nothing is sent until you press Send my request',
          };
