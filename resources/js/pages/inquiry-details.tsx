import { Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ChevronDown,
    FileText,
    Image as ImageIcon,
    Mail,
    MessageCircle,
    Phone,
} from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import { formatSize } from '@/components/file-upload';
import { STATUS_LABELS, StatusBadge } from '@/components/inquiry-status-badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Skeleton } from '@/components/ui/skeleton';
import { clients, type Client } from '@/data/clients';
import { people, type Person } from '@/data/conversations';
import {
    inquiries,
    type InquiryFile,
    type InquiryRequirementsRecord,
    type InquiryStoneSelection,
} from '@/data/inquiries';
import { PROJECT_CATEGORIES } from '@/data/projects';
import { serviceOptions } from '@/data/services';
import { stones, type Stone } from '@/data/stones';
import { useInitials } from '@/hooks/use-initials';
import { usePermissions } from '@/hooks/use-permissions';
import { permissions } from '@/layouts/workspace-layout';
import { index as inquiryIndex } from '@/routes/inquiry';
import { index as messageIndex } from '@/routes/message';
import { index as quoteIndex } from '@/routes/quote';

type InquiryDetailsProps = {
    inquiryId: string;
};

/**
 * One project with everything the reader put into it, resolved against the
 * catalogue so the page never has to look anything up while it renders.
 *
 * The stones are dropped rather than kept as ids, and through a map so a stone
 * removed from `stones` since the inquiry was raised falls off the page instead
 * of crashing it. A project's stones, their counts and how each is worked are
 * that project's own answer: the same stone in two projects is two lots of it,
 * cut two ways.
 *
 * A project with no stones is kept. The three services the team attends at an
 * hour are booked on their own, with nothing to supply or install, and dropping
 * those projects would have left a real request reported as an empty one.
 */
type ResolvedProject = {
    id: string;
    name: string;
    services: Array<{ name: string; image?: string }>;
    stones: Array<Stone & InquiryStoneSelection>;
    /** Category labels, in the order the client picked them. */
    categories: string[];
    requirements: InquiryRequirementsRecord;
};

/** Pieces rather than types: how much was asked for, not how many kinds of it. */
const countPieces = (selections: InquiryStoneSelection[]): number =>
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

/** Telephones are written the way a person reads them and dialled without. */
const dialNumber = (phone: string): string => phone.replace(/[\s()-]/g, '');

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
        <section className="border border-sidebar-border bg-background">
            <Collapsible open={open} onOpenChange={setOpen}>
                <h2 className="flex items-center gap-3 px-4 py-3 sm:px-5">
                    <span className="min-w-0 flex-1">{header}</span>
                    {badge}
                    <CollapsibleTrigger
                        aria-label={`${open ? 'Collapse' : 'Expand'} ${name}`}
                        className="flex size-8 shrink-0 cursor-pointer items-center justify-center text-secondary-foreground transition-colors hover:bg-secondary focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
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
        className="shrink-0 rounded-none px-2 py-0.5 text-[0.65rem] font-bold"
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

