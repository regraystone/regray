import { z } from 'zod';
import {
    LANDMARK_MAX,
    NOTES_MAX,
    PROJECT_NAME_MAX,
    type InquiryAttachment,
} from '@/data/inquiries';
import { serviceOptions, STANDALONE_SERVICE_IDS } from '@/data/services';
import { EDGE_PROFILES, FINISHES, stones } from '@/data/stones';
import { LOCATIONS } from '@/data/locations';
import { PROJECT_CATEGORIES, type ProjectCategory } from '@/data/projects';

/*
 * The step numbers live here rather than in the page because `STEP_FIELDS`
 * below is keyed by them: one owner means a step cannot be renumbered on one
 * side and still own the same fields on the other. `REVIEW_STEP` is the
 * exception and stays in the page, because it owns nothing to validate.
 *
 * Services come first and stones second, because the services are what tell the
 * reader whether they have a project of the kind that needs stones at all. A
 * reader who wants a site assessment has been made to walk a catalogue of
 * materials first to reach the question that decides the rest of their path.
 *
 * Category comes after stones and before requirements: a project with stones
 * needs to be categorized before moving to the details step.
 *
 * Steps 1 to 4 build one project and are a separate flow from the Review step,
 * which is about the inquiry as a whole. That is why `STEP_FIELDS` below starts
 * at `REQUIREMENTS_STEP`: the first four steps validate a draft rather than the
 * form the request is built from, so they own no committed fields.
 *
 * There are five steps and the fifth is the end of it. There was a Details step
 * once, asking where the work was and when it was wanted for every project at
 * once; those answers belong to a piece of work rather than to a request — a
 * countertop in Makati and a floor in Cebu are one email and two different
 * answers to the same question — so they moved into the requirements step, where
 * every other per-project answer already lives, and the step they filled had
 * nothing left to ask.
 */
export const SERVICES_STEP = 1;
export const STONES_STEP = 2;
export const CATEGORY_STEP = 3;
export const REQUIREMENTS_STEP = 4;

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
 * How many projects one inquiry may hold.
 *
 * A ceiling rather than a suggestion: ten is more than any real client sends,
 * and an unbounded list would let a reader build a request that takes an hour
 * to fill in on a phone.
 */
export const MAX_PROJECTS = 10;

/**
 * How many pieces of one stone a project may ask for.
 *
 * Exported because the quantity input's own `max` has to agree with the rule. A
 * stepper that wrote its own 9999 would refuse a value its own rule accepted.
 */
export const MAX_STONE_QUANTITY = 9999;

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

/**
 * What a time input hands back: two digits, a colon, two digits.
 *
 * Anchored and bounded on both ends so that `9:00`, `9:00:00` and half past
 * nothing are all refused — the field is a `<input type="time">`, so anything
 * else in the value is something no reader put there on purpose.
 */
const HH_MM = /^(?:[01]\d|2[0-3]):[0-5]\d$/;

/**
 * Whether a project's service is one the team attends at an hour.
 *
 * Every service in the catalogue answers "when would you like this?", but not
 * every answer is the same shape. A countertop is wanted before a closing on a
 * Friday, which is a day. A survey, a dismantling and a quality check are wanted
 * for a morning on a site, and the team has to be there at that hour, so for those
 * a day alone leaves the reader's actual question unanswered — and the team
 * ringing back to ask what time is the opposite of what a form is for.
 *
 * Read off the catalogue's own `standalone` flag rather than off a list kept here:
 * a service that stands on its own is exactly a service nobody is making or
 * installing, so the two cannot come to disagree about which these are.
 */
const needsNeededByTime = (serviceIds: string[]): boolean =>
    serviceIds.length > 0 &&
    serviceIds.every((id) => STANDALONE_SERVICE_IDS.has(id));

/**
 * This moment, to the minute, in the reader's own timezone.
 *
 * The same shape a `datetime-local` field fills in and the same one the two
 * answers are joined into before they are compared, which is what makes the
 * comparison an ordering of real instants rather than a string test. Built from
 * the clock parts rather than from `toISOString()` for the reason above: that one
 * reports UTC, and would call a reader's own afternoon yesterday evening.
 */
