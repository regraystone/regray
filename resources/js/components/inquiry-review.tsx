import { useEffect, useMemo, useRef, useState } from 'react';
import { useFormContext, useWatch } from 'react-hook-form';
import { FileText, Pencil, Plus, Trash2 } from 'lucide-react';
import AlertError from '@/components/alert-error';
import { formatSize } from '@/components/file-upload';
import InquiryServiceBullets from '@/components/inquiry-service-bullets';
import StoneTile from '@/components/inquiry-stone-tile';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { serviceOptions, stones, type Stone } from '@/data/constant';
import {
    MAX_PROJECTS,
    REQUIREMENTS_STEP,
    type InquiryFormInput,
    type InquiryFormValues,
} from '@/lib/inquiry-schema';

/**
 * `Intl` rather than `toISOString()`, which reports the UTC day: a reader east
 * of Greenwich would be shown the day before the one they picked. The `T00:00`
 * is what pins the reading to their own timezone, since a bare `YYYY-MM-DD` is
 * read as UTC midnight.
 */
const neededByFormatter = new Intl.DateTimeFormat(undefined, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
});

const formatNeededBy = (value: string): string =>
    value === '' ? '' : neededByFormatter.format(new Date(`${value}T00:00`));

/**
 * A needed-by read back as one moment.
 *
 * A `datetime-local` field's own string, which is already local time and already
 * in the order it sorts, so it is read as it stands rather than rebuilt out of a
 * `Date` — one which would be read as UTC and would move the morning the reader
 * asked for to the evening before. A project with a date and no time has not been
 * asked for a moment, so it is left as the date.
 */
const formatNeededByMoment = (date: string, time: string): string =>
    time === '' ? formatNeededBy(date) : `${formatNeededBy(date)} at ${time}`;

/** One answer, or the fact that there was none. */
const NotProvided = () => (
    <span className="font-normal text-secondary-foreground">Not provided</span>
);

const SummaryRow = ({
    label,
    value,
    multiline = false,
}: {
    label: string;
    value: string;
    multiline?: boolean;
}) => (
    <div className="min-w-0">
        <dt className="text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
            {label}
        </dt>
        <dd
            className={`mt-1.5 text-sm font-bold break-words text-foreground ${
                // Notes keep the reader's own line breaks, or a list of short
                // answers arrives as one paragraph.
                multiline ? 'whitespace-pre-wrap' : ''
            }`}
        >
            {value === '' ? <NotProvided /> : value}
        </dd>
    </div>
);

/**
 * The title, its count, the ways to change it, and the section's complaint.
 *
 * The rule under the title is the only thing marking where a section begins, so
 * it does the work a box used to: it holds the heading to the content beneath it
 * and lets both run the full width of the page.
 *
 * `onChange` and `onRemove` are optional because they are the two things a section
 * can do, and the empty section at the top has only one of them: there is no
 * project to remove.
 */
const SectionHeader = ({
    title,
    eyebrow,
    count,
    message,
    changeLabel,
    onChange,
    removeLabel,
    onRemove,
    locked,
}: {
    title: string;
    /** Small line above the title, for the thing that is not part of the name. */
    eyebrow?: string;
    count?: string;
    message?: string;
    changeLabel?: string;
    onChange?: () => void;
    removeLabel?: string;
    onRemove?: () => void;
    locked: boolean;
}) => (
    <>
        <div className="flex flex-wrap items-end justify-between gap-x-6 gap-y-3 border-b border-sidebar-border pb-4">
            <div className="min-w-0">
                {eyebrow ? (
                    <p className="text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
                        {eyebrow}
                    </p>
                ) : null}
                <h3
                    className={`text-lg font-bold tracking-[-0.03em] break-words text-foreground sm:text-xl ${eyebrow ? 'mt-2' : ''}`}
                >
                    {title}
                </h3>
            </div>

            <div className="flex shrink-0 items-center gap-3">
                {count ? (
                    <p className="text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
                        {count}
                    </p>
                ) : null}

                {onRemove ? (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={onRemove}
                        disabled={locked}
                        aria-label={removeLabel}
                        className="shrink-0"
                    >
                        <Trash2 aria-hidden />
                        Remove
                    </Button>
                ) : null}

                {onChange ? (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={onChange}
                        disabled={locked}
                        aria-label={changeLabel}
                        className="shrink-0"
                    >
                        <Pencil aria-hidden />
                        Change
                    </Button>
                ) : null}
            </div>
        </div>

        {/*
         * The same complaint is said twice on purpose: once in the list at the
         * top, which is what a screen reader meets first, and once here, under
         * the rule, which is where the reader is looking when they decide what
         * to change.
         */}
        {message ? (
            <p
                role="alert"
                className="mt-5 text-[0.8rem] font-semibold text-secondary-foreground"
            >
                {message}
            </p>
        ) : null}
    </>
);

