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

/**
 * The question after this one. A service that asks for no stones (Installation
 * Only) goes from What do you need? straight to the details.
 */
export const nextQuestion = (
    step: number,
    requiresStones: boolean = true,
): number =>
    step === SERVICE_STEP && !requiresStones
        ? REQUIREMENTS_STEP
        : Math.min(step + 1, REVIEW_STEP);

/** The answers that decide how far the questions go. */
export type DraftProgress = {
    serviceId: string;
    stoneIds: string[];
    stoneAdvice?: boolean;
    /** False when the chosen service asks for no stones; unknown counts as true. */
    requiresStones?: boolean;
};

/**
 * The furthest question the draft's answers reach: What do you need? until a
 * service is chosen, the stones until one is picked or left to Regray, then
 * the details. A service that asks for no stones reaches the details as soon
 * as it is chosen. A restored draft shows every question up to it.
 */
export const furthestQuestion = (draft: DraftProgress): number =>
    draft.serviceId === ''
        ? SERVICE_STEP
        : draft.requiresStones === false ||
            draft.stoneIds.length > 0 ||
            draft.stoneAdvice === true
          ? REQUIREMENTS_STEP
          : STONES_STEP;

/**
 * Why Next cannot show the question after this one yet, or null when it can.
 * Only the question Next sits under is checked.
 */
export const questionProblem = (
    question: number,
    draft: DraftProgress,
    offeredServiceIds: ReadonlySet<string>,
): { field: 'serviceId' | 'stoneIds'; message: string } | null => {
    if (question === SERVICE_STEP && !offeredServiceIds.has(draft.serviceId)) {
        return { field: 'serviceId', message: 'Choose what you need.' };
    }

    if (
        question === STONES_STEP &&
        draft.requiresStones !== false &&
        draft.stoneIds.length === 0 &&
        draft.stoneAdvice !== true
    ) {
        return { field: 'stoneIds', message: 'Select at least one stone.' };
    }

    return null;
};

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
 * the draft still justifies, and What do you need? is the floor. A service that
 * asks for no stones justifies the details with none, and never lands on the
 * stones question it does not ask.
 */
export const clampRestoredStep = (
    stored: number,
    projectCount: number,
    draft: DraftProgress,
): number => {
    if (stored >= REVIEW_STEP && projectCount > 0) {
        return REVIEW_STEP;
    }

    const furthest = furthestQuestion(draft);
    const requested = Number.isFinite(stored)
        ? Math.trunc(stored)
        : SERVICE_STEP;
    const landed = Math.min(Math.max(requested, SERVICE_STEP), furthest);

    return landed === STONES_STEP && draft.requiresStones === false
        ? REQUIREMENTS_STEP
        : landed;
};

/* ------------------------------------------------------------------ */
/* What the reader needs                                                */
/* ------------------------------------------------------------------ */

export const STONE_ADVICE_LABEL = 'Stone to be recommended by Regray';

/** One listed answer of a question, as InquiryDetailFieldResource sends it. */
export type ServiceFieldChoice = {
    value: string;
    label: string;
    /** The "Not sure yet, advise me" edge profile or finish. */
    isAdvisory?: boolean;
    /** "Same as stone": only a project with a stone row can take it. */
    linksToStone?: boolean;
};

/**
 * One question a service asks besides the stones, as InquiryDetailFieldResource
 * sends it. A `stone` one is answered on each stone row, a `project` one once
 * under Other details. `options` are the closed list to pick from;
 * `suggestions` are sample values shown beside a free entry, which
 * `allowsCustom` takes. Nothing about a question is written into the page.
 */
export type ServiceField = {
    slug: string;
    label: string;
    scope: 'stone' | 'project';
    answerType: 'text' | 'number' | 'choice' | 'boolean';
    required: boolean;
    allowsCustom: boolean;
    options: ServiceFieldChoice[];
    suggestions: ServiceFieldChoice[];
};

/**
 * One entry of the second dropdown, as InquiryServiceResource sends it.
 * `stoneTypes` are the slugs of the stone types it is made from;
 * `requiresStones` is false for a service that asks for no stones; `fields`
 * are the questions it asks besides them, in order.
 */
