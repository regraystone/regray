import { toast } from 'sonner';
import { z } from 'zod';
import { serviceOptions } from '@/data/services';
import { stones } from '@/data/stones';
import type { InquiryDraftInput, InquiryFormInput } from '@/lib/inquiry-schema';

/**
 * One committed project as it sits in storage: the form's own values with the
 * attachments taken off, because a `File` holds no data an `Item` can carry.
 */
export type StoredInquiryProject = Omit<
    InquiryFormInput['projects'][number],
    'attachments'
>;

/** The project in progress, likewise reduced to what a record can hold. */
export type StoredInquiryDraftValues = Omit<InquiryDraftInput, 'attachments'>;

/**
 * Everything one autosave writes, other than the bookkeeping the storage adds.
 *
 * Handed in already stripped of attachments by `inquiryDraftState`, so what
 * gets serialized for the "has anything changed?" comparison is exactly what
 * lands in storage — a `File` has no JSON form and would otherwise make every
 * comparison disagree with itself.
 */
export type InquiryDraftState = {
    step: number;
    /** Which committed project the open draft is editing, or null when new. */
    editingProjectId: string | null;
    projects: StoredInquiryProject[];
    draft: StoredInquiryDraftValues;
    /**
     * How many files were left out because they cannot be stored. Counted
     * rather than faked: the reader is told on the way back in that these need
     * attaching again, and a placeholder that never held bytes would be a lie
     * the Review sheet repeats.
     */
    droppedAttachments: number;
};

export type StoredInquiryDraft = InquiryDraftState & {
    version: 1;
    /** When the autosave wrote it, as it is shown back to the reader. */
    savedAt: string;
};

const DRAFT_VERSION = 1;

/**
 * The one key this browser's draft for this account lives under.
 *
 * Keyed by user id so two accounts sharing a browser never read each other's
 * half-built inquiry, and versioned so a future change to the shape can refuse
 * an old record outright rather than half-understand it.
 */
const storageKey = (userId: number): string =>
    `regray.inquiry-draft.v1:${userId}`;

/**
 * Say that a write failed, once per page load.
 *
 * The autosave retries every few seconds, and a reader whose storage is full
 * or switched off would otherwise be told so on every one of them until the
 * toast stopped being funny.
 */
let hasWarnedWriteFailure = false;

const warnWriteFailure = (): void => {
    if (hasWarnedWriteFailure) {
        return;
    }

    hasWarnedWriteFailure = true;
    toast.error(
        "We couldn't save your inquiry draft in this browser. Leave this page and it will be lost.",
    );
};

const STONE_IDS = new Set(stones.map((stone) => stone.id));
const SERVICE_IDS = new Set(serviceOptions.map((service) => service.id));

/**
 * One stone's stored specification.
 *
 * The quantity takes `.catch(1)` because the count input hands back `NaN`
 * when it is emptied and `JSON.stringify` turns `NaN` into `null`: a stored
 * count of nothing reads back as one piece rather than as proof that the whole
 * draft is broken. Everything else is a plain string, because a value that is
 * not a string here is not something this app ever wrote.
 */
const storedStoneSchema = z.object({
    stoneId: z.string(),
    quantity: z.number().catch(1),
    edge: z.string(),
    finish: z.string(),
});

const storedProjectSchema = z.object({
    id: z.string(),
    name: z.string(),
    serviceIds: z.array(z.string()),
    stones: z.array(storedStoneSchema),
    location: z.string(),
    landmark: z.string(),
    areaSqm: z.string(),
    neededBy: z.string(),
    neededByTime: z.string(),
    notes: z.string(),
});

const storedDraftSchema = z.object({
    name: z.string(),
    stoneIds: z.array(z.string()),
    serviceIds: z.array(z.string()),
    standaloneServiceIds: z.array(z.string()),
    stones: z.array(storedStoneSchema),
    location: z.string(),
    landmark: z.string(),
    areaSqm: z.string(),
    neededBy: z.string(),
    neededByTime: z.string(),
    notes: z.string(),
});

/**
 * The record as it is stored, checked before it is believed.
 *
 * Deliberately structural rather than exhaustive: this is a read of our own
 * past write, and the question is "is this the shape we wrote?", not "would
 * the form accept these answers?". A reader may well have walked away from a
 * half-answered step — that is the state worth keeping, not a reason to throw
 * the draft away. A wrong `version` fails here, which is the point of it: an
 * old record is unreadable, so it is no draft at all.
 */
const storedRecordSchema = z.object({
    version: z.literal(DRAFT_VERSION),
    savedAt: z.string(),
    step: z.number(),
    editingProjectId: z.string().nullable(),
    projects: z.array(storedProjectSchema),
    draft: storedDraftSchema,
    droppedAttachments: z.number(),
});

const knownStoneIds = (ids: string[]): string[] =>
    ids.filter((id) => STONE_IDS.has(id));

const knownServiceIds = (ids: string[]): string[] =>
    ids.filter((id) => SERVICE_IDS.has(id));

