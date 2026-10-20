import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ChevronDown,
    FileText,
    Image as ImageIcon,
    MessageCircle,
} from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { formatSize } from '@/components/file-upload';
import { STATUS_LABELS, StatusBadge } from '@/components/inquiry-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import type {
    InquiryFile,
    InquiryRequirementsRecord,
    InquiryStoneSelection,
} from '@/data/inquiries';
import { usePermissions } from '@/hooks/use-permissions';
import { permissions } from '@/layouts/workspace-layout';
import { index as inquiryIndex } from '@/routes/inquiry';
import { index as messageIndex } from '@/routes/message';
import { index as quoteIndex } from '@/routes/quote';
import type { QuoteInquiryDetails } from '@/types/quote';

type InquiryDetailsProps = {
    inquiry: QuoteInquiryDetails;
};

/**
 * One project as the page draws it.
 *
 * The server sends it already resolved — stones with their names and pictures,
 * services with theirs — so the page looks nothing up. A project with no stones
 * is kept: the services the team attends at an hour are booked on their own,
 * with nothing to supply or install, and dropping those projects would have left
 * a real request reported as an empty one.
 */
type ResolvedStone = {
    id: string;
    name: string;
    thumbnail: string;
    quantity: number;
    edge: string;
    finish: string;
};

type ResolvedProject = {
    id: string;
    name: string;
    services: Array<{ name: string; image?: string }>;
    stones: ResolvedStone[];
    /** Application names, in the order the client picked them. */
    applications: string[];
    requirements: InquiryRequirementsRecord;
};

/** Pieces rather than types: how much was asked for, not how many kinds of it. */
const countPieces = (
    selections: Array<Pick<InquiryStoneSelection, 'quantity'>>,
): number =>
    selections.reduce((total, selection) => total + selection.quantity, 0);

/**
 * When a status moved, read the way a reader would say it aloud.
 *
 * `Intl` rather than `toISOString()`, which reports UTC: a reader east of
 * Greenwich would be shown a day and an hour the record never had. `undefined`
 * as the locale is the reader's own, for the same reason.
 */
const dateTimeFormatter = new Intl.DateTimeFormat(undefined, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
});

const neededByFormatter = new Intl.DateTimeFormat(undefined, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
});

/**
 * A needed-by read back as one moment, or as just a day.
 *
 * `neededBy` is a bare `YYYY-MM-DD`, which is read as UTC midnight and so can
 * land on the day before the one that was picked. The `T00:00` pins the reading
 * to the reader's own timezone.
 *
 * The time is appended only when there is one, because a project that asked for
 * a date has not asked for a moment: the hour is asked for by the three services
 * the team attends at one, and everything else stops at the day.
 */
const formatNeededBy = ({
    neededBy,
    neededByTime,
}: InquiryRequirementsRecord): string | undefined => {
    if (neededBy === undefined || neededBy === '') {
        return undefined;
    }

    const day = neededByFormatter.format(new Date(`${neededBy}T00:00`));

    return neededByTime === undefined || neededByTime === ''
        ? day
        : `${day} at ${neededByTime}`;
};

/** One answer, or the fact that there was none. */
const NotProvided = () => (
    <span className="font-normal text-secondary-foreground">Not provided</span>
);

/**
 * A labelled value, in the shape the order summary uses: the question on the
 * left in muted text, the answer on the right. Read as a description list so a
 * screen reader pairs the two, which the visual alignment alone would not.
 */
const SummaryRow = ({ label, value }: { label: string; value?: string }) => (
    <div className="flex items-baseline justify-between gap-4 border-b border-dashed border-sidebar-border py-3 last:border-b-0 sm:gap-8">
        <dt className="text-sm text-secondary-foreground">{label}</dt>
        <dd className="min-w-0 text-right text-sm font-bold break-words">
            {value === undefined || value === '' ? <NotProvided /> : value}
        </dd>
    </div>
);

/**
 * The card every band on this page sits in, whoever wrote the heading.
 *
 * The header row carries whatever the section has to report about itself — a
 * count, a status — and the chevron that folds the body away, because a reader
 * who has read the notes does not want the notes taking up the screen while they
 * read the timeline. `open` is local rather than lifted: there is no reason for
 * one card to close another, and a reader who folds the stones back after the
 * services should find them folded on the way back too.
 *
 * The heading is a slot rather than a string because the page has two kinds of
 * card that want different headings: an inquiry-wide section is a short label in
 * caps, and a project is a name somebody typed, which wants room and lower case
 * and must not be truncated to fit beside a chevron.
 */
