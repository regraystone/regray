import { z } from 'zod';
import {
    NOTES_MAX,
    PROJECT_NAME_MAX,
    attachmentMeta,
    type InquiryAttachment,
    type InquirySavedAttachment,
    type InquiryUploadAttachment,
} from '@/data/inquiries';
import {
    BRAND_STEP,
    REQUIREMENTS_STEP,
    SERVICE_STEP,
    SIZE_FIELD,
    STONES_STEP,
    answerProblem,
    asksQuantity,
    brandProblem,
    customAnswerKey,
    customAnswerProblem,
    findService,
    fulfillmentProblems,
    furnitureSizeProblem,
    linkedToStoneProblem,
    normalizedStoneAnswer,
    projectFields,
    quantityProblem,
    sizeKindFor,
    stoneFieldKey,
    stoneFields,
    type AddressSource,
    type ServiceField,
    type StoneFieldKey,
} from '@/lib/inquiry-flow';
import { isAddressComplete, type AddressValue } from '@/lib/ph-address';
import type { InquiryCatalog } from '@/types/inquiry';

/*
 * The step numbers live in `inquiry-flow` so the tests can reach them without
 * the `@/` alias. They are re-exported here because `STEP_FIELDS` below is keyed
 * by them, and one owner means a step cannot be renumbered on one side and still
 * own the same fields on the other.
 *
 * What the reader needs comes first, then the stones, then the details.
 *
 * The questions build one project and are a separate flow from the Review step,
 * which is about the inquiry as a whole. That is why `STEP_FIELDS` below starts
 * at `REQUIREMENTS_STEP`: the questions validate a draft rather than the form the
 * request is built from, so they own no committed fields.
 */
export { BRAND_STEP, REQUIREMENTS_STEP, SERVICE_STEP, STONES_STEP };

/** Attachments, shared so the drop zone and the rule below cannot disagree. */
export const MAX_ATTACHMENTS = 10;
export const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;
export const ATTACHMENT_EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg'];
export const ATTACHMENT_MIME_TYPES = [
    'application/pdf',
    'image/png',
    'image/jpeg',
];

/** Which files an upload field takes, and how its hint names them. */
export type AcceptedFileTypes = {
    extensions: string[];
    mimeTypes: string[];
    label: string;
};

/** What a client may attach to an inquiry (`inquiry.limits.file_extensions`). */
export const CLIENT_ATTACHMENT_TYPES: AcceptedFileTypes = {
    extensions: ATTACHMENT_EXTENSIONS,
    mimeTypes: ATTACHMENT_MIME_TYPES,
    label: 'PDF, PNG, JPG or JPEG',
};

/**
 * What staff may upload as a document (quotation, job order, billing
 * statement, receipt, invoice, refund forms): PDF only, as
 * UpdateInquiryStatusRequest and UploadJobOrderRequest check.
 */
export const STAFF_DOCUMENT_TYPES: AcceptedFileTypes = {
    extensions: ['pdf'],
    mimeTypes: ['application/pdf'],
    label: 'PDF',
};

/**
 * How many projects one inquiry may hold. A client who wants another project
 * sends another inquiry. The backend holds the same number in config/inquiry.php.
 */
export const MAX_PROJECTS = 1;

/**
 * How many pieces of one stone a project may ask for.
 *
 * Exported because the quantity input's own `max` has to agree with the rule. A
 * stepper that wrote its own 9999 would refuse a value its own rule accepted.
 */
export const MAX_STONE_QUANTITY = 9999;

/**
 * How many rows of the same stone a project may hold.
 *
 * Each row represents a variant of the stone with its own edge profile and finish.
 * Exported because validation and UI both need to enforce this limit consistently.
 */
export const MAX_STONE_ROWS = 20;

/**
 * The objection an empty inquiry raises, in one place.
 *
 * The Review step has no field to hang it on: an inquiry with no projects has no
 * control to point at, so the sheet has to say it beside the section that says
 * there are none. Two copies of the sentence would be free to disagree about it.
 */
