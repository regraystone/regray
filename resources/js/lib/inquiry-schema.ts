import { z } from 'zod';
import {
    EDGE_PROFILES,
    FINISHES,
    LANDMARK_MAX,
    NOTES_MAX,
    PROJECT_NAME_MAX,
    serviceOptions,
    stones,
    type InquiryAttachment,
} from '@/data/constant';
import { LOCATIONS } from '@/data/locations';

/*
 * The step numbers live here rather than in the page because `STEP_FIELDS`
 * below is keyed by them: one owner means a step cannot be renumbered on one
 * side and still own the same fields on the other. `REVIEW_STEP` is the
 * exception and stays in the page, because it owns nothing to validate.
 */
export const STONES_STEP = 1;
export const SERVICES_STEP = 2;
export const REQUIREMENTS_STEP = 3;

/** Attachments, shared so the drop zone and the rule below cannot disagree. */
export const MAX_ATTACHMENTS = 10;
export const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;
export const ATTACHMENT_EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg'];
export const ATTACHMENT_MIME_TYPES = [
    'application/pdf',
    'image/png',
    'image/jpeg',
];

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

const STONE_IDS = new Set(stones.map((stone) => stone.id));
const SERVICE_IDS = new Set(serviceOptions.map((service) => service.id));

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
const attachmentSchema: z.ZodType<InquiryAttachment, InquiryAttachment> = z
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
            const key = `${entry.file.name}::${entry.file.size}`;

            if (claimed.has(key)) {
                ctx.addIssue({
                    code: 'custom',
                    path: [index],
                    message: `${entry.file.name} is already attached.`,
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

const stonesStepSchema = z.object({
    stoneIds: z
        .array(
            z.string().refine((id) => STONE_IDS.has(id), {
                error: 'That stone is no longer available.',
            }),
        )
        .min(1, { error: 'Select at least one stone.' }),
});

const servicesStepSchema = z.object({
    serviceIds: z
        .array(
            z.string().refine((id) => SERVICE_IDS.has(id), {
                error: 'That service is no longer available.',
            }),
        )
        .min(1, { error: 'Select at least one service.' }),
});

const requirementsStepSchema = z.object({
    projectName: z
        .string()
        .trim()
        .min(1, { error: 'Enter a project name.' })
        .max(PROJECT_NAME_MAX, {
            error: `Keep the project name to ${PROJECT_NAME_MAX} characters or fewer.`,
        }),

    /*
     * A search over a fixed list, so anything that is not one of its entries is
     * half-typed text rather than an answer we can route. The emptiness check
     * comes first so an untouched field reads as unanswered instead of as
     * something the reader mistyped.
     */
    location: z
        .string()
        .trim()
        .min(1, { error: 'Choose where the project is.' })
        .refine((location) => LOCATIONS.includes(location), {
            error: 'Choose a city or municipality from the list.',
        }),

    landmark: z
        .string()
        .trim()
        .max(LANDMARK_MAX, {
            error: `Keep the landmark to ${LANDMARK_MAX} characters or fewer.`,
        }),

    /*
     * The casts are how a `readonly string[]` of options becomes the literal
     * tuple `z.enum` wants. They widen the field back to `string`, which is what
     * lets the unanswered `''` in the defaults type-check while the rule itself
     * still only accepts real options.
     */
    edge: z.enum(EDGE_PROFILES as [string, ...string[]], {
        error: 'Choose an edge profile.',
    }),

    /*
     * The curated list, not one derived from the chosen stones. These finishes
     * are ones any of the stones can take, so the answer does not depend on step
     * 1 and changing a stone can never invalidate a finish already chosen.
     */
    finish: z.enum(FINISHES as [string, ...string[]], {
        error: 'Choose a finish.',
    }),

    /*
     * Optional, so an empty one is an answer rather than a mistake. `isFinite`
     * rather than `> 0` alone because `Number('Infinity')` is `Infinity` and
     * would otherwise pass as a real area.
     */
    areaSqm: z.string().refine(
        (value) => {
            const area = value.trim();

            return (
                area === '' ||
                (Number.isFinite(Number(area)) && Number(area) > 0)
            );
        },
        { error: 'Enter an area greater than 0.' },
    ),

    /*
     * Both sides are `YYYY-MM-DD`, which is why this is a string comparison: it
     * orders the same way the dates do.
     */
    neededBy: z
        .string()
        .refine((date) => date === '' || date >= todayIsoDate(), {
            error: 'Needed by cannot be in the past.',
        }),

    notes: z.string().max(NOTES_MAX, {
        error: `Keep the notes to ${NOTES_MAX} characters or fewer.`,
    }),

    attachments: attachmentsSchema,
});

/**
 * Every rule the inquiry form has, as one value.
 *
 * Composed rather than kept as three schemas because the form is one form: the
 * stepper only ever shows a step's own fields, but the Review step at the end
 * will need to check the whole thing in one pass.
 */
export const inquirySchema = z.object({
    ...stonesStepSchema.shape,
    ...servicesStepSchema.shape,
    ...requirementsStepSchema.shape,
});

export type InquiryFormInput = z.input<typeof inquirySchema>;
export type InquiryFormValues = z.output<typeof inquirySchema>;

/**
 * Which fields each step owns.
 *
 * Continue checks exactly these and nothing else, so a reader is never told
 * about a field on a step they have not reached yet. The order is the order the
 * fields are read in, and `shouldFocus` walks it in this order, so it is also
 * which wrong answer the reader is sent to first. Keyed by a partial record
 * because a step with nothing to check — Review, once it exists — is a real
 * case and the lookup has to be able to say so.
 */
export const STEP_FIELDS: Partial<
    Record<number, Array<keyof InquiryFormInput>>
> = {
    [STONES_STEP]: ['stoneIds'],
    [SERVICES_STEP]: ['serviceIds'],
    [REQUIREMENTS_STEP]: [
        'projectName',
        'location',
        'landmark',
        'edge',
        'finish',
        'areaSqm',
        'neededBy',
        'notes',
        'attachments',
    ],
};

export const inquiryDefaults = {
    stoneIds: [],
    serviceIds: [],
    projectName: '',
    location: '',
    landmark: '',
    edge: '',
    finish: '',
    areaSqm: '',
    neededBy: '',
    notes: '',
    attachments: [],
} satisfies InquiryFormValues;
