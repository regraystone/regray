import { z } from 'zod';
import {
    LANDMARK_MAX,
    NOTES_MAX,
    PROJECT_NAME_MAX,
    attachmentMeta,
    type InquiryAttachment,
    type InquirySavedAttachment,
    type InquiryUploadAttachment,
} from '@/data/inquiries';
import {
    APPLICATION_STEP,
    REQUIREMENTS_STEP,
    SERVICES_STEP,
    STONES_STEP,
} from '@/lib/inquiry-flow';
import type { InquiryCatalog } from '@/types/inquiry';

/*
 * The step numbers live in `inquiry-flow` so the tests can reach them without
 * the `@/` alias. They are re-exported here because `STEP_FIELDS` below is keyed
 * by them, and one owner means a step cannot be renumbered on one side and still
 * own the same fields on the other.
 *
 * Services come first and stones second, because the services are what tell the
 * reader whether they have a project of the kind that needs stones at all.
 * Application comes after stones and before requirements. A draft of nothing but
 * standalone services (a site visit) goes straight from services to requirements.
 *
 * Steps 1 to 4 build one project and are a separate flow from the Review step,
 * which is about the inquiry as a whole. That is why `STEP_FIELDS` below starts
 * at `REQUIREMENTS_STEP`: the first four steps validate a draft rather than the
 * form the request is built from, so they own no committed fields.
 */
export { APPLICATION_STEP, REQUIREMENTS_STEP, SERVICES_STEP, STONES_STEP };

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

/**
 * What a time input hands back: two digits, a colon, two digits.
 *
 * Anchored and bounded on both ends so that `9:00`, `9:00:00` and half past
 * nothing are all refused — the field is a `<input type="time">`, so anything
 * else in the value is something no reader put there on purpose.
 */
const HH_MM = /^(?:[01]\d|2[0-3]):[0-5]\d$/;

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

/**
 * A stone may appear multiple times in a project, but never with identical
 * edge profile and finish combinations.
 *
 * On the list rather than on the single stone because it is about two of them.
 * Shared by both lists below so the rule is written once.
 *
 * Allows a client to specify the same stone with different finishes or edge
 * profiles (e.g., polished with a straight edge and honed with a bevel). Rows
 * with empty edge or finish are skipped — they are incomplete and should first
 * be flagged by the individual field validators.
 */