/**
 * A thumbnail for one attachment.
 *
 * The object URL is made here rather than borrowed from the uploader, because
 * the uploader revokes its own on unmount and the Review step is a different
 * component that outlives neither the other nor this one. Revoked on the way
 * out for the same reason.
 */
const AttachmentPreview = ({ file }: { file: File }) => {
    const [url, setUrl] = useState<string | null>(null);

    useEffect(() => {
        if (!file.type.startsWith('image/')) {
            return;
        }

        const objectUrl = URL.createObjectURL(file);

        setUrl(objectUrl);

        return () => URL.revokeObjectURL(objectUrl);
    }, [file]);

    if (url === null) {
        return <FileText className="size-5" aria-hidden />;
    }

    return <img src={url} alt="" className="size-full object-cover" />;
};

/**
 * One project's files.
 *
 * Per project rather than one list for the request, because a floor plan belongs to
 * the floor and a kitchen elevation belongs to the counters: a single list asked the
 * reader to decide which project a drawing belonged to before they had said where
 * either project was, and the ten-file ceiling then counted drawings of ten jobs
 * against drawings of one.
 *
 * Its own ruled list rather than a grid of tiles: a plan is read by opening it, not
 * by being compared against another, so what matters is that each row names its own
 * file and says how big it is.
 */
const ProjectAttachments = ({
    attachments,
}: {
    attachments: InquiryFormValues['projects'][number]['attachments'];
}) => (
    <div className="mt-8 border-t border-sidebar-border pt-6">
        <h4 className="text-[0.65rem] font-bold tracking-[0.2em] text-secondary-foreground uppercase">
            Attachments
        </h4>

        {attachments.length === 0 ? (
            <p className="mt-2 text-sm text-secondary-foreground">
                Not provided
            </p>
        ) : (
            /*
             * Two to a row, each with its own rule: a `divide-y` across two columns
             * only rules the left one, which leaves the right column looking like it
             * is missing its top border.
             */
            <ul className="mt-4 grid gap-x-8 sm:grid-cols-2">
                {attachments.map((attachment) => (
                    <li
                        key={attachment.id}
                        className="flex min-w-0 items-center gap-3 border-t border-sidebar-border py-3"
                    >
                        <span className="grid size-10 shrink-0 place-items-center overflow-hidden border border-sidebar-border bg-background text-secondary-foreground">
                            <AttachmentPreview file={attachment.file} />
                        </span>
                        <span className="min-w-0 flex-1">
                            <span
                                className="block truncate text-sm font-bold text-foreground"
                                title={attachment.file.name}
                            >
                                {attachment.file.name}
                            </span>
                            <span className="block text-xs text-secondary-foreground">
                                {formatSize(attachment.file.size)}
                            </span>
                        </span>
                        {/*
                         * A file still in flight is shown as such, because the send
                         * is waiting on it and an unexplained wait reads as a
                         * broken button.
                         */}
                        {attachment.status === 'uploading' ? (
                            <span className="flex shrink-0 items-center gap-1.5 text-xs text-secondary-foreground">
                                <Spinner className="size-3.5 motion-reduce:animate-none" />
                                {attachment.progress}%
                            </span>
                        ) : null}
                    </li>
                ))}
            </ul>
        )}
    </div>
);

type InquiryReviewProps = {
    /** Reopen one project in the requirements step, prefilled. */
    onEditProject: (id: string) => void;
    /** Ask the page to confirm before a project goes. */
    onRemoveProject: (id: string) => void;
    /** Start a blank project and go back to the services list. */
    onAddProject: () => void;
    /**
     * One complaint about the request as a whole — no projects at all, or too many
     * — keyed by the step that owns the fields it is about. Separate from
     * `projectMessages` because a list-level complaint is not about any one of the
     * projects, and hanging it on the first one's heading would say it was.
     */
    sectionMessages: Partial<Record<number, string>>;
    /**
     * One short complaint per project, from the whole-form check, keyed by project
     * id so each lands under the project it is about rather than under the first.
     */
    projectMessages: Record<string, string>;
    /** The same complaints in full, for the alert above the page. */
    validationErrors: string[];
    /** True while the request is in flight, so nothing can change under it. */
    locked: boolean;
    onRequestQuote: () => void;
};