/** One way to reach the client, with the icon naming what it is. */
const ContactRow = ({
    icon: Icon,
    href,
    children,
}: {
    icon: typeof Mail;
    href?: string;
    children: string;
}) => (
    <li className="flex items-start gap-2.5 text-sm">
        <Icon
            className="mt-0.5 size-4 shrink-0 text-secondary-foreground"
            aria-hidden
        />
        <span className="min-w-0">
            {href ? (
                <a
                    href={href}
                    className="font-bold break-words underline-offset-4 hover:underline"
                >
                    {children}
                </a>
            ) : (
                <span className="break-words text-secondary-foreground">
                    {children}
                </span>
            )}
        </span>
    </li>
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
            <span className="flex size-10 shrink-0 items-center justify-center border border-sidebar-border">
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
 * what it is made of, how it is categorized, and then where and when. A project
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
                            {project.stones.map((stone) => (
                                <div key={stone.id} className="min-w-0">
                                    <img
                                        src={stone.thumbnail}
                                        alt=""
                                        loading="lazy"
                                        className="aspect-square w-full object-cover"
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
                                            className="size-12 shrink-0 object-cover"
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

                {project.categories.length > 0 ? (
                    <section>
                        <SectionLabel count={project.categories.length}>
                            Categories
                        </SectionLabel>

                        {/*
                         * Name only: categories are a pick, and the row is a
                         * record of what the client chose rather than a picture
                         * they still need to decide between.
                         */}
                        <ul className="mt-3 divide-y divide-dashed divide-sidebar-border">
                            {project.categories.map((category) => (
                                <li
                                    key={category}
                                    className="flex items-center gap-4 py-4 first:pt-0 last:pb-0"
                                >
                                    <p className="min-w-0 flex-1 text-sm font-bold break-words">
                                        {category}
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

const InquiryDetailsSkeleton = () => (
    <div className="mx-auto w-full max-w-6xl">
        <Skeleton className="mb-6 h-11 w-full sm:w-56" />

        <div className="mb-8 space-y-3 border-b border-sidebar-border pb-6 sm:flex sm:items-start sm:justify-between">
            <div className="space-y-3">
                <Skeleton className="h-8 w-64" />
                <Skeleton className="h-4 w-80 max-w-full" />
                <Skeleton className="h-3 w-64" />
            </div>
            <Skeleton className="mt-4 h-11 w-full sm:mt-0 sm:w-48" />
        </div>

        <div className="grid gap-x-6 gap-y-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            {/*
             * Two project cards rather than the mix of one tall card and two short
             * ones the old shape had, because a project is now one card however
             * much is in it.
             */}
            <div className="space-y-6 lg:order-1">
                {[0, 1].map((index) => (
                    <div
                        key={index}
                        className="border border-sidebar-border bg-background"
                    >
                        <div className="flex items-center gap-3 px-4 py-3 sm:px-5">
                            <div className="min-w-0 flex-1 space-y-1.5">
                                <Skeleton className="h-3 w-16" />
                                <Skeleton className="h-4 w-2/3" />
                            </div>
                            <Skeleton className="size-8" />
                        </div>

                        <div className="space-y-7 border-t border-sidebar-border p-4 sm:p-5">
                            <div className="space-y-3">
                                <Skeleton className="h-3 w-16" />
                                <Skeleton className="h-12 w-full" />
                                <Skeleton className="h-12 w-full" />
                            </div>
                            <div className="space-y-3">
                                <Skeleton className="h-3 w-16" />
                                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                                    {[0, 1, 2].map((tile) => (
                                        <Skeleton
                                            key={tile}
                                            className="aspect-square w-full rounded-none"
                                        />
                                    ))}
                                </div>
                            </div>
                            <div className="space-y-3">
                                <Skeleton className="h-3 w-24" />
                                <Skeleton className="h-10 w-full" />
                                <Skeleton className="h-10 w-full" />
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            <div className="space-y-6 lg:order-2">
                <Skeleton className="h-48 w-full" />
                <Skeleton className="h-64 w-full" />
                <Skeleton className="h-64 w-full" />
            </div>
        </div>
    </div>
);

const InquiryDetails = ({ inquiryId }: InquiryDetailsProps) => {
    const [isLoading, setIsLoading] = useState(true);
    const { can } = usePermissions();
    const getInitials = useInitials();

    const inquiry = inquiries.find((entry) => entry.id === inquiryId);

    useEffect(() => {
        const timer = window.setTimeout(() => setIsLoading(false), 700);

        return () => window.clearTimeout(timer);
    }, [inquiryId]);

    /*
     * The page hangs off whichever list this reader works from, and the two are
     * guarded separately, so the way back follows the permission rather than the
     * URL that happened to get here. A staff user who reached the quote URL has
     * no quote list to go back to, and vice versa.
     */
    const backHref = can(permissions.quotationsView)
        ? quoteIndex.url()
        : inquiryIndex.url();

    if (isLoading) {
        return <InquiryDetailsSkeleton />;
    }

    if (!inquiry) {
        /*
         * Not a 404: the data is mock and lives in the front end, so an id that
         * matches nothing is a reader who mistyped or followed a stale link, not
         * a missing resource. There is nothing on the server to be honest about.
         */
        return (
            <div className="mx-auto w-full max-w-6xl py-16">
                <div className="mx-auto flex w-full max-w-md flex-col items-center gap-3 border border-sidebar-border bg-background px-6 py-16 text-center">
                    <h1 className="text-2xl font-bold tracking-[-0.04em] sm:text-3xl">
                        Inquiry not found
                    </h1>
                    <p className="max-w-sm text-sm text-secondary-foreground">
                        There is no inquiry with that reference.
                    </p>
                    <Button asChild className="mt-1">
                        <Link href={backHref}>
                            <ArrowLeft aria-hidden />
                            Back to inquiries
                        </Link>
                    </Button>
                </div>
            </div>
        );
    }

    const client: Client | undefined = clients.find(
        (entry) => entry.id === inquiry.clientId,
    );
    /*
     * An inquiry can sit with more than one person, so this is a list and not a
     * single lookup. Ids that are no longer on the books drop off the same way
     * a dropped stone does.
     */
    const handlers = inquiry.handledByIds
        .map((id) => people.find((entry) => entry.id === id))
        .filter((person): person is Person => person !== undefined);

    const inquiryProjects: ResolvedProject[] = inquiry.projects.map(
        (project) => ({
            id: project.id,
            name: project.name,
            services: project.services.map((name) => ({
                name,
                image: serviceOptions.find((option) => option.name === name)
                    ?.image,
            })),
            stones: project.stones
                .map((selection) => {
                    const stone = stones.find(
                        (entry) => entry.id === selection.stoneId,
                    );

                    return stone === undefined
                        ? undefined
                        : { ...stone, ...selection };
                })
                .filter(
                    (stone): stone is Stone & InquiryStoneSelection =>
                        stone !== undefined,
                ),
            categories: (project.categories ?? [])
                .map(
                    (category) =>
                        PROJECT_CATEGORIES.find(
                            (entry) => entry.value === category,
                        )?.label,
                )
                .filter((label): label is string => label !== undefined),
            requirements: project.requirements,
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
            project.stones.map((selection) => selection.stoneId),
        ),
    ).size;

    return (
        <div className="mx-auto w-full max-w-6xl">
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

                            <div>
                                <h3 className="text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
                                    Handled by
                                </h3>
                                {handlers.length > 0 ? (
                                    <ul className="mt-2.5 space-y-3">
                                        {handlers.map((handler) => (
                                            <li
                                                key={handler.id}
                                                className="flex items-center gap-3"
                                            >
                                                <Avatar className="size-9 rounded-none">
                                                    <AvatarFallback className="rounded-none bg-primary text-xs font-bold text-primary-foreground">
                                                        {getInitials(
                                                            handler.name,
                                                        )}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div className="min-w-0">
                                                    <p className="text-sm font-bold break-words">
                                                        {handler.name}
                                                    </p>
                                                    <p className="text-xs break-words text-secondary-foreground">
                                                        {handler.role}
                                                    </p>
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p className="mt-2 text-sm text-secondary-foreground">
                                        Not assigned yet
                                    </p>
                                )}
                            </div>
                        </div>
                    </DetailsCard>

                    <DetailsCard title="Client" name="client">
                        {client ? (
                            <>
                                <div className="flex items-center gap-3">
                                    <Avatar className="size-11 rounded-none">
                                        <AvatarFallback className="rounded-none bg-primary text-sm font-bold text-primary-foreground">
                                            {getInitials(client.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <p className="min-w-0 text-sm font-bold break-words">
                                        {client.name}
                                    </p>
                                </div>

                                <ul className="mt-5 space-y-3 border-t border-sidebar-border pt-4">
                                    <ContactRow
                                        icon={Mail}
                                        href={`mailto:${client.email}`}
                                    >
                                        {client.email}
                                    </ContactRow>
                                    <ContactRow
                                        icon={Phone}
                                        href={`tel:${dialNumber(client.phone)}`}
                                    >
                                        {client.phone}
                                    </ContactRow>
                                </ul>
                            </>
                        ) : (
                            <p className="text-sm text-secondary-foreground">
                                The client for this inquiry is no longer on
                                file.
                            </p>
                        )}
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
                        <div className="border border-sidebar-border bg-background p-4 sm:p-5">
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