const CollapsibleCard = ({
    header,
    badge,
    footer,
    name,
    children,
}: {
    header: ReactNode;
    badge?: ReactNode;
    footer?: ReactNode;
    /** What the card is, for the chevron, which carries no text of its own. */
    name: string;
    children: ReactNode;
}) => {
    const [open, setOpen] = useState(true);

    return (
        <section className="overflow-hidden rounded-md border border-sidebar-border bg-background">
            <Collapsible open={open} onOpenChange={setOpen}>
                <h2 className="flex items-center gap-3 px-4 py-3 sm:px-5">
                    <span className="min-w-0 flex-1">{header}</span>
                    {badge}
                    <CollapsibleTrigger
                        aria-label={`${open ? 'Collapse' : 'Expand'} ${name}`}
                        className="flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-md text-secondary-foreground transition-colors hover:bg-secondary focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    >
                        <ChevronDown
                            className={`size-4 transition-transform ${
                                open ? 'rotate-180' : ''
                            }`}
                            aria-hidden
                        />
                    </CollapsibleTrigger>
                </h2>

                <CollapsibleContent>
                    <div className="border-t border-sidebar-border p-4 sm:p-5">
                        {children}
                    </div>
                    {footer ? (
                        <div className="border-t border-sidebar-border bg-secondary px-4 py-3 text-sm text-secondary-foreground">
                            {footer}
                        </div>
                    ) : null}
                </CollapsibleContent>
            </Collapsible>
        </section>
    );
};

/** A band about the inquiry as a whole: status, client, timeline. */
const DetailsCard = ({
    title,
    badge,
    footer,
    name,
    children,
}: {
    title: string;
    /** A small status badge beside the title, where the section reports one. */
    badge?: ReactNode;
    /** The band along the bottom, which holds a count or a hint. */
    footer?: ReactNode;
    name: string;
    children: ReactNode;
}) => (
    <CollapsibleCard
        name={name}
        badge={badge}
        footer={footer}
        header={
            <span className="block truncate text-sm font-bold tracking-[0.08em] uppercase">
                {title}
            </span>
        }
    >
        {children}
    </CollapsibleCard>
);

/** The small square count a card reports on itself. */
const CountBadge = ({ count }: { count: number }) => (
    <Badge
        variant="secondary"
        className="shrink-0 rounded-md px-2 py-0.5 text-[0.65rem] font-bold"
    >
        {count}
    </Badge>
);

/**
 * The heading of one part of a card.
 *
 * A heading level below the card's own, so a screen reader reading the page
 * hears where it is: the card is the project, and these are the three answers
 * that made it up. The count beside it is set back to the normal weight so it
 * reads as a tally of the heading rather than as part of the word.
 */
const SectionLabel = ({
    children,
    count,
}: {
    children: string;
    count?: number;
}) => (
    <h3 className="text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
        {children}
        {count === undefined ? null : (
            <span className="ml-2 font-normal tracking-normal">{count}</span>
        )}
    </h3>
);

/**
 * One file the project arrived with.
 *
 * Name, size and type, and nothing else: there is no upload endpoint yet, so
 * there is no stored copy to download or preview and no control that could
 * pretend otherwise.
 */
const FileRow = ({ file }: { file: InquiryFile }) => {
    const isPdf = file.mimeType === 'application/pdf';

    return (
        <li className="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-md border border-sidebar-border">
                {isPdf ? (
                    <FileText className="size-4" aria-hidden />
                ) : (
                    <ImageIcon className="size-4" aria-hidden />
                )}
            </span>
            <span className="min-w-0 flex-1">
                {/*
                 * Truncated rather than wrapped: these are file names, and a name
                 * broken across lines stops looking like a name. The full one is
                 * kept in `title` for the pointer.
                 */}
                <span
                    className="block truncate text-sm font-bold"
                    title={file.name}
                >
                    {file.name}
                </span>
                <span className="block text-xs text-secondary-foreground">
                    {formatSize(file.sizeBytes)}
                </span>
            </span>
            <span className="sr-only">{isPdf ? 'PDF' : 'Image'}</span>
        </li>
    );
};

/**
 * One project, whole.
 *
 * Services, stones and requirements in a single card, because that is the shape
 * the reader built them in and the shape they answer for. It used to be three
 * cards — stones, services, then one set of requirements for the whole request —
 * which read well on a request with one project and fell apart on a request with
 * three: the same project name was printed twice in two different cards, and the
 * one answer that most obviously differed between projects, where and when, was
 * the one answer there was only ever room for once.
 *
 * Within it the order is what the reader entered it in: what they want done,
 * what it is made of, what it is for, and then where and when. A project
 * that was named only by its services reads in that order too, which is the
 * whole of what it is.
 */