/**
 * The last step of the inquiry flow: everything the reader chose, and the one
 * button that sends it.
 *
 * This is a project sheet rather than another picker. Steps 1 and 2 are a list and
 * a grid of things to tap, step 3 is a form of empty controls; a page of boxes to
 * choose from one more time would ask the reader to check their own answers by
 * re-deciding them.
 *
 * One ruled section per project, read straight down. Three bordered boxes said the
 * three parts were three separate objects to be dealt with one at a time; a title,
 * a rule and the answers under it say they are one sheet of notes. It also hands
 * the whole width to the content, which is what lets the stone tiles sit four to a
 * row and each project's own answers sit three to a row.
 *
 * Everything under a project belongs to that project, because everything under it
 * was asked for that project: its stones and how they are worked, the services they
 * are wanted for, where the work is, when it is wanted and what was sent with it.
 * There is no section about the request itself, because there is no answer left that
 * is about the request rather than about one of the pieces of work in it.
 *
 * Values come from the stepper's one form rather than from props, so nothing shown
 * here can disagree with what the next send would actually carry.
 */
const InquiryReview = ({
    onEditProject,
    onRemoveProject,
    onAddProject,
    sectionMessages,
    projectMessages,
    validationErrors,
    locked,
    onRequestQuote,
}: InquiryReviewProps) => {
    const { control } = useFormContext<
        InquiryFormInput,
        unknown,
        InquiryFormValues
    >();

    /*
     * Subscribed rather than read with `watch`.
     *
     * `watch` takes a value out of the form and nothing else: it does not listen
     * for the value changing, so this sheet only showed what had been committed
     * when something else happened to re-render it. Taking a project off is
     * nothing but a change to this list, so the sheet kept showing the project
     * that had just been removed — the form said one thing and the reader was
     * looking at another.
     *
     * `useWatch` is the same read with the subscription attached, which is what
     * lets the sheet answer for the form as it is at this moment rather than as
     * it was when it was last drawn.
     */
    const projects = useWatch({ control, name: 'projects' });

    /*
     * Whether the request is full. Read here rather than left to the form's own
     * rule so the button can say so instead of pressing it and being refused.
     */
    const atCap = projects.length >= MAX_PROJECTS;

    /*
     * Resolved in the order the reader picked them rather than in the order the
     * catalogue lists them, because the order they were picked in is the
     * reader's own and is what the sheet should be reading back. A stone dropped
     * from the catalogue drops off the sheet rather than blanking it.
     */
    const projectStones = useMemo(
        () =>
            projects.map((project) => ({
                project,
                stones: project.stones
                    .map((selection) => ({
                        ...selection,
                        stone: stones.find(
                            (entry) => entry.id === selection.stoneId,
                        ),
                    }))
                    .filter(
                        (entry): entry is typeof entry & { stone: Stone } =>
                            entry.stone !== undefined,
                    ),
            })),
        [projects],
    );

    const alertRef = useRef<HTMLDivElement>(null);
    const hasMovedFocus = useRef(false);

    /*
     * Focus follows the alert, once per batch of complaints. The flag matters
     * because re-validating re-renders with the same messages, and dragging
     * focus back on every re-render would fight whatever the reader was doing
     * with it in between.
     */
    useEffect(() => {
        if (validationErrors.length === 0) {
            hasMovedFocus.current = false;
            return;
        }

        if (hasMovedFocus.current) {
            return;
        }

        hasMovedFocus.current = true;
        alertRef.current?.focus();
    }, [validationErrors]);

    return (
        <div>
            {/* Above everything, because it is the answer to the button. */}
            <div ref={alertRef} tabIndex={-1} className="outline-none">
                {validationErrors.length > 0 ? (
                    <AlertError
                        errors={validationErrors}
                        title="A few things need attention"
                    />
                ) : null}
            </div>

            {/*
             * One section per project, as a single document: a title, a rule, the
             * answers. The `mt-8` reads as the gap below the stepper whether or not
             * the alert above has shown itself, so the first rule always sits the
             * same distance down.
             *
             * Nothing is numbered in place of a name except the position each
             * project holds: a project is titled by what the reader called it, and
             * every other answer about it sits inside it rather than in a list of
             * its own further down.
             */}
            <div className="mt-8 space-y-12">
                {/*
                 * About the list rather than about any one project — too few, too
                 * many — so it is said once above them all instead of being hung on
                 * the first project's heading, where it would read as being about
                 * that project.
                 */}
                {projects.length > 0 && sectionMessages[REQUIREMENTS_STEP] ? (
                    <p
                        role="alert"
                        className="text-[0.8rem] font-semibold text-secondary-foreground"
                    >
                        {sectionMessages[REQUIREMENTS_STEP]}
                    </p>
                ) : null}

                {projects.length === 0 ? (
                    <section>
                        <SectionHeader
                            title="Projects"
                            message={sectionMessages[REQUIREMENTS_STEP]}
                            changeLabel="Add a project"
                            onChange={onAddProject}
                            locked={locked}
                        />

                        <p className="mt-6 text-sm text-secondary-foreground">
                            No projects have been added yet.
                        </p>
                    </section>
                ) : (
                    projectStones.map(({ project, stones: rows }, index) => {
                        /*
                         * Whether this project asks for material. Read off what it
                         * was committed with rather than off the tiles above it, so
                         * that a project whose stones have all been dropped from the
                         * catalogue is still described by the services it is for
                         * rather than by nothing at all.
                         */
                        const hasStones = project.stones.length > 0;

                        return (
                            <section key={project.id}>
                                {/*
                                 * The name the reader gave this project, over the
                                 * number. On the sheet a reader is about to send, the
                                 * number is the least useful thing they could be shown:
                                 * it says where this sits in a list they have to keep
                                 * their place in, and nothing about the work itself.
                                 *
                                 * The count is the project's measure, whichever it
                                 * happens to be: a project of one service that stands
                                 * on its own has no stones to count, and saying "0
                                 * stones" beside its name would read as something
                                 * missing rather than as the whole of what was asked.
                                 *
                                 * Every project can be changed, which is not quite so
                                 * when there are stones: a project that is a service
                                 * and nothing else still has a site and a date on it,
                                 * and those are as worth correcting as a finish.
                                 */}
                                <SectionHeader
                                    title={project.name}
                                    eyebrow={`Project ${index + 1}`}
                                    count={
                                        hasStones
                                            ? `${rows.length} ${
                                                  rows.length === 1
                                                      ? 'stone'
                                                      : 'stones'
                                              }`
                                            : `${project.serviceIds.length} ${
                                                  project.serviceIds.length ===
                                                  1
                                                      ? 'service'
                                                      : 'services'
                                              }`
                                    }
                                    message={projectMessages[project.id]}
                                    changeLabel={`Change ${project.name}`}
                                    onChange={() => onEditProject(project.id)}
                                    removeLabel={`Remove ${project.name}`}
                                    onRemove={() => onRemoveProject(project.id)}
                                    locked={locked}
                                />

                                {/*
                                 * The same tile the requirements step summarised them with.
                                 * It replaces the contact sheet and the separate list
                                 * of specification rows that used to sit either side
                                 * of the services — the same stone was drawn twice and
                                 * its answers were split across the page.
                                 */}
                                {rows.length > 0 ? (
                                    <ul className="mt-6 grid grid-cols-2 gap-x-6 gap-y-8 sm:grid-cols-4">
                                        {rows.map(
                                            ({
                                                stone,
                                                stoneId,
                                                quantity,
                                                edge,
                                                finish,
                                            }) => (
                                                <StoneTile
                                                    key={stoneId}
                                                    stone={stone}
                                                    quantity={quantity}
                                                    edge={edge}
                                                    finish={finish}
                                                />
                                            ),
                                        )}
                                    </ul>
                                ) : null}

                                {/*
                                 * Three to a row, because a list one name to a
                                 * row stretched across the page reads as nine
                                 * separate lines of prose rather than as nine
                                 * things included.
                                 *
                                 * A project with no stones is the one case where
                                 * the list cannot be there: its name is already the
                                 * service, so listing the service under the
                                 * service says the same thing twice about the same
                                 * work. The service's own bullets stand in its
                                 * place, which is what a reader checking a request
                                 * before sending it wants under a project with
                                 * nothing else in it.
                                 */}
                                {hasStones ? (
                                    project.serviceIds.length > 0 ? (
                                        <ul className="mt-6 grid gap-x-8 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                                            {project.serviceIds.map((id) => {
                                                const service =
                                                    serviceOptions.find(
                                                        (entry) =>
                                                            entry.id === id,
                                                    );

                                                return (
                                                    <li
                                                        key={id}
                                                        className="flex min-w-0 items-center gap-3"
                                                    >
                                                        {service ? (
                                                            <img
                                                                src={
                                                                    service.image
                                                                }
                                                                alt=""
                                                                loading="lazy"
                                                                className="size-11 shrink-0 object-cover"
                                                            />
                                                        ) : null}
                                                        <span className="min-w-0 truncate text-sm font-bold text-foreground">
                                                            {service?.name ??
                                                                id}
                                                        </span>
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    ) : null
                                ) : (
                                    <InquiryServiceBullets
                                        serviceIds={project.serviceIds}
                                    />
                                )}

                                {/*
                                 * The site's own answers, which is everything else
                                 * the requirements step asked for this project. Under the
                                 * stones and the services because they are about the
                                 * project rather than about its material, and the
                                 * order is the order they were asked in.
                                 */}
                                <dl className="mt-8 grid gap-x-10 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">
                                    <SummaryRow
                                        label="Location"
                                        value={[
                                            project.location,
                                            project.landmark,
                                        ]
                                            .filter(Boolean)
                                            .join(', ')}
                                    />
                                    <SummaryRow
                                        label="Approximate area"
                                        value={
                                            project.areaSqm.trim() === ''
                                                ? ''
                                                : `${project.areaSqm.trim()} sqm`
                                        }
                                    />
                                    <SummaryRow
                                        label="Needed by"
                                        value={formatNeededByMoment(
                                            project.neededBy,
                                            project.neededByTime,
                                        )}
                                    />
                                    {/* Notes span the row: a paragraph in one
                                     * third of the width wraps into something
                                     * unreadable. */}
                                    <div className="sm:col-span-2 lg:col-span-3">
                                        <SummaryRow
                                            label="Notes"
                                            value={project.notes}
                                            multiline
                                        />
                                    </div>
                                </dl>

                                <ProjectAttachments
                                    attachments={project.attachments}
                                />
                            </section>
                        );
                    })
                )}

                {/*
                 * A project of its own, rather than a link inside another
                 * project: adding one is not a change to an existing project, and
                 * tucking the way to do it under the last one would make it look
                 * like an action on that project.
                 *
                 * Solid, where the Change and Remove buttons above it are
                 * outlined: those two revise something already on the sheet,
                 * while this one is the only way to extend the request, and a
                 * filled button is the one the eye lands on first. The only other
                 * filled button on the step is the request at its foot, and the
                 * two are not competing — one adds to the sheet, the other sends
                 * it.
                 *
                 * Disabled at the ceiling rather than left to refuse later: the
                 * whole-form check that enforces it can only speak once the
                 * reader has pressed send, which is the last moment at which to
                 * tell them the request was never going to hold.
                 */}
                <div>
                    <Button
                        type="button"
                        variant="default"
                        size="sm"
                        onClick={onAddProject}
                        disabled={locked || atCap}
                    >
                        <Plus aria-hidden />
                        Add another project
                    </Button>

                    {atCap ? (
                        <p className="mt-2 text-xs text-secondary-foreground">
                            This request already has {MAX_PROJECTS} projects,
                            which is the most it can hold.
                        </p>
                    ) : null}
                </div>
            </div>

            {/*
             * The same bar as the other steps, at the same `mt-6`, with the one
             * button that actually sends anything. The sections above are spaced
             * further apart, but the gap into the bar is the one the reader sees
             * on every step and it should not change on the last one.
             *
             * No Back, because there is nothing before this step but the flow the
             * reader has just come through — and every project here can be reopened
             * through its own Change, which is a narrower and more useful way back
             * than a step button that would drop them at the start of a grid they
             * did not ask to see again.
             */}
            <div className="sticky bottom-0 z-10 mt-6 flex flex-col gap-3 border-t border-sidebar-border bg-background py-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="min-w-0 flex-1 text-xs text-secondary-foreground">
                    Nothing is sent until you request a quote
                </p>

                <Button
                    type="button"
                    disabled={locked}
                    onClick={onRequestQuote}
                    className="w-full sm:w-auto"
                >
                    {locked ? (
                        <>
                            <Spinner />
                            Sending...
                        </>
                    ) : (
                        'Request a Quote'
                    )}
                </Button>
            </div>
        </div>
    );
};

export default InquiryReview;