const localDateTime = (): string => {
    const now = new Date();
    const date = todayIsoDate();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');

    return `${date}T${hours}:${minutes}`;
};

const STONE_IDS = new Set(stones.map((stone) => stone.id));
const SERVICE_IDS = new Set(serviceOptions.map((service) => service.id));
const CATEGORY_VALUES = new Set(PROJECT_CATEGORIES.map((cat) => cat.value));

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

/*
 * A stone and a service, as an id that has to still be in the catalogue. One
 * definition for both, because the two are the same rule with a different
 * sentence and the pickers have nothing else to check against.
 */
const stoneIdSchema = z.string().refine((id) => STONE_IDS.has(id), {
    error: 'That stone is no longer available.',
});

const serviceIdSchema = z.string().refine((id) => SERVICE_IDS.has(id), {
    error: 'That service is no longer available.',
});

const categoryIdSchema = z
    .string()
    .refine((value) => CATEGORY_VALUES.has(value as ProjectCategory), {
        error: 'That category is no longer available.',
    });

/**
 * The categories of a project, kept in the order the reader picked them.
 *
 * No rule about how many, because whether one is needed is a fact about the
 * project rather than about this list: a project made of nothing but services
 * that stand on their own skips this step entirely, and the rule that says so
 * lives on the draft, where those services are.
 */
const categoriesSchema = z
    .array(categoryIdSchema)
    .superRefine((entries, ctx) => {
        const seen = new Set<string>();

        entries.forEach((entry, index) => {
            if (seen.has(entry)) {
                ctx.addIssue({
                    code: 'custom',
                    path: [index],
                    message: 'A category can only appear once in a project.',
                });

                return;
            }

            seen.add(entry);
        });
    });

/**
 * Step 2 of the project flow: which stones it is built from.
 *
 * Holds a list of ids rather than the specifications they grow into, because the
 * two answers are not made at the same time and the reader is not going back for
 * a slab count every time they change their mind about a material.
 *
 * No rule about how many, because whether one is needed is a fact about the
 * project rather than about this step: a project made of nothing but services
 * that stand on their own skips this step entirely, and the rule that says so
 * lives on the draft, where those services are.
 */
const stonesStepSchema = z.object({
    stoneIds: z.array(stoneIdSchema),
});

/**
 * Step 1: which services are wanted.
 *
 * No rule of its own about how many, because the reader's answer is two lists —
 * the services this project supplies stone for, and the services that stand as
 * projects of their own — and "at least one" is a rule about both of them
 * together. That rule lives on the draft below, where both lists are.
 */
const servicesStepSchema = z.object({
    serviceIds: z.array(serviceIdSchema),
});

/**
 * One stone inside a project: which stone, how many of it, and how it is cut.
 *
 * The three are the project rather than the inquiry, because a countertop and a
 * floor in the same building are worked differently — which is exactly why the
 * same stone may appear in two projects with two finishes and why a project may
 * hold the same stone only once.
 */
const projectStoneSchema = z.object({
    stoneId: stoneIdSchema,

    /*
     * A count, not a string. The input is `type="number"` with `valueAsNumber`,
     * so anything unparseable arrives here as `NaN`, which is how an emptied
     * field reaches the "how many" complaint instead of the "not a number" one.
     */
    quantity: z
        .number({ error: 'Enter how many pieces you need.' })
        .int('Quantity has to be a whole number of pieces.')
        .min(1, { error: 'Enter at least 1 piece.' })
        .max(MAX_STONE_QUANTITY, {
            error: `Enter ${MAX_STONE_QUANTITY} pieces or fewer.`,
        }),

    /*
     * The same `readonly string[]` casts as the old inquiry-wide pair. They widen
     * each field back to `string`, which is what lets the unanswered `''` in the
     * defaults type-check while the rule itself still only accepts real options.
     */
    edge: z.enum(EDGE_PROFILES as [string, ...string[]], {
        error: 'Choose an edge profile.',
    }),

    finish: z.enum(FINISHES as [string, ...string[]], {
        error: 'Choose a finish.',
    }),
});