const rejectRepeatedStones = (
    entries: readonly { stoneId: string; edge: string; finish: string }[],
    ctx: z.RefinementCtx,
) => {
    const seen = new Map<string, number>();

    entries.forEach((entry, index) => {
        if (entry.edge === '' || entry.finish === '') {
            return;
        }

        const key = `${entry.stoneId}::${entry.edge}::${entry.finish}`;

        if (seen.has(key)) {
            ctx.addIssue({
                code: 'custom',
                path: [index, 'finish'],
                message:
                    'Another row of this stone has the same edge profile and finish. Change one of them or remove this row.',
            });

            return;
        }

        seen.set(key, index);
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
 * A function rather than constants because what a stone, a service, an
 * application, an edge profile, a finish or a location *is* now comes from the
 * database through the page's props: the rules that say "it has to still be on
 * the list" can only be written once the list is known.
 */
export const createInquirySchemas = (catalog: InquiryCatalog) => {
    const stoneIds = new Set(catalog.stones.map((stone) => stone.slug));
    const serviceIds = new Set(catalog.services.map((service) => service.id));
    const applicationIds = new Set(
        catalog.applications.map((application) => application.id),
    );
    const edgeNames = new Set(catalog.edgeProfiles.map((edge) => edge.name));
    const finishNames = new Set(catalog.finishes.map((finish) => finish.name));
    const standaloneIds = catalog.standaloneServiceIds;
    const servicesById = new Map(
        catalog.services.map((service) => [service.id, service]),
    );

    /**
     * Every picked service needs one of its own kinds of work, such as "Stone
     * supply only". The complaint names the service and sits under its row, at
     * `serviceOptions.<service id>`, so step 1 can show it there.
     */
    const requireServiceOptions = (
        ids: string[],
        options: Record<string, string>,
        ctx: z.RefinementCtx,
    ): void => {
        ids.forEach((id) => {
            const service = servicesById.get(id);
            const picked = options[id];

            if (
                service === undefined ||
                service.options.some((option) => option.id === picked)
            ) {
                return;
            }

            ctx.addIssue({
                code: 'custom',
                path: ['serviceOptions', id],
                message: `Choose what you need for ${service.name}.`,
            });
        });
    };

    /** The kind of work picked under each service, by service id. */
    const serviceOptionsSchema = z.record(z.string(), z.string());

    /**
     * Whether a project's service is one the team attends at an hour.
     *
     * Every service answers "when would you like this?", but not every answer is
     * the same shape. A countertop is wanted before a closing on a Friday, which
     * is a day. A survey, a dismantling and a quality check are wanted for a
     * morning on a site, and the team has to be there at that hour, so for those
     * a day alone leaves the reader's actual question unanswered.
     *
     * Read off the catalogue's own `standalone` flag rather than off a list kept
     * here: a service that stands on its own is exactly a service nobody is
     * making or installing, so the two cannot come to disagree about which these
     * are.
     */
    const needsNeededByTime = (ids: string[]): boolean =>
        ids.length > 0 && ids.every((id) => standaloneIds.has(id));

    /*
     * A stone, a service and an application, as an id that has to still be in
     * the catalogue. Three definitions of one rule with a different sentence.
     */
    const stoneIdSchema = z.string().refine((id) => stoneIds.has(id), {
        error: 'That stone is no longer available.',
    });

    const serviceIdSchema = z.string().refine((id) => serviceIds.has(id), {
        error: 'That service is no longer available.',
    });

    const applicationIdSchema = z
        .string()
        .refine((id) => applicationIds.has(id), {
            error: 'That application is no longer available.',
        });

    /**
     * The applications of a project, kept in the order the reader picked them.
     *
     * No rule about how many, because whether one is needed is a fact about the
     * project rather than about this list: it is needed once a service supplies
     * stone, and optional when the project is only services that stand on their
     * own. The rule that says so lives on the project and on the draft, where
     * those services are.
     */
    const applicationsSchema = z
        .array(applicationIdSchema)
        .superRefine((entries, ctx) => {
            const seen = new Set<string>();

            entries.forEach((entry, index) => {
                if (seen.has(entry)) {
                    ctx.addIssue({
                        code: 'custom',
                        path: [index],
                        message:
                            'An application can only appear once in a project.',
                    });

                    return;
                }

                seen.add(entry);
            });
        });

    /**
     * Step 2 of the project flow: which stones it is built from.
     *
     * Holds a list of ids rather than the specifications they grow into, because
     * the two answers are not made at the same time and the reader is not going
     * back for a slab count every time they change their mind about a material.
     *
     * No rule about how many, because whether one is needed depends on the
     * services, which sit in the same object as the stones.
     */
    const stonesStepSchema = z.object({
        stoneIds: z.array(stoneIdSchema),
    });

    /**
     * Step 1: which services are wanted.
     *
     * No rule of its own about how many, because the reader's answer is two
     * lists — the services this project supplies stone for, and the services
     * that stand as projects of their own — and "at least one" is a rule about
     * both of them together. That rule lives on the draft below.
     */
    const servicesStepSchema = z.object({
        serviceIds: z.array(serviceIdSchema),
    });

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
         * "not a number" one.
         */
        quantity: z
            .number({ error: 'Enter how many pieces you need.' })
            .int('Quantity has to be a whole number of pieces.')
            .min(1, { error: 'Enter at least 1 piece.' })
            .max(MAX_STONE_QUANTITY, {
                error: `Enter ${MAX_STONE_QUANTITY} pieces or fewer.`,
            }),

        /*
         * Plain strings so the unanswered `''` in the defaults type-checks, while
         * the rule itself only accepts a name the page was given.
         */
        edge: z.string().refine((edge) => edgeNames.has(edge), {
            error: 'Choose an edge profile.',
        }),

        finish: z.string().refine((finish) => finishNames.has(finish), {
            error: 'Choose a finish.',
        }),
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
        })
        .superRefine(rejectRepeatedStones);

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
    const projectDetailsShape = {
        /*
         * A search over a fixed list, so anything that is not one of its entries
         * is half-typed text rather than an answer we can route. The emptiness
         * check comes first so an untouched field reads as unanswered instead of
         * as something the reader mistyped.
         */
        location: z
            .string()
            .trim()
            .min(1, { error: 'Choose where the site is.' })
            .refine((location) => catalog.locations.includes(location), {
                error: 'Choose a city or municipality from the list.',
            }),

        landmark: z
            .string()
            .trim()
            .max(LANDMARK_MAX, {
                error: `Keep the landmark to ${LANDMARK_MAX} characters or fewer.`,
            }),

        /*
         * Optional, so an empty one is an answer rather than a mistake.
         * `isFinite` rather than `> 0` alone because `Number('Infinity')` is
         * `Infinity` and would otherwise pass as a real area.
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
         * Both sides are `YYYY-MM-DD`, which is why this is a string comparison:
         * it orders the same way the dates do.
         */
        neededBy: z
            .string()
            .refine((date) => date === '' || date >= todayIsoDate(), {
                error: 'That date has already passed.',
            }),

        /*
         * The clock half of "when it is wanted", asked for only of the services
         * that turn up at an hour rather than over a run of days. `HH:mm` because
         * that is what the control gives and what the team reads. The pairing with
         * `neededBy` is checked by the schemas that know whether this project is
         * one of those services.
         */
        neededByTime: z
            .string()
            .refine((time) => time === '' || HH_MM.test(time), {
                error: 'Enter a time as hours and minutes.',
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
     * One project as it is committed to the inquiry.
     *
     * The `id` is here rather than derived from the index because a project has to
     * survive being reordered and removed: Edit and Remove both point at one, and
     * an index would mean Project 3 quietly became Project 2 when Project 1 went.
     *
     * A project whose only services stand on its own needs no stone and no
     * application. If it has any, they are extra information and are not
     * checked against the rules that apply to a project of stone.
     */
    const projectSchema = z
        .object({
            id: z.string(),
            name: projectNameSchema,
            serviceIds: z
                .array(serviceIdSchema)
                .min(1, { error: 'Select at least one service.' }),
            serviceOptions: serviceOptionsSchema,
            applications: applicationsSchema,
            stoneAdvice: z.boolean(),
            stones: projectStonesSchema,
            ...projectDetailsShape,
        })
        .superRefine((project, ctx) => {
            requireServiceOptions(
                project.serviceIds,
                project.serviceOptions,
                ctx,
            );

            /*
             * One service that supplies something is enough to need a stone, so
             * the test is "any", not "all": a reader who wants a site survey
             * *and* a countertop has to say what the countertop is made of.
             */
            const needsStones = project.serviceIds.some(
                (id) => !standaloneIds.has(id),
            );

            if (
                needsStones &&
                project.stones.length === 0 &&
                !project.stoneAdvice
            ) {
                ctx.addIssue({
                    code: 'custom',
                    path: ['stones'],
                    message: 'Select at least one stone.',
                });
            }

            if (needsStones && project.applications.length === 0) {
                ctx.addIssue({
                    code: 'custom',
                    path: ['applications'],
                    message: 'Choose at least one place.',
                });
            }

            /*
             * The hour the team has to be on site, which only these services ask
             * for. The pairing rather than the time on its own, because a time
             * with no date is not an appointment.
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
                    `${project.neededBy}T${project.neededByTime}` <=
                    localDateTime()
                ) {
                    ctx.addIssue({
                        code: 'custom',
                        path: ['neededByTime'],
                        message: 'That time has already passed.',
                    });
                }
            } else if (project.neededBy === '') {
                /*
                 * Every project needs a day, because sending the inquiry books
                 * it as a schedule. Without a time it is booked for 10:00.
                 */
                ctx.addIssue({
                    code: 'custom',
                    path: ['neededBy'],
                    message: 'Say when this is needed.',
                });
            }
        });

    /**
     * The project in progress, as its own form.
     *
     * Held outside `inquirySchema` on purpose. A reader who has picked three
     * stones and not yet said how many is not an inquiry that fails to validate,
     * they are a reader halfway through a step.
     *
     * It carries the step 1 to 3 picks alongside the requirements answers rather
     * than replacing them, because that is what lets the reader walk back to the
     * grid, change a stone and come forward again without their specifications
     * vanishing.
     *
     * The services are two lists because they become two different things. One is
     * what this project is for; the other are services that stand as projects of
     * their own, and are worked through one at a time — the head of that list is
     * the one the requirements form is about.
     */
    const projectDraftSchema = z
        .object({
            ...stonesStepSchema.shape,
            ...servicesStepSchema.shape,
            name: projectNameSchema,
            applicationIds: applicationsSchema,
            standaloneServiceIds: z.array(serviceIdSchema),
            serviceOptions: serviceOptionsSchema,
            stoneAdvice: z.boolean(),
            stones: projectStonesSchema,
            ...projectDetailsShape,
        })
        .superRefine((draft, ctx) => {
            /*
             * "At least one service" is a rule about both lists together, so it is
             * checked here rather than on either of them.
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

            requireServiceOptions(
                [...draft.serviceIds, ...draft.standaloneServiceIds],
                draft.serviceOptions,
                ctx,
            );

            /*
             * Stones and an application are required when a service supplies
             * stone. A draft of nothing but standalone services passes both with
             * nothing picked: they are extra information for the visit.
             */
            if (
                draft.serviceIds.length > 0 &&
                draft.stoneIds.length === 0 &&
                !draft.stoneAdvice
            ) {
                ctx.addIssue({
                    code: 'custom',
                    path: ['stoneIds'],
                    message: 'Select at least one stone.',
                });
            }

            if (
                draft.serviceIds.length > 0 &&
                draft.applicationIds.length === 0
            ) {
                ctx.addIssue({
                    code: 'custom',
                    path: ['applicationIds'],
                    message: 'Choose at least one place.',
                });
            }

            /*
             * The hour, asked of the queue rather than of the draft's own
             * services: a draft whose services all stand on their own is one of
             * the queued ones being written out, and it is the head of that queue
             * the requirements form is about.
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
            } else if (draft.neededBy === '') {
                ctx.addIssue({
                    code: 'custom',
                    path: ['neededBy'],
                    message: 'Say when this is needed.',
                });
            }
        });

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
    stoneAdvice: false,
    stoneIds: [],
    serviceIds: [],
    standaloneServiceIds: [],
    serviceOptions: {},
    stones: [],
    applicationIds: [],
    location: '',
    landmark: '',
    areaSqm: '',
    neededBy: '',
    neededByTime: '',
    notes: '',
    attachments: [],
} satisfies InquiryDraftValues;