export type ServiceOption = {
    id: string;
    name: string;
    description: string;
    features: string[];
    stoneTypes: string[];
    requiresStones: boolean;
    fields: ServiceField[];
};

/**
 * The key a stone row holds each stone-scope answer under, by question slug.
 * These are the three columns of a stone row, which is why they are named
 * here; what each asks, its label and its options all come from the service.
 */
export const STONE_FIELD_KEYS = {
    slab_size: 'slabSize',
    edge_profile: 'edge',
    finish: 'finish',
} as const satisfies Record<string, 'slabSize' | 'edge' | 'finish'>;

export type StoneFieldKey =
    (typeof STONE_FIELD_KEYS)[keyof typeof STONE_FIELD_KEYS];

/** The key a stone-scope question is answered under on a row, or null. */
export const stoneFieldKey = (
    field: Pick<ServiceField, 'slug'>,
): StoneFieldKey | null =>
    (STONE_FIELD_KEYS as Record<string, StoneFieldKey>)[field.slug] ?? null;

/** The question that asks how many pieces of a stone: only the slab services ask it. */
export const QUANTITY_FIELD = 'quantity';

/** Whether a service asks how many pieces of each stone. */
export const asksQuantity = (
    service: Pick<ServiceOption, 'fields'> | undefined,
): boolean =>
    (service?.fields ?? []).some(({ slug }) => slug === QUANTITY_FIELD);

/** The most pieces of one stone a row may ask for; the server holds the same. */
export const QUANTITY_MAX = 9999;

/**
 * Why a piece count is refused, or null, in the words StoreInquiryRequest
 * uses. An emptied count arrives as `NaN`.
 */
export const quantityProblem = (quantity: number): string | null => {
    if (!Number.isFinite(quantity)) {
        return 'Enter how many pieces you need.';
    }

    if (!Number.isInteger(quantity)) {
        return 'Quantity has to be a whole number of pieces.';
    }

    if (quantity < 1) {
        return 'Enter at least 1 piece.';
    }

    return quantity > QUANTITY_MAX
        ? `Enter ${QUANTITY_MAX} pieces or fewer.`
        : null;
};

/**
 * A service's stone-scope questions answered with a choice or an entry, in
 * the order asked. How many pieces is drawn and checked on its own.
 */
export const stoneFields = (
    service: Pick<ServiceOption, 'fields'> | undefined,
): ServiceField[] =>
    (service?.fields ?? []).filter(
        (field) => field.scope === 'stone' && stoneFieldKey(field) !== null,
    );

/** A service's project-scope questions, in the order asked. */
export const projectFields = (
    service: Pick<ServiceOption, 'fields'> | undefined,
): ServiceField[] =>
    (service?.fields ?? []).filter((field) => field.scope === 'project');

/** The service of this id, under whichever type offers it. */
export const findService = <Service extends { id: string }>(
    serviceTypes: ReadonlyArray<{ services: Service[] }>,
    serviceId: string,
): Service | undefined =>
    serviceTypes
        .flatMap(({ services }) => services)
        .find(({ id }) => id === serviceId);

/** What a yes or no question is answered with on the page. */
export const YES = 'yes';
export const NO = 'no';

/** The largest number an answer holds, as the server stores it. */
export const ANSWER_NUMBER_MAX = 9999999999.99;

/** The longest free text an answer holds; a slab size holds less. */
export const ANSWER_TEXT_MAX = 255;
export const SLAB_SIZE_TEXT_MAX = 50;

const answerTextMax = (field: Pick<ServiceField, 'slug'>): number =>
    field.slug === 'slab_size' ? SLAB_SIZE_TEXT_MAX : ANSWER_TEXT_MAX;

/**
 * Why an answer to a question is refused, or null when it stands, in the
 * words StoreInquiryRequest uses: an empty required answer, a closed list's
 * answer that is not on it, and a free entry by its type. A suggestion is
 * never checked against: a free entry stands wherever the question takes one.
 */