/**
 * One stone twice in one project is one stone with two sets of specifications,
 * which cannot be two answers to the same question.
 *
 * On the list rather than on the single stone because it is about two of them.
 * Shared by both lists below so the rule is written once: it is the same rule
 * whether or not the list is also required to be non-empty.
 */
const rejectRepeatedStones = (
    entries: readonly { stoneId: string }[],
    ctx: z.RefinementCtx,
) => {
    const seen = new Set<string>();

    entries.forEach((entry, index) => {
        if (seen.has(entry.stoneId)) {
            ctx.addIssue({
                code: 'custom',
                path: [index, 'stoneId'],
                message: 'A stone can only appear once in a project.',
            });

            return;
        }

        seen.add(entry.stoneId);
    });
};

/**
 * The stones of one committed project, and the stones of one still being
 * specified, which are the same rule.
 *
 * Neither list has to be non-empty here, because neither question can be
 * answered here either: whether a project needs a stone at all depends on which
 * services it is for, and those sit in the same object as the stones. Both
 * `projectSchema` and `projectDraftSchema` below settle it in their own
 * `superRefine`, where the services are to hand.
 */
const projectStonesSchema = z
    .array(projectStoneSchema)
    .superRefine(rejectRepeatedStones);

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
 * Where and when one piece of work is, and anything else the team needs about it.
 *
 * Per project rather than per inquiry, because one request can hold work at more
 * than one place: a countertop in Makati and a floor in Cebu are one email and two
 * different answers to the same question, and a single answer for the request
 * would force the reader to invent a city that is not there or to send two
 * requests instead of one. It is also what makes a project mean one place and one
 * date, which is the shape the team's own quoting sheet is read in.
 *
 * Shared by the draft and the committed project so the two cannot come to accept
 * different answers to the same question, which is how a value passes a step it
 * was never checked at and fails only once it is too late to fix.
 */
const projectDetailsShape = {
    /*
     * A search over a fixed list, so anything that is not one of its entries is
     * half-typed text rather than an answer we can route. The emptiness check
     * comes first so an untouched field reads as unanswered instead of as
     * something the reader mistyped.
     */
    location: z
        .string()
        .trim()
        .min(1, { error: 'Choose where the site is.' })
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
            error: 'Target deadline cannot be in the past.',
        }),

    /*
     * The clock half of "when it is wanted", asked for only of the services that
     * turn up at an hour rather than over a run of days.
     *
     * `HH:mm` because that is what the control gives and what the team reads, and
     * because two strings in one format can be compared to each other — which is
     * what refusing a moment already gone is. The pairing with `neededBy` is
     * checked by the schemas that know whether this project is one of those
     * services, rather than here: this shape is shared with the projects that ask
     * for a date alone, and a field that has to be empty unless the project is
     * one particular kind of work is a rule that belongs with that rule.
     */
    neededByTime: z.string().refine((time) => time === '' || HH_MM.test(time), {
        error: 'Enter a time as hours and minutes.',
    }),

    notes: z.string().max(NOTES_MAX, {
        error: `Keep the notes to ${NOTES_MAX} characters or fewer.`,
    }),

    /*
     * Counted per project rather than per inquiry, for the same reason the site is
     * asked for per project: ten plans of one job is a different request from ten
     * plans of ten jobs, and one ceiling over the lot would refuse the second of
     * those for holding what the first was allowed to.
     */
    attachments: attachmentsSchema,
};

/**
 * One project as it is committed to the inquiry.
 *
 * The `id` is here rather than derived from the index because a project has to
 * survive being reordered and removed: Change and Remove both point at one, and an
 * index would mean Project 3 quietly became Project 2 when Project 1 went.
 *
 * Its stones may be none, which is the one shape this cannot be a plain object
 * for: a project whose only service stands on its own has asked for no material,
 * and the step that would normally insist on one is a step it never reaches.
 */