const NEEDS_A_PROJECT = 'Add at least one project.';

/**
 * Today, in the reader's own timezone, as the `YYYY-MM-DD` a date input wants.
 *
 * Deliberately not `toISOString()`, which reports the UTC day: that would tell a
 * client east of Greenwich their own chosen date is in the past. The rule below
 * and the input's own `min` both read this one definition, so they cannot end up
 * disagreeing about which day "today" is.
 */
export const todayIsoDate = (): string => {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${now.getFullYear()}-${month}-${day}`;
};

/*
 * Zod v4 takes its messages as `error`, not the v3 `message`. Both spellings
 * work at runtime, but `error` is the one v4 documents and `z.enum` only
 * honours it, so every message in this file uses it.
 */

/**
 * One uploaded file.
 *
 * `file` is checked as an instance rather than described field by field: a `File`
 * is a browser object with none of its data on the instance, so a structural
 * schema would either strip it or reject it. The annotation is what holds the
 * four keys here to the four the uploader actually writes.
 */
const uploadAttachmentSchema: z.ZodType<
    InquiryUploadAttachment,
    InquiryUploadAttachment
> = z
    .object({
        id: z.string(),
        file: z.custom<File>((value) => value instanceof File, {
            error: 'That file could not be read.',
        }),
        status: z.enum(['uploading', 'done']),
        progress: z.number(),
    })
    .superRefine((entry, ctx) => {
        const extension = entry.file.name.split('.').pop()?.toLowerCase() ?? '';

        /*
         * Both halves are checked, because either can lie. The extension is what
         * the reader typed and the type is what the browser reports, and a file
         * renamed to `.pdf` still arrives as something else.
         */
        if (
            !ATTACHMENT_EXTENSIONS.includes(extension) ||
            !ATTACHMENT_MIME_TYPES.includes(entry.file.type)
        ) {
            ctx.addIssue({
                code: 'custom',
                message: `${entry.file.name} isn't a supported type. Use PDF, PNG, JPG or JPEG.`,
            });
        }

        if (entry.file.size > MAX_ATTACHMENT_BYTES) {
            ctx.addIssue({
                code: 'custom',
                message: `${entry.file.name} is over 10 MB.`,
            });
        }
    });

/**
 * A file already stored with the inquiry being edited. Checked when it was
 * uploaded, so here it only has to be what the server described.
 */
const savedAttachmentSchema: z.ZodType<
    InquirySavedAttachment,
    InquirySavedAttachment
> = z.object({
    id: z.string(),
    status: z.literal('saved'),
    fileId: z.number(),
    name: z.string(),
    sizeBytes: z.number(),
    mimeType: z.string(),
});

const attachmentSchema: z.ZodType<InquiryAttachment, InquiryAttachment> =
    z.union([savedAttachmentSchema, uploadAttachmentSchema]);

/**
 * The whole attachment list.
 *
 * The count and the per-file rules sit on the same value because the two rules
 * about the list as a whole — how many, and how many still going — only make
 * sense against everything that is in it.
 */
const attachmentsSchema = z
    .array(attachmentSchema)
    .max(MAX_ATTACHMENTS, {
        error: `You can attach up to ${MAX_ATTACHMENTS} files.`,
    })
    .superRefine((entries, ctx) => {
        /*
         * Keyed on name *and* size, so the same drawing at two sizes is still two
         * files while an identical one twice is one file sent twice. The first
         * of a pair is kept as the real one and the repeat is named, rather than
         * dropping both.
         */
        const claimed = new Set<string>();

        entries.forEach((entry, index) => {
            const { name, size } = attachmentMeta(entry);
            const key = `${name}::${size}`;

            if (claimed.has(key)) {
                ctx.addIssue({
                    code: 'custom',
                    path: [index],
                    message: `${name} is already attached.`,
                });
                return;
            }

            claimed.add(key);
        });

        /*
         * Reported once for the list rather than once per file, because the
         * reader's answer to it is the same either way: wait. The files still in
         * flight are named by their own rows.
         */
        if (entries.some((entry) => entry.status === 'uploading')) {
            ctx.addIssue({
                code: 'custom',
                message: 'Wait for uploads to finish.',
            });
        }
    });