const ProjectCard = ({
    index,
    project,
    ofMany,
}: {
    index: number;
    project: ResolvedProject;
    /** How many projects the inquiry holds, which is what decides numbering. */
    ofMany: number;
}) => {
    const pieces = countPieces(project.stones);
    const stoneTypes = new Set(project.stones.map((stone) => stone.id)).size;
    const files = project.requirements.attachments;
    const neededBy = formatNeededBy(project.requirements);
    const serviceCount = project.services.length;

    return (
        <CollapsibleCard
            name={project.name}
            badge={stoneTypes > 0 ? <CountBadge count={stoneTypes} /> : null}
            footer={
                stoneTypes > 0
                    ? `${serviceCount} ${
                          serviceCount === 1 ? 'service' : 'services'
                      } · ${pieces} ${
                          pieces === 1 ? 'piece' : 'pieces'
                      } of ${stoneTypes} ${
                          stoneTypes === 1 ? 'stone' : 'stones'
                      }`
                    : `${serviceCount} ${
                          serviceCount === 1 ? 'service' : 'services'
                      }`
            }
            header={
                <>
                    {/*
                     * Numbered only when there is more than one. "Project 1 of 1"
                     * says the reader is partway through a set when there is no
                     * set, and on a single-project request the name is the whole
                     * of what identifies it.
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
                </>
            }
        >
            <div className="space-y-7">
                <section>
                    <SectionLabel count={stoneTypes}>Stones</SectionLabel>

                    {project.stones.length > 0 ? (
                        /*
                         * A stone is the answer to a picture, so the tiles go
                         * across rather than down. Fewer columns than the
                         * stepper's, twice over — the card is narrower than the
                         * stepper's sheet, and the longest name in the catalogue
                         * needs room beside its count rather than under it.
                         */
                        <div className="mt-3 grid grid-cols-2 gap-x-4 gap-y-5 sm:grid-cols-3">
                            {project.stones.map((stone, index) => (
                                <div
                                    key={`${stone.id}-${index}`}
                                    className="min-w-0"
                                >
                                    <img
                                        src={stone.thumbnail}
                                        alt=""
                                        loading="lazy"
                                        className="aspect-square w-full rounded-md object-cover"
                                    />
                                    {/*
                                     * The count sits opposite the name rather than
                                     * under it: how many of a stone is part of
                                     * which stone, so the two read as one line. It
                                     * holds its width so a long name truncates
                                     * instead of pushing the count off the tile.
                                     */}
                                    <div className="mt-2 flex items-baseline justify-between gap-x-2">
                                        <p className="truncate text-sm font-bold">
                                            {stone.name}
                                        </p>
                                        <p className="shrink-0 text-xs font-bold text-secondary-foreground">
                                            {stone.quantity} pcs
                                        </p>
                                    </div>
                                    <p className="mt-1 truncate text-xs text-secondary-foreground">
                                        {stone.edge}
                                    </p>
                                    <p className="truncate text-xs text-secondary-foreground">
                                        {stone.finish}
                                    </p>
                                </div>
                            ))}
                        </div>
                    ) : (
                        /*
                         * Said plainly rather than treated as a gap. A project with
                         * no stones is a booked visit rather than a mistake, and a
                         * reader looking at one needs to be told so rather than
                         * left to wonder what was left out.
                         */
                        <p className="mt-2 text-sm text-secondary-foreground">
                            Nothing to supply or install — this project is a
                            visit.
                        </p>
                    )}
                </section>

                <section>
                    <SectionLabel count={serviceCount}>Services</SectionLabel>

                    {project.services.length > 0 ? (
                        /*
                         * Name and picture only. The bullets are what the service
                         * card in the stepper is for, where a client is still
                         * choosing; here they have already chosen, and the row is a
                         * record of what they picked.
                         */
                        <ul className="mt-3 divide-y divide-dashed divide-sidebar-border">
                            {project.services.map(({ name, image }) => (
                                <li
                                    key={name}
                                    className="flex items-center gap-4 py-4 first:pt-0 last:pb-0"
                                >
                                    <p className="min-w-0 flex-1 text-sm font-bold break-words">
                                        {name}
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
                            ))}
                        </ul>
                    ) : (
                        <p className="mt-2 text-sm text-secondary-foreground">
                            No services were asked for on this project.
                        </p>
                    )}
                </section>

                {project.applications.length > 0 ? (
                    <section>
                        <SectionLabel count={project.applications.length}>
                            Applications
                        </SectionLabel>

                        {/*
                         * Name only: applications are a pick, and the row is a
                         * record of what the client chose rather than a picture
                         * they still need to decide between.
                         */}
                        <ul className="mt-3 divide-y divide-dashed divide-sidebar-border">
                            {project.applications.map((application) => (
                                <li
                                    key={application}
                                    className="flex items-center gap-4 py-4 first:pt-0 last:pb-0"
                                >
                                    <p className="min-w-0 flex-1 text-sm font-bold break-words">
                                        {application}
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
                            value={project.requirements.location}
                        />
                        <SummaryRow
                            label="Landmark"
                            value={project.requirements.landmark}
                        />
                        <SummaryRow
                            label="Approximate area"
                            value={
                                project.requirements.areaSqm === undefined
                                    ? undefined
                                    : `${project.requirements.areaSqm} sqm`
                            }
                        />
                        <SummaryRow label="Target deadline" value={neededBy} />
                    </dl>

                    <div className="mt-5 border-t border-sidebar-border pt-4">
                        <h4 className="text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
                            Notes
                        </h4>
                        {/* Kept verbatim: the client wrote line breaks on purpose. */}
                        <p className="mt-2 text-sm break-words whitespace-pre-wrap">
                            {project.requirements.notes ? (
                                project.requirements.notes
                            ) : (
                                <NotProvided />
                            )}
                        </p>
                    </div>

                    <div className="mt-5 border-t border-sidebar-border pt-4">
                        <h4 className="text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
                            Attachments
                        </h4>
                        {files.length > 0 ? (
                            <>
                                <ul className="mt-3 divide-y divide-dashed divide-sidebar-border">
                                    {files.map((file) => (
                                        <FileRow key={file.id} file={file} />
                                    ))}
                                </ul>
                                <p className="mt-3 text-xs text-secondary-foreground">
                                    Files are not stored in this preview.
                                </p>
                            </>
                        ) : (
                            <p className="mt-2 text-sm text-secondary-foreground">
                                No files were attached to this project.
                            </p>
                        )}
                    </div>
                </section>
            </div>
        </CollapsibleCard>
    );
};

/** The server's nulls are the page's absent answers. */
const toRequirements = (
    requirements: QuoteInquiryDetails['projects'][number]['requirements'],
): InquiryRequirementsRecord => ({
    location: requirements.location,
    landmark: requirements.landmark ?? undefined,
    areaSqm: requirements.areaSqm ?? undefined,
    neededBy: requirements.neededBy ?? undefined,
    neededByTime: requirements.neededByTime ?? undefined,
    notes: requirements.notes ?? undefined,
    attachments: requirements.attachments as InquiryFile[],
});

const InquiryDetails = () => {
    const { inquiry } = usePage<InquiryDetailsProps>().props;
    const { can } = usePermissions();

    /*
     * The page hangs off whichever list this reader works from, and the two are
     * guarded separately, so the way back follows the permission rather than the
     * URL that happened to get here. A staff user who reached the quote URL has
     * no quote list to go back to, and vice versa.
     */
    const backHref = can(permissions.quotationsView)
        ? quoteIndex.url()
        : inquiryIndex.url();

    const inquiryProjects: ResolvedProject[] = inquiry.projects.map(
        (project) => ({
            id: project.id,
            name: project.name,
            services: project.services,
            stones: project.stones,
            applications: project.applications,
            requirements: toRequirements(project.requirements),
        }),
    );

    /*
     * The shape of the whole request, for the line under the reference.
     *
     * Counted the way the cards count rather than from the inquiry's own
     * shortcuts: stones across projects are pieces, because the same material in
     * two projects is two lots of it, and the reader is about to see that in two
     * different cards.
     */
    const projectCount = inquiryProjects.length;
    const pieceTotal = inquiryProjects.reduce(
        (total, project) => total + countPieces(project.stones),
        0,
    );
    const stoneTypeTotal = new Set(
        inquiry.projects.flatMap((project) =>
            project.stones.map((stone) => stone.id),
        ),
    ).size;

    return (
        <div className="mx-auto w-full max-w-6xl">
            <Button asChild variant="outline" className="mb-6 w-full sm:w-auto">
                <Link href={backHref}>
                    <ArrowLeft aria-hidden />
                    Back to inquiries
                </Link>
            </Button>

            <div className="mb-8 flex flex-col gap-4 border-b border-sidebar-border pb-6 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0 space-y-2">
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="text-2xl font-bold tracking-[-0.03em]">
                            {inquiry.reference}
                        </h1>
                        <StatusBadge status={inquiry.status} />
                    </div>
                    <p className="text-sm text-secondary-foreground">
                        Submitted{' '}
                        {dateTimeFormatter.format(new Date(inquiry.createdAt))}
                    </p>
                    {/*
                     * What was asked for, before any of it. The project names are
                     * deliberately not here: they are the headings of the cards
                     * below, and repeating the first one beside the reference is
                     * the same line printed twice.
                     */}
                    <p className="text-xs text-secondary-foreground">
                        {projectCount}{' '}
                        {projectCount === 1 ? 'project' : 'projects'} ·{' '}
                        {inquiry.services.length}{' '}
                        {inquiry.services.length === 1 ? 'service' : 'services'}{' '}
                        · {pieceTotal} {pieceTotal === 1 ? 'piece' : 'pieces'}{' '}
                        of {stoneTypeTotal}{' '}
                        {stoneTypeTotal === 1 ? 'stone' : 'stones'}
                    </p>
                </div>

                {/*
                 * Read-only page: the only thing it offers is a way to ask a
                 * question, and a reader with no message page has nothing to ask
                 * it on.
                 */}
                {can(permissions.messagesView) ? (
                    <Button
                        asChild
                        variant="default"
                        className="shrink-0 self-start"
                    >
                        <Link href={messageIndex.url()}>
                            <MessageCircle aria-hidden />
                            Message the team
                        </Link>
                    </Button>
                ) : null}
            </div>

            {/*
             * The side column is written first so that below `lg` it reads before
             * the projects: where this has got to, who it is for and how it got
             * there are the things worth knowing before the detail. The order
             * classes put the projects back on the left once there is room for
             * both.
             */}
            <div className="grid gap-x-6 gap-y-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div className="space-y-6 lg:order-2">
                    <DetailsCard
                        title="Status"
                        name="status"
                        badge={<StatusBadge status={inquiry.status} />}
                    >
                        <div className="space-y-5">
                            <p className="text-sm text-secondary-foreground">
                                Last updated{' '}
                                {dateTimeFormatter.format(
                                    new Date(inquiry.updatedAt),
                                )}
                            </p>
                        </div>
                    </DetailsCard>

                    <DetailsCard
                        title="Timeline"
                        name="timeline"
                        badge={
                            <CountBadge count={inquiry.statusHistory.length} />
                        }
                    >
                        {/*
                         * Newest first, so the state the inquiry is in now is the
                         * first thing read and the marker that says so is the
                         * filled one. `updatedAt` is the last entry's time, which
                         * is what makes "first" and "current" the same entry.

                         * A status and a time, and nothing else. A timeline on a
                         * read-only page answers "when did this move and to
                         * what", and anything written about the move belongs
                         * with whoever wrote it rather than here.
                         */}
                        <ol className="space-y-5">
                            {[...inquiry.statusHistory]
                                .reverse()
                                .map((change, index) => {
                                    const isCurrent = index === 0;

                                    return (
                                        <li
                                            key={`${change.status}-${change.at}`}
                                            className="flex gap-4"
                                        >
                                            <span
                                                aria-hidden
                                                className="relative flex w-3 shrink-0 justify-center"
                                            >
                                                <span
                                                    className={`mt-1 size-3 shrink-0 ${
                                                        isCurrent
                                                            ? 'bg-primary'
                                                            : 'border border-sidebar-border'
                                                    }`}
                                                />
                                                {index <
                                                inquiry.statusHistory.length -
                                                    1 ? (
                                                    <span className="absolute top-4 bottom-[-1.25rem] w-px bg-sidebar-border" />
                                                ) : null}
                                            </span>
                                            <div className="flex min-w-0 flex-1 flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                                <p
                                                    className={`text-sm font-bold ${
                                                        isCurrent
                                                            ? 'text-foreground'
                                                            : 'text-secondary-foreground'
                                                    }`}
                                                >
                                                    {
                                                        STATUS_LABELS[
                                                            change.status
                                                        ]
                                                    }
                                                </p>
                                                <time
                                                    dateTime={change.at}
                                                    className="text-xs text-secondary-foreground"
                                                >
                                                    {dateTimeFormatter.format(
                                                        new Date(change.at),
                                                    )}
                                                </time>
                                            </div>
                                        </li>
                                    );
                                })}
                        </ol>
                    </DetailsCard>
                </div>

                <div className="space-y-6 lg:order-1">
                    {projectCount > 0 ? (
                        inquiryProjects.map((project, index) => (
                            <ProjectCard
                                key={project.id}
                                index={index}
                                project={project}
                                ofMany={projectCount}
                            />
                        ))
                    ) : (
                        <div className="rounded-md border border-sidebar-border bg-background p-4 sm:p-5">
                            <p className="text-sm text-secondary-foreground">
                                This inquiry has no projects on it.
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

export default InquiryDetails;