const projectSchema = z
    .object({
        id: z.string(),
        name: projectNameSchema,
        serviceIds: z
            .array(serviceIdSchema)
            .min(1, { error: 'Select at least one service.' }),
        categories: categoriesSchema,
        stones: projectStonesSchema,
        ...projectDetailsShape,
    })
    .superRefine((project, ctx) => {
        /*
         * One service that supplies something is enough to need a stone, so the
         * test is "any", not "all": a reader who wants a site survey *and* a
         * countertop has to say what the countertop is made of, and the survey
         * alongside it does not make that question go away.
         */
        const needsStones = project.serviceIds.some(
            (id) => !STANDALONE_SERVICE_IDS.has(id),
        );

        if (needsStones && project.stones.length === 0) {
            ctx.addIssue({
                code: 'custom',
                path: ['stones'],
                message: 'Select at least one stone.',
            });
        }

        /*
         * At least one category is required when the project has stones, since
         * those projects went through the category step. Standalone-only
         * projects have no category at all.
         */
        if (needsStones && project.categories.length === 0) {
            ctx.addIssue({
                code: 'custom',
                path: ['categories'],
                message: 'Select at least one category.',
            });
        }

        /*
         * The hour the team has to be on site, which only these services ask for.
         *
         * The pairing rather than the time on its own, because a time with no
         * date is not an appointment. Both together are one answer to one
         * question, so both are complained about at the date — which is the field
         * the reader lands on when they are sent here, and the one that decides
         * whether the hour they gave is still ahead of them.
         */
        if (needsNeededByTime(project.serviceIds)) {
            if (project.neededBy === '') {
                ctx.addIssue({
                    code: 'custom',
                    path: ['neededBy'],
                    message: 'Say when this service is needed.',
                });
            }

            if (project.neededByTime === '') {
                ctx.addIssue({
                    code: 'custom',
                    path: ['neededByTime'],
                    message: 'Say what time of day works.',
                });
            } else if (
                `${project.neededBy}T${project.neededByTime}` <= localDateTime()
            ) {
                ctx.addIssue({
                    code: 'custom',
                    path: ['neededByTime'],
                    message: 'That time has already passed.',
                });
            }
        }
    });

/**
 * The project in progress, as its own form.
 *
 * Held outside `inquirySchema` on purpose. A reader who has picked three stones
 * and not yet said how many is not an inquiry that fails to validate, they are
 * a reader halfway through a step, and mixing the two would make the request
 * unsendable for as long as they were looking at it.
 *
 * It carries the step 1 and 2 picks alongside the step 3 answers rather than
 * replacing them, because that is what lets the reader walk back to the grid,
 * change a stone and come forward again without their specifications vanishing.
 *
 * The services are two lists because they become two different things. One is what
 * this project is for; the other are services that stand as projects of their own,
 * and are worked through one at a time — the head of that list is the one the
 * requirements form is about, and committing takes it off and puts the next one in
 * its place. Keeping them apart here rather than splitting at the end is what lets
 * the requirements form say back only the services that belong to the project it
 * is about to write.
 *
 * It carries the site's own answers as well as the materials, because a project
 * is one piece of work in one place on one date and the request is only the list
 * of them.
 */
