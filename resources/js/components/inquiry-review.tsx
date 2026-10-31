import { ChevronDown, FileText, Pencil, Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { useFormContext, useWatch } from 'react-hook-form';
import AlertError from '@/components/alert-error';
import { formatSize } from '@/components/file-upload';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Spinner } from '@/components/ui/spinner';
import { attachmentMeta } from '@/data/inquiries';
import { useInquiryCatalog } from '@/hooks/use-inquiry-catalog';
import {
    isStandaloneProject,
    reviewActions,
    type InquiryMode,
} from '@/lib/inquiry-flow';
import {
    MAX_PROJECTS,
    REQUIREMENTS_STEP,
    type InquiryFormInput,
    type InquiryFormValues,
} from '@/lib/inquiry-schema';
import type { InquiryStoneOption } from '@/types/inquiry';

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
 * The time is appended only when there is one, because a project that asked for
 * a date has not asked for a moment: the hour is asked for by the services the
 * team attends at one, and everything else stops at the day.
 */
const formatNeededByMoment = (date: string, time: string): string =>
    time === '' ? formatNeededBy(date) : `${formatNeededBy(date)} at ${time}`;

/** One answer, or the fact that there was none. */
const NotProvided = () => (
    <span className="font-normal text-secondary-foreground">Not provided</span>
);

/**
 * A labelled value: the question on the left in muted text, the answer on the
 * right. Read as a description list so a screen reader pairs the two, which the
 * visual alignment alone would not.
 */
const SummaryRow = ({ label, value }: { label: string; value: string }) => (
    <div className="flex items-baseline justify-between gap-4 border-b border-dashed border-sidebar-border py-3 last:border-b-0 sm:gap-8">
        <dt className="text-sm text-secondary-foreground">{label}</dt>
        <dd className="min-w-0 text-right text-sm font-bold break-words">
            {value === '' ? <NotProvided /> : value}
        </dd>
    </div>
);