export const answerProblem = (
    field: ServiceField,
    raw: string | undefined,
): string | null => {
    const value = (raw ?? '').trim();
    const name = field.label.toLowerCase();
    const fromList = `Choose the ${name} from the list.`;

    if (value === '') {
        if (!field.required) {
            return null;
        }

        return field.answerType === 'text' || field.answerType === 'number'
            ? `Enter the ${name}.`
            : `Choose the ${name}.`;
    }

    if (field.answerType === 'choice') {
        return field.options.some((option) => option.value === value)
            ? null
            : fromList;
    }

    if (field.answerType === 'boolean') {
        return value === YES || value === NO ? null : 'Choose yes or no.';
    }

    if (
        !field.allowsCustom &&
        !field.suggestions.some((option) => option.value === value)
    ) {
        return fromList;
    }

    if (field.answerType === 'number') {
        const number = Number(value);

        if (!Number.isFinite(number) || number <= 0) {
            return 'Enter a number greater than 0.';
        }

        return number > ANSWER_NUMBER_MAX ? 'Enter a smaller number.' : null;
    }

    return value.length > answerTextMax(field)
        ? `Keep the ${name} to ${answerTextMax(field)} characters or fewer.`
        : null;
};

/**
 * Why "Same as stone" cannot be the answer, or null: it means a stone row's
 * stone, so a project with none (Help me choose) cannot take it.
 */
export const linkedToStoneProblem = (
    field: ServiceField,
    value: string | undefined,
    hasStones: boolean,
): string | null => {
    const option = field.options.find((entry) => entry.value === value);

    return option?.linksToStone === true && !hasStones
        ? `${option.label} needs a stone. Choose another ${field.label.toLowerCase()}.`
        : null;
};

/**
 * Whether a stone is offered for the service: its primary type is one the
 * service is made from. The same rule as Stone::offeredFor() on the server.
 */
export const stoneFitsService = (
    stone: { type: { slug: string } },
    service: Pick<ServiceOption, 'stoneTypes'> | undefined,
): boolean =>
    service !== undefined && service.stoneTypes.includes(stone.type.slug);

/**
 * The picked stones a service does not offer, in the order they were picked.
 * A stone the page knows nothing about is left alone: whether it is still
 * offered at all is the server's to say.
 */
export const stonesNotOffered = <
    Stone extends { slug: string; type: { slug: string } },
>(
    stoneIds: readonly string[],
    known: readonly Stone[],
    service: Pick<ServiceOption, 'stoneTypes'> | undefined,
): Stone[] =>
    stoneIds.flatMap((id) => {
        const stone = known.find(({ slug }) => slug === id);

        return stone === undefined || stoneFitsService(stone, service)
            ? []
            : [stone];
    });

/**
 * What step 2 says after removing stones a service does not offer. `count` is
 * how many stones the names stand for, when one name is a count ("2 stones")
 * because a restored draft only knows how many the server left out.
 */
export const removedStonesNotice = (
    names: readonly string[],
    serviceName: string,
    count: number = names.length,
): string => {
    if (names.length === 0) {
        return '';
    }

    const listed =
        names.length === 1
            ? names[0]
            : `${names.slice(0, -1).join(', ')} and ${names.at(-1)}`;

    return `Removed ${listed} for this service; ${count === 1 ? 'it is' : 'they are'} not offered for ${serviceName}.`;
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
    /** The chosen service's slug; with none, the server sends no stones. */
    service: string;
    tab: StoneFilter;
    stoneType: string;
    search: string;
};