export const projectDraftSchema = z
    .object({
        ...stonesStepSchema.shape,
        ...servicesStepSchema.shape,
        name: projectNameSchema,
        categories: categoriesSchema,
        standaloneServiceIds: z.array(serviceIdSchema),
        stones: projectStonesSchema,
        ...projectDetailsShape,
    })
    .superRefine((draft, ctx) => {
        /*
         * "At least one service" is a rule about both lists together, so it is
         * checked here rather than on either of them. Checking `serviceIds` alone
         * would raise it the moment a reader had picked only services that stand
         * on their own — which is the one answer that should raise nothing.
         */
        if (
            draft.serviceIds.length === 0 &&
            draft.standaloneServiceIds.length === 0
        ) {
            ctx.addIssue({
                code: 'custom',
                path: ['serviceIds'],
                message: 'Select at least one service.',
            });
        }

        /*
         * The same question the committed project asks, asked of the draft at the
         * picks rather than at the specifications, because this is the step where
         * it is answered. `stoneIds` and not `stones`: the specifications are made
         * one step later and are seeded from these picks, so a reader standing on
         * the grid has decided whether there are stones without having been asked
         * what they are made of.
         */
        if (draft.serviceIds.length > 0 && draft.stoneIds.length === 0) {
            ctx.addIssue({
                code: 'custom',
                path: ['stoneIds'],
                message: 'Select at least one stone.',
            });
        }

        /*
         * At least one category is required when a project has stones
         * (non-standalone), since those projects reach the category step.
         * Standalone-only projects skip both stones and category.
         */
        if (draft.serviceIds.length > 0 && draft.categories.length === 0) {
            ctx.addIssue({
                code: 'custom',
                path: ['categories'],
                message: 'Select at least one category.',
            });
        }

        /*
         * The hour, asked of the queue rather than of the draft's own services.
         *
         * A draft whose services all stand on their own is one of the queued ones
         * being written out, and it is the head of that queue the requirements
         * form is about — so that is the service this rule is decided by. Reading
         * the draft's `serviceIds` instead would see an empty list and ask for
         * nothing, which is how a survey could be sent for a morning with no
         * morning in it.
         *
         * The same pairing, and the same reason, as on the committed project.
         */
        if (
            draft.serviceIds.length === 0 &&
            needsNeededByTime(draft.standaloneServiceIds)
        ) {
            if (draft.neededBy === '') {
                ctx.addIssue({
                    code: 'custom',
                    path: ['neededBy'],
                    message: 'Say when this service is needed.',
                });
            }

            if (draft.neededByTime === '') {
                ctx.addIssue({
                    code: 'custom',
                    path: ['neededByTime'],
                    message: 'Say what time of day works.',
                });
            } else if (
                `${draft.neededBy}T${draft.neededByTime}` <= localDateTime()
            ) {
                ctx.addIssue({
                    code: 'custom',
                    path: ['neededByTime'],
                    message: 'That time has already passed.',
                });
            }
        }
    });

/**
 * Every rule the inquiry form has, as one value.
 *
 * One object, and only projects, because a project now carries everything the
 * request is made of: which stones, how they are worked, which services they are
 * wanted for, and where and when the work is. What the inquiry adds on top is the
 * count of them.
 */
export const inquirySchema = z.object({
    projects: z
        .array(projectSchema)
        .min(1, { error: NEEDS_A_PROJECT })
        .max(MAX_PROJECTS, {
            error: `An inquiry can hold up to ${MAX_PROJECTS} projects.`,
        }),
});

export type InquiryFormInput = z.input<typeof inquirySchema>;
export type InquiryFormValues = z.output<typeof inquirySchema>;

/**
 * The project in progress.
 *
 * Its own types rather than `InquiryFormInput`, because a draft is not part of
 * the request: it is never sent, and reading it through the inquiry's types
 * would claim otherwise.
 */
export type InquiryDraftInput = z.input<typeof projectDraftSchema>;
export type InquiryDraftValues = z.output<typeof projectDraftSchema>;

/**
 * Which fields each step owns.
 *
 * The whole-form check the Review step runs reads this, so a reader is sent from a
 * complaint to the step that can fix it rather than to the end of a flow they have
 * already finished. Keyed by a partial record because a step with nothing to check
 * — steps 1 and 2, which hold the draft rather than the inquiry — is a real case
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
    stoneIds: [],
    serviceIds: [],
    standaloneServiceIds: [],
    stones: [],
    categories: [],
    location: '',
    landmark: '',
    areaSqm: '',
    neededBy: '',
    neededByTime: '',
    notes: '',
    attachments: [],
} satisfies InquiryDraftValues;