/** "a, b and c". */
const listed = (names: string[]): string =>
    names.length <= 1
        ? (names[0] ?? '')
        : `${names.slice(0, -1).join(', ')} and ${names.at(-1)}`;

/**
 * A stone may appear multiple times in a project, but never with the same
 * answers to every stone-scope question its service asks.
 *
 * Allows a client to specify the same stone with different finishes or edge
 * profiles (e.g., polished with a straight edge and honed with a bevel). Rows
 * with an unanswered question are skipped — they are incomplete and are first
 * flagged by the question's own rule. The complaint sits on the row's last
 * question, which is where the reader changes it.
 */
const rejectRepeatedStones = (
    entries: readonly ({ stoneId: string } & Record<StoneFieldKey, string>)[],
    questions: ServiceField[],
    ctx: z.RefinementCtx,
) => {
    const asked = questions.flatMap((field) => {
        const key = stoneFieldKey(field);

        return key === null ? [] : [{ field, key }];
    });
    const keys = asked.map(({ key }) => key);
    const seen = new Set<string>();

    entries.forEach((entry, index) => {
        // "160x320 CM" and "160 x 320 cm" are the same slab, as the server reads them.
        const values = asked.map(({ field, key }) =>
            normalizedStoneAnswer(field, entry[key]),
        );

        if (values.some((value) => value === '')) {
            return;
        }

        const signature = [entry.stoneId, ...values].join('::');

        if (seen.has(signature)) {
            ctx.addIssue({
                code: 'custom',
                path: ['stones', index, keys.at(-1) ?? 'quantity'],
                message: `Another row of this stone has the same ${listed(questions.map((field) => field.label.toLowerCase()))}. Change one of them or remove this row.`,
            });

            return;
        }

        seen.add(signature);
    });
};

/**
 * What one project is called.
 *
 * On the project rather than on the inquiry, because one request can hold ten of
 * these and a single name for all of them would say they were the same work. It
 * is also what the inquiry is known by: the reader names the main project the
 * way they would name the job, and the rest hang off it.
 */
const projectNameSchema = z
    .string()
    .trim()
    .min(1, { error: 'Give this project a name.' })
    .max(PROJECT_NAME_MAX, {
        error: `Keep the project name to ${PROJECT_NAME_MAX} characters or fewer.`,
    });

/**
 * Every rule the inquiry form has, built over the options the page was given.
 *
 * A function rather than constants because what a stone, a service, a furniture
 * type, an edge profile or a finish *is* now comes from the
 * database through the page's props: the rules that say "it has to still be on
 * the list" can only be written once the list is known.
 *
 * Where the address comes from is the reader's: a client picks one of their
 * saved addresses, staff enter one with the picker (`addressSource`).
 *
 * What else a project is asked depends on the service it is for, so those
 * rules are chosen per project, from the service its answers name: each stone
 * row is checked against the stone-scope questions that service lists, and the
 * project answers against its project-scope ones (`answerProblem`, worded as
 * StoreInquiryRequest words them). A question the service does not ask is not
 * checked, and not sent.
 */