/** The filters as the query the server reads, leaving out what is no filter. */
export const stoneFilterQuery = ({
    service,
    tab,
    stoneType,
    search,
}: StoneQuery): Record<string, string> => ({
    ...(!service ? {} : { stone_service: service }),
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
    slabSize: string;
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
                      slabSize: '',
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
    /** The project-scope answers, by question slug. */
    answers: Record<string, string>;
    deliveryMethod: string;
    location: string;
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
        answers: { ...draft.answers },
        deliveryMethod: draft.deliveryMethod,
        location: isPickup ? '' : draft.location,
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
    answers: Record<string, string>;
    deliveryMethod: string;
    location: string;
    neededBy: string;
    notes: string;
    attachments: RequestAttachment[];
};

/**
 * The inquiry as multipart form data, in the field names the backend's form
 * request validates: `projects[i][name]`, `projects[i][serviceId]`,
 * `projects[i][stones][k][quantity]`, `projects[i][answers][slug]` and, for
 * each file in the order it was attached, `projects[i][attachments][j]`.
 *
 * Only what the project's service asks is sent (`fieldsFor`): a stone row's
 * answers to its stone-scope questions, and the project answers to the rest,
 * a yes or no as 1 or 0. A service that asks for no stones sends no stones and
 * no Help me choose. What the reader typed for a question the service no
 * longer asks stays on the page but is not sent, since the server refuses it.
 *
 * Multipart rather than JSON because the attachments are `File` objects.
 *
 * When editing (`withProjectIds`), each project also carries its database id
 * as `projects[i][id]`, and the stored files it keeps as
 * `projects[i][keepFileIds][k]`; `attachments` are then the new uploads only.
 */
export const buildInquiryFormData = (
    values: { projects: RequestProject[] },
    {
        withProjectIds = false,
        serviceFor,
    }: {
        withProjectIds?: boolean;
        serviceFor: (
            serviceId: string,
        ) => Pick<ServiceOption, 'fields' | 'requiresStones'> | undefined;
    },
): FormData => {
    const body = new FormData();

    values.projects.forEach((project, i) => {
        const field = (name: string): string => `projects[${i}][${name}]`;
        const service = serviceFor(project.serviceId);
        const takesStones = service?.requiresStones !== false;

        if (withProjectIds && project.id !== undefined) {
            body.append(field('id'), project.id);
        }

        body.append(field('name'), project.name);
        body.append(field('serviceId'), project.serviceId);
        body.append(
            field('stoneAdvice'),
            takesStones && project.stoneAdvice ? '1' : '0',
        );

        (takesStones ? project.stones : []).forEach((row, k) => {
            const stone = (name: string): string =>
                `${field('stones')}[${k}][${name}]`;

            body.append(stone('stoneId'), row.stoneId);

            if (asksQuantity(service)) {
                body.append(stone('quantity'), String(row.quantity));
            }

            for (const question of stoneFields(service)) {
                const key = stoneFieldKey(question);

                if (key !== null) {
                    body.append(stone(key), row[key].trim());
                }
            }
        });

        for (const question of projectFields(service)) {
            const value = (project.answers[question.slug] ?? '').trim();

            if (value === '') {
                continue;
            }

            body.append(
                `${field('answers')}[${question.slug}]`,
                question.answerType === 'boolean'
                    ? value === YES
                        ? '1'
                        : '0'
                    : value,
            );
        }

        body.append(field('deliveryMethod'), project.deliveryMethod);
        body.append(field('location'), project.location);
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
    /** A stone row's answers are null where its service does not ask them. */
    stones: Array<{
        stoneId: string;
        /** Null where the service does not ask how many pieces. */
        quantity: number | null;
        slabSize: string | null;
        edge: string | null;
        finish: string | null;
    }>;
    /** The project answers by question slug: text, a number, an option's slug, yes or no. */
    answers: Record<string, string | number | boolean | null>;
    deliveryMethod: string;
    location: string | null;
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
    answers: Record<string, string>;
    deliveryMethod: string;
    location: string;
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

/** An answer as the form holds it: text, a yes or no as `yes` or `no`. */
const formAnswer = (value: string | number | boolean | null): string =>
    value === null
        ? ''
        : typeof value === 'boolean'
          ? value
              ? YES
              : NO
          : String(value);

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
        stones: project.stones.map((row) => ({
            stoneId: row.stoneId,
            quantity: row.quantity ?? 1,
            slabSize: row.slabSize ?? '',
            edge: row.edge ?? '',
            finish: row.finish ?? '',
        })),
        answers: Object.fromEntries(
            Object.entries(project.answers).map(([slug, value]) => [
                slug,
                formAnswer(value),
            ]),
        ),
        deliveryMethod: project.deliveryMethod,
        location: project.location ?? '',
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