/** The heading of one part of a project card, with its tally. */
const SectionLabel = ({
    children,
    count,
}: {
    children: string;
    count?: number;
}) => (
    <h4 className="text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
        {children}
        {count === undefined ? null : (
            <span className="ml-2 font-normal tracking-normal">{count}</span>
        )}
    </h4>
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

type ReviewProject = InquiryFormValues['projects'][number];

/**
 * One project, whole.
 *
 * The same card the inquiry details page uses for a project, so a reader checking
 * what they are about to send sees it laid out the way they will read it once it
 * is sent: stones as tiles with their pieces, edge and finish; services as rows
 * with their pictures; the applications; and the site's own answers.
 *
 * Edit and Remove sit in the header, beside the chevron that folds the body away.
 * The project's complaint, when the whole-form check has one, is said under the
 * header where the reader is looking when they decide what to change.
 */
const ReviewProjectCard = ({
    index,
    project,
    ofMany,
    stones,
    message,
    locked,
    onEdit,
    onRemove,
}: {
    index: number;
    project: ReviewProject;
    ofMany: number;
    stones: Array<
        ReviewProject['stones'][number] & { stone: InquiryStoneOption }
    >;
    message: string | undefined;
    locked: boolean;
    onEdit: () => void;
    /** Absent when projects cannot be removed, as when editing a sent inquiry. */
    onRemove?: () => void;
}) => {
    const catalog = useInquiryCatalog();
    const [open, setOpen] = useState(true);

    const pieces = stones.reduce((total, row) => total + row.quantity, 0);
    const stoneTypes = new Set(stones.map((row) => row.stoneId)).size;
    const serviceCount = project.serviceIds.length;
    const isVisit = isStandaloneProject(
        project.serviceIds,
        catalog.standaloneServiceIds,
    );

    const applications = project.applications.flatMap((id) => {
        const application = catalog.applications.find(
            (entry) => entry.id === id,
        );

        return application === undefined ? [] : [application.name];
    });

    const services = project.serviceIds.flatMap((id) => {
        const service = catalog.services.find((entry) => entry.id === id);

        return service === undefined
            ? []
            : [
                  {
                      ...service,
                      // The kind of work picked under it, by name.
                      kind: service.options.find(
                          (option) => option.id === project.serviceOptions[id],
                      )?.name,
                  },
              ];
    });

    const plural = (count: number, one: string, many: string): string =>
        `${count} ${count === 1 ? one : many}`;

    const footer =
        stoneTypes > 0
            ? `${plural(serviceCount, 'service', 'services')} · ${plural(pieces, 'piece', 'pieces')} of ${plural(stoneTypes, 'stone', 'stones')}`
            : plural(serviceCount, 'service', 'services');

    return (
        <section className="overflow-hidden rounded-md border border-sidebar-border bg-background">
            <Collapsible open={open} onOpenChange={setOpen}>
                <div className="flex flex-wrap items-center gap-x-3 gap-y-3 px-4 py-3 sm:px-5">
                    <h3 className="min-w-0 flex-1">
                        {/*
                         * Numbered only when there is more than one. "Project 1 of
                         * 1" says the reader is partway through a set when there
                         * is no set, and on a single-project request the name is
                         * the whole of what identifies it.
                         */}
                        {ofMany > 1 ? (
                            <span className="block text-[0.65rem] font-bold tracking-[0.2em] text-secondary-foreground uppercase">
                                Project {index + 1}
                            </span>
                        ) : null}

                        <span
                            className={`block text-sm font-bold break-words ${
                                ofMany > 1 ? 'mt-1' : ''
                            }`}
                        >
                            {project.name}
                        </span>
                    </h3>

                    {stoneTypes > 0 ? (
                        <Badge
                            variant="secondary"
                            className="shrink-0 rounded-md px-2 py-0.5 text-[0.65rem] font-bold"
                        >
                            {stoneTypes}
                        </Badge>
                    ) : null}

                    <div className="flex shrink-0 items-center gap-2">
                        {onRemove ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={onRemove}
                                disabled={locked}
                                aria-label={`Remove ${project.name}`}
                            >
                                <Trash2 aria-hidden />
                                Remove
                            </Button>
                        ) : null}

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={onEdit}
                            disabled={locked}
                            aria-label={`Edit ${project.name}`}
                        >
                            <Pencil aria-hidden />
                            Edit
                        </Button>

                        <CollapsibleTrigger
                            aria-label={`${open ? 'Collapse' : 'Expand'} ${project.name}`}
                            className="flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-md text-secondary-foreground transition-colors hover:bg-secondary focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            <ChevronDown
                                className={`size-4 transition-transform ${
                                    open ? 'rotate-180' : ''
                                }`}
                                aria-hidden
                            />
                        </CollapsibleTrigger>
                    </div>
                </div>

                {/*
                 * The same complaint is said twice on purpose: once in the list at
                 * the top, which is what a screen reader meets first, and once
                 * here, which is where the reader is looking when they decide what
                 * to change.
                 */}
                {message ? (
                    <p
                        role="alert"
                        className="px-4 pb-3 text-[0.8rem] font-semibold text-secondary-foreground sm:px-5"
                    >
                        {message}
                    </p>
                ) : null}

                <CollapsibleContent>
                    <div className="border-t border-sidebar-border p-4 sm:p-5">
                        <div className="space-y-7">
                            <section>
                                <SectionLabel count={stoneTypes}>
                                    Stones
                                </SectionLabel>

                                {stones.length > 0 ? (
                                    <div className="mt-3 grid grid-cols-2 gap-x-4 gap-y-5 sm:grid-cols-3">
                                        {stones.map((row, rowIndex) => (
                                            <div
                                                key={`${row.stoneId}-${rowIndex}`}
                                                className="min-w-0"
                                            >
                                                <img
                                                    src={row.stone.thumbnail}
                                                    alt=""
                                                    loading="lazy"
                                                    className="aspect-square w-full rounded-md object-cover"
                                                />
                                                <div className="mt-2 flex items-baseline justify-between gap-x-2">
                                                    <p className="truncate text-sm font-bold">
                                                        {row.stone.name}
                                                    </p>
                                                    <p className="shrink-0 text-xs font-bold text-secondary-foreground">
                                                        {row.quantity} pcs
                                                    </p>
                                                </div>
                                                <p className="mt-1 truncate text-xs text-secondary-foreground">
                                                    {row.edge}
                                                </p>
                                                <p className="truncate text-xs text-secondary-foreground">
                                                    {row.finish}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="mt-2 text-sm text-secondary-foreground">
                                        {isVisit
                                            ? 'Nothing to supply or install — this project is a visit.'
                                            : 'No stones were chosen for this project.'}
                                    </p>
                                )}
                            </section>

                            <section>
                                <SectionLabel count={services.length}>
                                    Services
                                </SectionLabel>

                                {services.length > 0 ? (
                                    <ul className="mt-3 divide-y divide-dashed divide-sidebar-border">
                                        {services.map(
                                            ({ id, name, image, kind }) => (
                                                <li
                                                    key={id}
                                                    className="flex items-center gap-4 py-4 first:pt-0 last:pb-0"
                                                >
                                                    <p className="min-w-0 flex-1 text-sm font-bold break-words">
                                                        {name}
                                                        {kind ? (
                                                            <span className="mt-0.5 block font-normal text-secondary-foreground">
                                                                {kind}
                                                            </span>
                                                        ) : null}
                                                    </p>
                                                    {image ? (
                                                        <img
                                                            src={image}
                                                            alt=""
                                                            loading="lazy"
                                                            className="size-12 shrink-0 rounded-md object-cover"
                                                        />
                                                    ) : null}
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                ) : (
                                    <p className="mt-2 text-sm text-secondary-foreground">
                                        No services were asked for on this
                                        project.
                                    </p>
                                )}
                            </section>

                            {applications.length > 0 ? (
                                <section>
                                    <SectionLabel count={applications.length}>
                                        Applications
                                    </SectionLabel>

                                    <ul className="mt-3 divide-y divide-dashed divide-sidebar-border">
                                        {applications.map((name) => (
                                            <li
                                                key={name}
                                                className="flex items-center gap-4 py-4 first:pt-0 last:pb-0"
                                            >
                                                <p className="min-w-0 flex-1 text-sm font-bold break-words">
                                                    {name}
                                                </p>
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            ) : null}

                            <section>
                                <SectionLabel>Requirements</SectionLabel>

                                <dl>
                                    <SummaryRow
                                        label="Location"
                                        value={project.location}
                                    />
                                    <SummaryRow
                                        label="Landmark"
                                        value={project.landmark}
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
                                        label="Target deadline"
                                        value={formatNeededByMoment(
                                            project.neededBy,
                                            project.neededByTime,
                                        )}
                                    />
                                </dl>

                                <div className="mt-5 border-t border-sidebar-border pt-4">
                                    <SectionLabel>Notes</SectionLabel>
                                    {/* Kept verbatim: the client wrote line breaks on purpose. */}
                                    <p className="mt-2 text-sm break-words whitespace-pre-wrap">
                                        {project.notes ? (
                                            project.notes
                                        ) : (
                                            <NotProvided />
                                        )}
                                    </p>
                                </div>

                                <div className="mt-5 border-t border-sidebar-border pt-4">
                                    <SectionLabel>Attachments</SectionLabel>

                                    {project.attachments.length > 0 ? (
                                        <ul className="mt-3 divide-y divide-dashed divide-sidebar-border">
                                            {project.attachments.map(
                                                (attachment) => {
                                                    const meta =
                                                        attachmentMeta(
                                                            attachment,
                                                        );

                                                    return (
                                                        <li
                                                            key={attachment.id}
                                                            className="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0"
                                                        >
                                                            <span className="grid size-10 shrink-0 place-items-center overflow-hidden rounded-md border border-sidebar-border text-secondary-foreground">
                                                                {attachment.status ===
                                                                'saved' ? (
                                                                    <FileText
                                                                        className="size-5"
                                                                        aria-hidden
                                                                    />
                                                                ) : (
                                                                    <AttachmentPreview
                                                                        file={
                                                                            attachment.file
                                                                        }
                                                                    />
                                                                )}
                                                            </span>
                                                            <span className="min-w-0 flex-1">
                                                                <span
                                                                    className="block truncate text-sm font-bold"
                                                                    title={
                                                                        meta.name
                                                                    }
                                                                >
                                                                    {meta.name}
                                                                </span>
                                                                <span className="block text-xs text-secondary-foreground">
                                                                    {formatSize(
                                                                        meta.size,
                                                                    )}
                                                                </span>
                                                            </span>
                                                            {attachment.status ===
                                                            'uploading' ? (
                                                                <span className="flex shrink-0 items-center gap-1.5 text-xs text-secondary-foreground">
                                                                    <Spinner className="size-3.5 motion-reduce:animate-none" />
                                                                    {
                                                                        attachment.progress
                                                                    }
                                                                    %
                                                                </span>
                                                            ) : null}
                                                        </li>
                                                    );
                                                },
                                            )}
                                        </ul>
                                    ) : (
                                        <p className="mt-2 text-sm text-secondary-foreground">
                                            No files were attached to this
                                            project.
                                        </p>
                                    )}
                                </div>
                            </section>
                        </div>
                    </div>

                    <div className="border-t border-sidebar-border bg-secondary px-4 py-3 text-sm text-secondary-foreground">
                        {footer}
                    </div>
                </CollapsibleContent>
            </Collapsible>
        </section>
    );
};

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
    /**
     * `edit` when changing a sent inquiry: no Add or Remove, and the button
     * saves the changes instead of requesting a quote. See `reviewActions`.
     */
    mode?: InquiryMode;
};

/**
 * The last step of the inquiry flow: everything the reader chose, and the one
 * button that sends it.
 *
 * One card per project, laid out as the inquiry details page lays out the same
 * project. Everything under a project belongs to that project, because everything
 * under it was asked for that project: its stones and how they are worked, the
 * services they are wanted for, the applications, where the work is, when it is
 * wanted and what was sent with it.
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
    mode = 'create',
}: InquiryReviewProps) => {
    const catalog = useInquiryCatalog();
    const actions = reviewActions(mode);
    const { control } = useFormContext<
        InquiryFormInput,
        unknown,
        InquiryFormValues
    >();

    /*
     * Subscribed rather than read with `watch`, which takes a value out of the
     * form and nothing else: it does not listen for the value changing, so the
     * sheet would keep showing a project that had just been removed.
     */
    const projects = useWatch({ control, name: 'projects' });

    /*
     * Whether the request is full. Read here rather than left to the form's own
     * rule so the button can say so instead of pressing it and being refused.
     */
    const atCap = projects.length >= MAX_PROJECTS;

    /*
     * Resolved in the order the reader picked them rather than in the order the
     * catalogue lists them. A stone dropped from the catalogue drops off the
     * sheet rather than blanking it.
     */
    const projectStones = useMemo(
        () =>
            projects.map((project) => ({
                project,
                stones: project.stones.flatMap((selection) => {
                    const stone = catalog.stones.find(
                        (entry) => entry.slug === selection.stoneId,
                    );

                    return stone === undefined ? [] : [{ ...selection, stone }];
                }),
            })),
        [projects, catalog.stones],
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

    const requestMessage = sectionMessages[REQUIREMENTS_STEP];

    const addProjectButton: ReactNode = (
        <div>
            <Button
                type="button"
                variant="default"
                size="sm"
                onClick={onAddProject}
                disabled={locked || atCap}
            >
                <Plus aria-hidden />
                {projects.length === 0
                    ? 'Add a project'
                    : 'Add another project'}
            </Button>

            {atCap ? (
                <p className="mt-2 text-xs text-secondary-foreground">
                    This request already has {MAX_PROJECTS} projects, which is
                    the most it can hold.
                </p>
            ) : null}
        </div>
    );

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

            <div className="mt-8 space-y-6">
                {/*
                 * About the list rather than about any one project — too few, too
                 * many — so it is said once above them all instead of being hung on
                 * the first project's heading, where it would read as being about
                 * that project.
                 */}
                {requestMessage ? (
                    <p
                        role="alert"
                        className="text-[0.8rem] font-semibold text-secondary-foreground"
                    >
                        {requestMessage}
                    </p>
                ) : null}

                {projects.length === 0 ? (
                    <div className="rounded-md border border-sidebar-border bg-background p-4 sm:p-5">
                        <p className="text-sm text-secondary-foreground">
                            No projects have been added yet.
                        </p>
                    </div>
                ) : (
                    projectStones.map(({ project, stones }, index) => (
                        <ReviewProjectCard
                            key={project.id}
                            index={index}
                            project={project}
                            ofMany={projects.length}
                            stones={stones}
                            message={projectMessages[project.id]}
                            locked={locked}
                            onEdit={() => onEditProject(project.id)}
                            onRemove={
                                actions.canRemoveProject
                                    ? () => onRemoveProject(project.id)
                                    : undefined
                            }
                        />
                    ))
                )}

                {/*
                 * A project of its own, rather than a link inside another project:
                 * adding one is not a change to an existing project. Disabled at the
                 * ceiling rather than left to refuse later, because the whole-form
                 * check can only speak once the reader has pressed send.
                 */}
                {actions.canAddProject ? addProjectButton : null}
            </div>

            {/*
             * The same bar as the other steps, at the same `mt-6`, with the one
             * button that actually sends anything.
             *
             * No Back, because there is nothing before this step but the flow the
             * reader has just come through — and every project here can be reopened
             * through its own Edit, which is a narrower and more useful way back
             * than a step button that would drop them at the start of a grid they
             * did not ask to see again.
             */}
            <div className="sticky bottom-0 z-10 mt-6 flex flex-col gap-3 border-t border-sidebar-border bg-background py-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="min-w-0 flex-1 text-xs text-secondary-foreground">
                    {actions.footerNote}
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
                            {mode === 'edit' ? 'Saving...' : 'Sending...'}
                        </>
                    ) : (
                        actions.submitLabel
                    )}
                </Button>
            </div>
        </div>
    );
};

export default InquiryReview;