export const createInquirySchemas = (
    catalog: InquiryCatalog,
    addressSource: AddressSource = 'book',
) => {
    const serviceIds = new Set(
        catalog.serviceTypes.flatMap(({ services }) =>
            services.map(({ id }) => id),
        ),
    );

    /*
     * A stone, by its slug. The page only holds the stones it has been sent, so
     * whether this one is still on offer is the server's to say.
     */
    const stoneIdSchema = z.string();

    /**
     * One stone inside a project: which stone, how many of it, and how it is cut.
     *
     * The three are the project rather than the inquiry, because a countertop and
     * a floor in the same building are worked differently — which is exactly why
     * the same stone may appear in two projects with two finishes.
     */
    const projectStoneSchema = z.object({
        stoneId: stoneIdSchema,

        /*
         * A count, not a string. The input is `type="number"` with
         * `valueAsNumber`, so anything unparseable arrives here as `NaN`, which is
         * how an emptied field reaches the "how many" complaint instead of the
         * "not a number" one. Only the slab services ask it, so it is checked
         * with the project (`quantityProblem`).
         */
        quantity: z.number().or(z.nan()),

        /*
         * The stone-scope answers, `''` while unanswered or not asked. Whether
         * each is asked, and what it has to be, depends on the project's
         * service, so they are checked with the project (`refineAnswers`).
         */
        slabSize: z.string(),
        thickness: z.string(),
        edge: z.string(),
        finish: z.string(),
    });

    /**
     * The stones of one committed project, and the stones of one still being
     * specified, which are the same rule.
     *
     * Neither list has to be non-empty here: whether a project needs a stone at
     * all depends on which services it is for. Both `projectSchema` and
     * `projectDraftSchema` below settle it in their own `superRefine`.
     */
    const projectStonesSchema = z
        .array(projectStoneSchema)
        .max(MAX_STONE_ROWS, {
            error: `A project can have up to ${MAX_STONE_ROWS} stone rows.`,
        });

    /**
     * Where and when one piece of work is, and anything else the team needs about
     * it.
     *
     * Per project rather than per inquiry, because one request can hold work at
     * more than one place: a countertop in Makati and a floor in Cebu are one
     * email and two different answers to the same question.
     *
     * Shared by the draft and the committed project so the two cannot come to
     * accept different answers to the same question.
     */
    const placeSchema = z.object({ code: z.string(), name: z.string() });

    const projectDetailsShape = {
        /* The brands by slug, or No preference: see `refineAnswers`. */
        brands: z.array(z.string()),
        brandNoPreference: z.boolean(),

        /*
         * Whether a method is asked, and an address, is the service's: see
         * `refineAnswers`. A client's address is one of their saved ones by
         * id; staff's is the picker's value.
         */
        deliveryMethod: z.string(),
        addressId: z.string(),
        address: z
            .object({
                region: placeSchema.nullable(),
                province: placeSchema.nullable(),
                noProvince: z.boolean(),
                city: placeSchema.nullable(),
                barangay: placeSchema.nullable(),
                postalCode: z.string(),
                lat: z.number().nullable(),
                lng: z.number().nullable(),
                needsPostalCode: z.boolean().optional(),
            })
            .nullable(),

        /*
         * The service's project-scope answers, by question slug. Checked with
         * the project, since which are asked depends on its service.
         */
        answers: z.record(z.string(), z.string()),

        /*
         * Both sides are `YYYY-MM-DD`, which is why this is a string comparison:
         * it orders the same way the dates do.
         */
        neededBy: z
            .string()
            .refine((date) => date === '' || date >= todayIsoDate(), {
                error: 'That date has already passed.',
            }),

        notes: z.string().max(NOTES_MAX, {
            error: `Keep the notes to ${NOTES_MAX} characters or fewer.`,
        }),

        /*
         * Counted per project rather than per inquiry, for the same reason the
         * site is asked for per project.
         */
        attachments: attachmentsSchema,
    };

    /**
     * The rules a project's answers have to meet together, committed or not.
     *
     * A project needs a service, and a stone or the request for a
     * recommendation, unless its service asks for no stones. Each stone row
     * answers the stone-scope questions the service asks, and the project the
     * project-scope ones; "Same as stone" needs a stone to be the same as.
     * Brands or No preference, one or the other. A service that is delivered
     * or picked up asks which, and a delivery needs its address; an installed
     * service needs its installation address. Every project needs a day,
     * because sending the inquiry books it as a schedule, for 10:00.
     */
    const refineAnswers = (
        answers: {
            serviceId: string;
            stoneAdvice: boolean;
            stoneIds: string[];
            stones: ({ stoneId: string; quantity: number } & Record<
                StoneFieldKey,
                string
            >)[];
            answers: Record<string, string>;
            brands: string[];
            brandNoPreference: boolean;
            deliveryMethod: string;
            addressId: string;
            address: AddressValue | null;
            neededBy: string;
        },
        stonesField: string,
        ctx: z.RefinementCtx,
    ): void => {
        const service = findService(catalog.serviceTypes, answers.serviceId);
        const takesStones = service?.requiresStones !== false;

        if (!serviceIds.has(answers.serviceId)) {
            ctx.addIssue({
                code: 'custom',
                path: ['serviceId'],
                message: 'Choose what you need.',
            });
        }

        if (
            takesStones &&
            answers.stoneIds.length === 0 &&
            !answers.stoneAdvice
        ) {
            ctx.addIssue({
                code: 'custom',
                path: [stonesField],
                message: 'Select at least one stone.',
            });
        }

        if (takesStones) {
            const questions = stoneFields(service);
            const countsPieces = asksQuantity(service);

            answers.stones.forEach((row, index) => {
                const pieces = countsPieces
                    ? quantityProblem(row.quantity)
                    : null;

                if (pieces !== null) {
                    ctx.addIssue({
                        code: 'custom',
                        path: ['stones', index, 'quantity'],
                        message: pieces,
                    });
                }

                for (const question of questions) {
                    const key = stoneFieldKey(question);
                    const problem =
                        key === null ? null : answerProblem(question, row[key]);

                    if (key !== null && problem !== null) {
                        ctx.addIssue({
                            code: 'custom',
                            path: ['stones', index, key],
                            message: problem,
                        });
                    }
                }
            });

            rejectRepeatedStones(answers.stones, questions, ctx);
        }

        const hasStones = takesStones && answers.stoneIds.length > 0;

        // A table's size is written the way its shape asks it (sizeKindFor).
        const sizeKind = sizeKindFor(service, answers.answers);

        for (const question of projectFields(service)) {
            const value = answers.answers[question.slug];
            const written = (value ?? '').trim();
            const problem =
                answerProblem(question, value) ??
                linkedToStoneProblem(question, value, hasStones) ??
                (question.slug === SIZE_FIELD &&
                sizeKind !== null &&
                written !== ''
                    ? furnitureSizeProblem(sizeKind, written)
                    : null);

            if (problem !== null) {
                ctx.addIssue({
                    code: 'custom',
                    path: ['answers', question.slug],
                    message: problem,
                });
            }

            const custom = customAnswerProblem(
                question,
                value,
                answers.answers[customAnswerKey(question.slug)],
            );

            if (custom !== null) {
                ctx.addIssue({
                    code: 'custom',
                    path: ['answers', customAnswerKey(question.slug)],
                    message: custom,
                });
            }
        }

        const brands = brandProblem(answers);

        if (brands !== null) {
            ctx.addIssue({ code: 'custom', path: ['brands'], message: brands });
        }

        for (const { field, message } of fulfillmentProblems(
            answers,
            service,
            addressSource,
            (address) => isAddressComplete(address as AddressValue),
        )) {
            ctx.addIssue({ code: 'custom', path: [field], message });
        }

        if (answers.neededBy === '') {
            ctx.addIssue({
                code: 'custom',
                path: ['neededBy'],
                message: 'Say when this is needed.',
            });
        }
    };

    /**
     * One project as it is committed to the inquiry.
     *
     * The `id` is here rather than derived from the index because a project has to
     * survive being reordered and removed: Edit and Remove both point at one, and
     * an index would mean Project 3 quietly became Project 2 when Project 1 went.
     */
    const projectSchema = z
        .object({
            id: z.string(),
            name: projectNameSchema,
            serviceTypeId: z.string(),
            serviceId: z.string(),
            stoneAdvice: z.boolean(),
            stones: projectStonesSchema,
            ...projectDetailsShape,
        })
        .superRefine((project, ctx) =>
            refineAnswers(
                {
                    ...project,
                    stoneIds: [
                        ...new Set(project.stones.map((row) => row.stoneId)),
                    ],
                },
                'stones',
                ctx,
            ),
        );

    /**
     * The project in progress, as its own form.
     *
     * Held outside `inquirySchema` on purpose. A reader who has picked three
     * stones and not yet said how many is not an inquiry that fails to validate,
     * they are a reader halfway through a step.
     *
     * It carries the earlier picks alongside the details rather than replacing
     * them, because that is what lets the reader walk back to the grid, change a
     * stone and come forward again without their specifications vanishing.
     */
    const projectDraftSchema = z
        .object({
            stoneIds: z.array(stoneIdSchema),
            name: projectNameSchema,
            serviceTypeId: z.string(),
            serviceId: z.string(),
            stoneAdvice: z.boolean(),
            stones: projectStonesSchema,
            ...projectDetailsShape,
        })
        .superRefine((draft, ctx) => refineAnswers(draft, 'stoneIds', ctx));

    /**
     * Every rule the inquiry form has, as one value.
     *
     * One object, and only projects, because a project carries everything the
     * request is made of. What the inquiry adds on top is the count of them.
     */
    const inquirySchema = z.object({
        projects: z
            .array(projectSchema)
            .min(1, { error: NEEDS_A_PROJECT })
            .max(MAX_PROJECTS, {
                error: 'An inquiry can only have one project.',
            }),
    });

    return { inquirySchema, projectDraftSchema };
};