const knownStoneRows = <T extends { stoneId: string }>(rows: T[]): T[] =>
    rows.filter((row) => STONE_IDS.has(row.stoneId));

/**
 * Read the stored draft back, with anything the catalogue has since dropped
 * silently taken out of it.
 *
 * A stone or service that no longer exists cannot be rendered as a card or
 * named on a badge, so its id is simply gone: the reader is not asked about a
 * material they cannot see, and the send's own validation is what says a
 * project has lost one of its services. Everything else is kept as it stands.
 */
const sanitize = (
    record: z.output<typeof storedRecordSchema>,
): StoredInquiryDraft => ({
    ...record,
    projects: record.projects.map((project) => ({
        ...project,
        serviceIds: knownServiceIds(project.serviceIds),
        stones: knownStoneRows(project.stones),
    })),
    draft: {
        ...record.draft,
        stoneIds: knownStoneIds(record.draft.stoneIds),
        serviceIds: knownServiceIds(record.draft.serviceIds),
        standaloneServiceIds: knownServiceIds(
            record.draft.standaloneServiceIds,
        ),
        stones: knownStoneRows(record.draft.stones),
    },
});

/**
 * Take the attachments off both forms and count what that cost.
 *
 * One builder for the write and for the "has anything changed?" comparison, so
 * the two are looking at the same value and a draft that only changed its
 * files cannot slip past the check or fail it.
 */
export const inquiryDraftState = (values: {
    step: number;
    editingProjectId: string | null;
    projects: InquiryFormInput['projects'];
    draft: InquiryDraftInput;
}): InquiryDraftState => {
    const strip = <T extends { attachments: unknown }>(
        entry: T,
    ): Omit<T, 'attachments'> => {
        const copy = { ...entry } as Partial<T>;

        delete copy.attachments;

        return copy as Omit<T, 'attachments'>;
    };

    const droppedAttachments =
        values.projects.reduce(
            (total, project) => total + project.attachments.length,
            0,
        ) + values.draft.attachments.length;

    return {
        step: values.step,
        editingProjectId: values.editingProjectId,
        projects: values.projects.map(strip),
        draft: strip(values.draft),
        droppedAttachments,
    };
};

/**
 * Whether a state is one a reader could not tell from a fresh stepper.
 *
 * Checked against what is written rather than against whether anything was
 * typed: opening the stepper and leaving it again has to leave no draft
 * behind, or every visit would grow one.
 */
const isBlank = (state: InquiryDraftState): boolean =>
    state.projects.length === 0 &&
    state.draft.name === '' &&
    state.draft.stoneIds.length === 0 &&
    state.draft.serviceIds.length === 0 &&
    state.draft.standaloneServiceIds.length === 0 &&
    state.draft.stones.length === 0 &&
    state.draft.location === '' &&
    state.draft.landmark === '' &&
    state.draft.areaSqm === '' &&
    state.draft.neededBy === '' &&
    state.draft.neededByTime === '' &&
    state.draft.notes === '';

/**
 * The stored draft for this account, or null when there is none worth having.
 *
 * Never throws, and treats everything it does not understand as "no draft":
 * blocked storage, a private mode that reports then refuses, an emptied quota,
 * JSON that no longer parses and a record written by another version of this
 * shape all end the same two ways — the key is taken off, so the next visit
 * starts clean, and null comes back.
 */
export const readInquiryDraft = (userId: number): StoredInquiryDraft | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    const key = storageKey(userId);
    let raw: string | null = null;

    try {
        raw = window.localStorage.getItem(key);
    } catch {
        return null;
    }

    if (raw === null) {
        return null;
    }

    const drop = (): null => {
        try {
            window.localStorage.removeItem(key);
        } catch {
            // Nothing to be done about storage that will not take a write.
        }

        return null;
    };

    let parsed: unknown;

    try {
        parsed = JSON.parse(raw);
    } catch {
        return drop();
    }

    const result = storedRecordSchema.safeParse(parsed);

    if (!result.success) {
        return drop();
    }

    return sanitize(result.data);
};

/**
 * Write the draft, or take the key off when there is nothing to keep.
 *
 * Reports whether storage now says what it was asked to say, so the autosave
 * can hold on to its last known good write when it does not. A blank state
 * removes the key rather than storing it: a stepper that was opened and
 * walked away from is not a draft, and leaving an empty record behind would
 * greet the reader with a "Continue your draft?" about nothing.
 */
export const writeInquiryDraft = (
    userId: number,
    state: InquiryDraftState,
): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    const key = storageKey(userId);

    try {
        if (isBlank(state)) {
            window.localStorage.removeItem(key);

            return true;
        }

        const record: StoredInquiryDraft = {
            version: DRAFT_VERSION,
            savedAt: new Date().toISOString(),
            ...state,
        };

        window.localStorage.setItem(key, JSON.stringify(record));

        return true;
    } catch {
        warnWriteFailure();

        return false;
    }
};

/** Forget this account's draft. Never throws. */
export const clearInquiryDraft = (userId: number): void => {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.removeItem(storageKey(userId));
    } catch {
        // Blocked storage has nothing stored to forget anyway.
    }
};
