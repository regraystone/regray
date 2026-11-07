import { ChevronDown, FileText, Pencil, Sparkles } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { useFormContext, useWatch } from 'react-hook-form';
import AlertError from '@/components/alert-error';
import { formatSize } from '@/components/file-upload';
import { InquiryActionBar } from '@/components/inquiry-question';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import {
    attachmentMeta,
    PROJECT_NAME_MAX,
    type InquiryAttachment,
} from '@/data/inquiries';
import { useInquiryCatalog } from '@/hooks/use-inquiry-catalog';
import {
    DELIVERY_METHODS,
    PICKUP,
    reviewActions,
    STONE_ADVICE_LABEL,
    type InquiryMode,
} from '@/lib/inquiry-flow';
import {
    REQUIREMENTS_STEP,
    SERVICE_STEP,
    STONES_STEP,
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

/** Files, with a preview of the ones the browser still holds. */
const AttachmentList = ({
    attachments,
}: {
    attachments: InquiryAttachment[];
}) => (
    <ul className="mt-3 divide-y divide-dashed divide-sidebar-border">
        {attachments.map((attachment) => {
            const meta = attachmentMeta(attachment);

            return (
                <li
                    key={attachment.id}
                    className="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0"
                >
                    <span className="grid size-10 shrink-0 place-items-center overflow-hidden rounded-md border border-sidebar-border text-secondary-foreground">
                        {attachment.status === 'saved' ? (
                            <FileText className="size-5" aria-hidden />
                        ) : (
                            <AttachmentPreview file={attachment.file} />
                        )}
                    </span>
                    <span className="min-w-0 flex-1">
                        <span
                            className="block truncate text-sm font-bold"
                            title={meta.name}
                        >
                            {meta.name}
                        </span>
                        <span className="block text-xs text-secondary-foreground">
                            {formatSize(meta.size)}
                        </span>
                    </span>
                    {attachment.status === 'uploading' ? (
                        <span className="flex shrink-0 items-center gap-1.5 text-xs text-secondary-foreground">
                            <Spinner className="size-3.5 motion-reduce:animate-none" />
                            {attachment.progress}%
                        </span>
                    ) : null}
                </li>
            );
        })}
    </ul>
);

/** A section's way back to the question that asked it. */
const EditSection = ({
    label,
    locked,
    onClick,
}: {
    label: string;
    locked: boolean;
    onClick: () => void;
}) => (
    <Button
        type="button"
        variant="outline"
        size="sm"
        onClick={onClick}
        disabled={locked}
    >
        <Pencil aria-hidden />
        {label}
    </Button>
);

/** A section's heading, with its way back on the right. */
const SectionHeader = ({
    label,
    count,
    children,
}: {
    label: string;
    count?: number;
    children?: ReactNode;
}) => (
    <div className="flex flex-wrap items-center justify-between gap-2">
        <SectionLabel count={count}>{label}</SectionLabel>
        <div className="flex flex-wrap items-center gap-2">{children}</div>
    </div>
);

type ReviewProject = InquiryFormValues['projects'][number];

/**
 * One project, whole: what is needed, the stones, then the details. Each section's Edit returns to the question that asked it. A
 * visit, which only an inquiry already sent can be, has no stones to choose and
 * says so.
 *
 * The project's complaint, when the whole-form check has one, is said under the
 * header where the reader is looking when they decide what to change.
 */
const ReviewProjectCard = ({
    index,
    project,
    stones,
    message,
    locked,
    onEdit,
}: {
    index: number;
    project: ReviewProject;
    stones: Array<
        ReviewProject['stones'][number] & { stone: InquiryStoneOption }
    >;
    message: string | undefined;
    locked: boolean;
    onEdit: (step?: number) => void;
}) => {
    const catalog = useInquiryCatalog();
    const [open, setOpen] = useState(true);
    const { setValue } = useFormContext<
        InquiryFormInput,
        unknown,
        InquiryFormValues
    >();
    const [newName, setNewName] = useState<string | null>(null);

    /** Keep the typed name unless it is blank, which a project can't be. */
    const saveName = () => {
        const name = (newName ?? '').trim();

        if (name !== '') {
            setValue(`projects.${index}.name`, name, { shouldDirty: true });
        }

        setNewName(null);
    };

    const pieces = stones.reduce((total, row) => total + row.quantity, 0);
    const stoneTypes = new Set(stones.map((row) => row.stoneId)).size;
    const serviceType = catalog.serviceTypes.find(
        ({ id }) => id === project.serviceTypeId,
    );
    const service = serviceType?.services.find(
        ({ id }) => id === project.serviceId,
    );
    const chosenStones = [
        ...new Map(stones.map((row) => [row.stoneId, row.stone])).values(),
    ];
    const deliveryMethodName =
        DELIVERY_METHODS.find(({ value }) => value === project.deliveryMethod)
            ?.title ?? '';
    const isPickup = project.deliveryMethod === PICKUP;
    const plural = (count: number, one: string, many: string): string =>
        `${count} ${count === 1 ? one : many}`;

    const footer = [
        service?.name,
        stoneTypes > 0
            ? `${plural(pieces, 'piece', 'pieces')} of ${plural(stoneTypes, 'stone', 'stones')}`
            : undefined,
    ]
        .filter(Boolean)
        .join(' · ');

    const stoneTiles = (
        <div className="mt-3 grid grid-cols-2 gap-x-4 gap-y-5 sm:grid-cols-3">
            {stones.map((row, rowIndex) => (
                <div key={`${row.stoneId}-${rowIndex}`} className="min-w-0">
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
                    {row.slabSize.trim() === '' ? null : (
                        <p className="mt-1 text-xs break-words text-secondary-foreground">
                            Size: {row.slabSize.trim()}
                        </p>
                    )}
                    {row.edge === catalog.advisoryEdge &&
                    row.finish === catalog.advisoryFinish ? (
                        <p className="mt-1 text-xs text-secondary-foreground">
                            We'll advise on edge and finish
                        </p>
                    ) : (
                        <>
                            <p className="mt-1 truncate text-xs text-secondary-foreground">
                                {row.edge}
                            </p>
                            <p className="truncate text-xs text-secondary-foreground">
                                {row.finish}
                            </p>
                        </>
                    )}
                </div>
            ))}
        </div>
    );

    return (
        <section className="overflow-hidden rounded-md border border-sidebar-border bg-background">
            <Collapsible open={open} onOpenChange={setOpen}>
                <div className="flex flex-wrap items-center gap-x-3 gap-y-3 px-4 py-3 sm:px-5">
                    <h3 className="min-w-0 flex-1">
                        <span className="block text-sm font-bold break-words">
                            {project.name}
                        </span>

                        {newName === null ? (
                            <button
                                type="button"
                                onClick={() => setNewName(project.name)}
                                disabled={locked}
                                aria-label={`Change name: ${project.name}`}
                                className="mt-0.5 block cursor-pointer py-1 text-xs font-normal underline underline-offset-2 disabled:cursor-not-allowed"
                            >
                                Change name
                            </button>
                        ) : null}
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

                {newName !== null ? (
                    <div className="flex flex-col gap-2 px-4 pb-3 sm:flex-row sm:items-center sm:px-5">
                        <Input
                            value={newName}
                            onChange={(event) => setNewName(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    event.preventDefault();
                                    saveName();
                                }
                            }}
                            maxLength={PROJECT_NAME_MAX}
                            aria-label="Project name"
                            autoFocus
                            className="min-w-0 flex-1 rounded-none"
                        />
                        <div className="flex gap-2">
                            <Button type="button" size="sm" onClick={saveName}>
                                Save name
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setNewName(null)}
                            >
                                Cancel
                            </Button>
                        </div>
                    </div>
                ) : null}

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
                            <section data-testid="review-service">
                                <SectionHeader label="What you need">
                                    <EditSection
                                        label="Edit service"
                                        locked={locked}
                                        onClick={() => onEdit(SERVICE_STEP)}
                                    />
                                </SectionHeader>

                                <dl className="mt-3">
                                    <SummaryRow
                                        label="Service type"
                                        value={serviceType?.name ?? ''}
                                    />
                                    <SummaryRow
                                        label="Service"
                                        value={service?.name ?? ''}
                                    />
                                </dl>
                            </section>

                            <section data-testid="review-stones">
                                <SectionHeader
                                    label="Stones"
                                    count={chosenStones.length}
                                >
                                    <EditSection
                                        label="Edit stones"
                                        locked={locked}
                                        onClick={() => onEdit(STONES_STEP)}
                                    />
                                </SectionHeader>

                                {chosenStones.length > 0 ? (
                                    <ul className="mt-3 divide-y divide-dashed divide-sidebar-border">
                                        {chosenStones.map((stone) => (
                                            <li
                                                key={stone.slug}
                                                className="flex items-center gap-4 py-4 first:pt-0 last:pb-0"
                                                data-testid="review-stone"
                                            >
                                                <img
                                                    src={stone.thumbnail}
                                                    alt=""
                                                    loading="lazy"
                                                    className="size-14 shrink-0 rounded-md object-cover"
                                                />
                                                <p className="min-w-0 flex-1 text-sm font-bold break-words">
                                                    {stone.name}
                                                </p>
                                            </li>
                                        ))}
                                    </ul>
                                ) : project.stoneAdvice ? (
                                    <div className="mt-3 flex items-center gap-4">
                                        <span
                                            aria-hidden
                                            className="grid size-14 shrink-0 place-items-center rounded-md bg-secondary text-secondary-foreground"
                                        >
                                            <Sparkles className="size-5" />
                                        </span>
                                        <p className="min-w-0 flex-1 text-sm font-bold break-words">
                                            {STONE_ADVICE_LABEL}
                                        </p>
                                    </div>
                                ) : null}
                            </section>

                            <SectionHeader label="Details">
                                <EditSection
                                    label="Edit details"
                                    locked={locked}
                                    onClick={() => onEdit(REQUIREMENTS_STEP)}
                                />
                            </SectionHeader>

                            {stones.length > 0 ? (
                                <section>
                                    <SectionLabel count={stones.length}>
                                        Pieces, edge and finish
                                    </SectionLabel>
                                    {stoneTiles}
                                </section>
                            ) : null}

                            <section>
                                <SectionLabel>Where and when</SectionLabel>

                                <dl>
                                    <SummaryRow
                                        label="How you'll get it"
                                        value={deliveryMethodName}
                                    />
                                    {isPickup ? null : (
                                        <SummaryRow
                                            label="Location"
                                            value={project.location}
                                        />
                                    )}
                                    <SummaryRow
                                        label="A nearby landmark"
                                        value={project.landmark}
                                    />
                                    <SummaryRow
                                        label="Size of the area"
                                        value={
                                            project.areaSqm.trim() === ''
                                                ? ''
                                                : `${project.areaSqm.trim()} sqm`
                                        }
                                    />
                                    <SummaryRow
                                        label="When you need it"
                                        value={formatNeededBy(project.neededBy)}
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
                                        <AttachmentList
                                            attachments={project.attachments}
                                        />
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
    /** Reopen one project at a question, the details unless it says, prefilled. */
    onEditProject: (id: string, step?: number) => void;
    /** Ask the page to confirm before a project goes. */
    /** Start a blank project and go back to the services list. */
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
     * `edit` when changing a sent inquiry: the button
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
     * sheet would keep showing a project as it was before a change.
     */
    const projects = useWatch({ control, name: 'projects' });

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
                            stones={stones}
                            message={projectMessages[project.id]}
                            locked={locked}
                            onEdit={(step) => onEditProject(project.id, step)}
                        />
                    ))
                )}
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
            <InquiryActionBar>
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
            </InquiryActionBar>
        </div>
    );
};

export default InquiryReview;