type InquirySchemas = ReturnType<typeof createInquirySchemas>;

export type InquiryFormInput = z.input<InquirySchemas['inquirySchema']>;
export type InquiryFormValues = z.output<InquirySchemas['inquirySchema']>;

/**
 * The project in progress.
 *
 * Its own types rather than `InquiryFormInput`, because a draft is not part of
 * the request: it is never sent, and reading it through the inquiry's types
 * would claim otherwise.
 */
export type InquiryDraftInput = z.input<InquirySchemas['projectDraftSchema']>;
export type InquiryDraftValues = z.output<InquirySchemas['projectDraftSchema']>;

/**
 * Which fields each step owns.
 *
 * The whole-form check the Review step runs reads this, so a reader is sent from a
 * complaint to the step that can fix it rather than to the end of a flow they have
 * already finished. Keyed by a partial record because a step with nothing to check
 * — steps 1 to 3, which hold the draft rather than the inquiry — is a real case
 * and the lookup has to be able to say so.
 *
 * `projects` belongs to `REQUIREMENTS_STEP` because that is the step that commits them,
 * and Review is where a request with none of them has to be caught.
 */
export const STEP_FIELDS: Partial<
    Record<number, Array<keyof InquiryFormInput>>
> = {
    [REQUIREMENTS_STEP]: ['projects'],
};

/** A request that has not been started. */
export const inquiryDefaults = {
    projects: [],
} satisfies InquiryFormValues;

/**
 * A project that has not been started.
 *
 * Separate from `inquiryDefaults` so emptying the draft is one call and cannot
 * half-happen: a project whose stones survived but whose services did not would
 * fail `projectDraftSchema` on a step the reader thought they had finished.
 */
export const draftDefaults = {
    name: '',
    serviceTypeId: '',
    serviceId: '',
    stoneAdvice: false,
    stoneIds: [],
    stones: [],
    answers: {},
    brands: [],
    brandNoPreference: false,
    deliveryMethod: '',
    addressId: '',
    address: null,
    neededBy: '',
    notes: '',
    attachments: [],
} satisfies InquiryDraftValues;
