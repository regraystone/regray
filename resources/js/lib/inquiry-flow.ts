/*
 * The Inquire flow's pure logic: where each step leads, where a restored draft
 * may land, what a committed project is made of, how a request is put on the
 * wire and how the backend's complaints are put back on the page.
 *
 * No imports, on purpose. The tests under tests/js run this file straight under
 * Node, where the `@/` alias does not exist, so everything it needs is declared
 * here or handed in as an argument.
 */

export const SERVICES_STEP = 1;
export const STONES_STEP = 2;
export const APPLICATION_STEP = 3;
export const REQUIREMENTS_STEP = 4;

/** Review is the last step, and owns no fields of its own to validate. */
export const REVIEW_STEP = REQUIREMENTS_STEP + 1;

export const INQUIRY_STEP_LABELS = [
    'Services',
    'Stones',
    'Application',
    'Requirements',
    'Review',
] as const;

/**
 * The part of the draft that decides the path through the steps.
 *
 * `serviceIds` are the services that supply stone; `standaloneServiceIds` are
 * the ones that stand as projects of their own, such as a site visit.
 */
export type FlowDraft = {
    serviceIds: string[];
    standaloneServiceIds: string[];
    stoneIds: string[];
    applicationIds: string[];
};

/** Whether the draft is nothing but services that stand on their own. */
export const isStandaloneOnly = (draft: FlowDraft): boolean =>
    draft.serviceIds.length === 0 && draft.standaloneServiceIds.length > 0;

/**
 * Whether Stones and Application have to be answered.
 *
 * Required as soon as one service supplies stone, optional when the draft is
 * only standalone services. Optional is still a step: it is walked through, not
 * skipped.
 */
export const isStonesAndApplicationRequired = (draft: FlowDraft): boolean =>
    draft.serviceIds.length > 0;

/** Whether the step is one the reader may continue past with nothing picked. */
export const isStepOptional = (step: number, draft: FlowDraft): boolean =>
    (step === STONES_STEP || step === APPLICATION_STEP) &&
    isStandaloneOnly(draft);

/** The line shown on an optional step. */
export const OPTIONAL_STEP_NOTE = 'Optional — extra information for this visit';

/**
 * The step after this one. Every draft goes Services → Stones → Application →
 * Requirements → Review; what the draft holds changes whether a step needs an
 * answer, never whether it is shown.
 */
export const nextStep = (step: number): number =>
    Math.min(step + 1, REVIEW_STEP);

/** The step before this one, never before Services. */
export const previousStep = (step: number): number =>
    Math.max(step - 1, SERVICES_STEP);

/**
 * Where a restored draft is allowed to land.
 *
 * The stored step is a number the draft behind it may no longer justify: a
 * Review with no projects left to review, an Application step whose stones were
 * never picked, a step number no longer in the stepper at all. So the stored
 * step is held to the furthest step the draft still justifies — Review needs a
 * project, Requirements needs a service, Stones are needed once a service
 * supplies stone, and then Application — and Services is the floor.
 */
export const clampRestoredStep = (
    stored: number,
    projectCount: number,
    draft: FlowDraft,
): number => {
    if (stored >= REVIEW_STEP && projectCount > 0) {
        return REVIEW_STEP;
    }

    if (draft.serviceIds.length + draft.standaloneServiceIds.length === 0) {
        return SERVICES_STEP;
    }

    let furthest = REQUIREMENTS_STEP;

    if (isStonesAndApplicationRequired(draft)) {
        if (draft.stoneIds.length === 0) {
            furthest = STONES_STEP;
        } else if (draft.applicationIds.length === 0) {
            furthest = APPLICATION_STEP;
        }
    }

    const requested = Number.isFinite(stored) ? Math.trunc(stored) : 1;

    return Math.min(Math.max(requested, SERVICES_STEP), furthest);
};

/**
 * The stone rows for the picks.
 *
 * Keeps every row of a stone that is still picked, drops the rows of one that
 * is not, and gives each newly picked stone one fresh row. A draft that is only
 * standalone services has nothing to specify, so its rows are filled with the
 * "advise me" edge and finish, which is what lets the picks travel as extra
 * information without a question the reader was never asked.
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

    return picked.flatMap((id) => {
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
export type CommitDraft = FlowDraft & {
    name: string;
    stones: StoneRow[];
    location: string;
    landmark: string;
    areaSqm: string;
    neededBy: string;
    neededByTime: string;
    notes: string;
    attachments: unknown[];
};

/**
 * What a commit does with the queue of standalone services.
 *
 * The head of the queue is the one committed when the draft is nothing but
 * standalone services; a draft with stone of its own is committed beside the
 * queue, which then waits whole.
 */
export const commitPlan = (
    draft: FlowDraft,
): { isStandalone: boolean; head: string | undefined; remaining: string[] } => {
    const [head, ...queue] = draft.standaloneServiceIds;
    const isStandalone = draft.serviceIds.length === 0 && head !== undefined;

    return {
        isStandalone,
        head,
        remaining: isStandalone ? queue : draft.standaloneServiceIds,
    };
};

/**
 * One committed project.
 *
 * A standalone project still carries the stones and applications picked in the
 * steps before it, as extra information. It is still one project per service.
 */
export const buildCommittedProject = <T extends CommitDraft>(
    draft: T,
    context: { id: string; standaloneService?: { id: string; name: string } },
) => {
    const standalone = context.standaloneService;

    return {
        id: context.id,
        name: standalone === undefined ? draft.name : standalone.name,
        serviceIds:
            standalone === undefined ? [...draft.serviceIds] : [standalone.id],
        applications: [...draft.applicationIds],
        stones: draft.stones.map((row) => ({ ...row })),
        location: draft.location,
        landmark: draft.landmark,
        areaSqm: draft.areaSqm,
        neededBy: draft.neededBy,
        neededByTime: draft.neededByTime,
        notes: draft.notes,
        attachments: draft.attachments.map((attachment) =>
            typeof attachment === 'object' && attachment !== null
                ? { ...attachment }
                : attachment,
        ) as T['attachments'],
    };
};

/**
 * The draft that waits for the next queued service.
 *
 * The stones and applications stay, because they are copied onto every
 * standalone project that comes out of this draft.
 */
export const draftForNextInQueue = <T extends CommitDraft>(
    draft: T,
    blank: T,
    remaining: string[],
): T => ({
    ...blank,
    standaloneServiceIds: remaining,
    stoneIds: [...draft.stoneIds],
    stones: draft.stones.map((row) => ({ ...row })),
    applicationIds: [...draft.applicationIds],
});

/** A project is a standalone one when every service it is for stands alone. */
export const isStandaloneProject = (
    serviceIds: string[],
    standaloneIds: ReadonlySet<string>,
): boolean =>
    serviceIds.length > 0 && serviceIds.every((id) => standaloneIds.has(id));

/* ------------------------------------------------------------------ */
/* The request                                                          */
/* ------------------------------------------------------------------ */

export type RequestProject = {
    name: string;
    serviceIds: string[];
    applications: string[];
    stones: StoneRow[];
    location: string;
    landmark: string;
    areaSqm: string;
    neededBy: string;
    neededByTime: string;
    notes: string;
    attachments: Array<{ file: File }>;
};

/**
 * The inquiry as multipart form data, in the field names the backend's form
 * request validates: `projects[i][name]`, `projects[i][stones][k][quantity]`
 * and, for each file in the order it was attached,
 * `projects[i][attachments][j]`.
 *
 * Multipart rather than JSON because the attachments are `File` objects.
 */
export const buildInquiryFormData = (values: {
    projects: RequestProject[];
}): FormData => {
    const body = new FormData();

    values.projects.forEach((project, i) => {
        const field = (name: string): string => `projects[${i}][${name}]`;

        body.append(field('name'), project.name);

        project.serviceIds.forEach((id, k) =>
            body.append(`${field('serviceIds')}[${k}]`, id),
        );

        project.applications.forEach((id, k) =>
            body.append(`${field('applicationIds')}[${k}]`, id),
        );

        project.stones.forEach((row, k) => {
            const stone = (name: string): string =>
                `${field('stones')}[${k}][${name}]`;

            body.append(stone('stoneId'), row.stoneId);
            body.append(stone('quantity'), String(row.quantity));
            body.append(stone('edge'), row.edge);
            body.append(stone('finish'), row.finish);
        });

        body.append(field('location'), project.location);
        body.append(field('landmark'), project.landmark);
        body.append(field('areaSqm'), project.areaSqm);
        body.append(field('neededBy'), project.neededBy);
        body.append(field('neededByTime'), project.neededByTime);
        body.append(field('notes'), project.notes);

        project.attachments.forEach((attachment, j) =>
            body.append(`${field('attachments')}[${j}]`, attachment.file),
        );
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
