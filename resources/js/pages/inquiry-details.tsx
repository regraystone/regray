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
import {
    clients,
    inquiries,
    inquiryTitle,
    people,
    serviceOptions,
    stones,
    type Client,
    type FeedItem,
    type InquiryFile,
    type InquiryStoneSelection,
    type Person,
} from '@/data/constant';
import { useInitials } from '@/hooks/use-initials';
import { usePermissions } from '@/hooks/use-permissions';
import { index as inquiryIndex } from '@/routes/inquiry';
import { index as messageIndex } from '@/routes/message';
import { index as quoteIndex } from '@/routes/quote';

type InquiryDetailsProps = {
    inquiryId: string;
};

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

/**
 * `neededBy` is a bare `YYYY-MM-DD`, which is read as UTC midnight and so can
 * land on the day before the one that was picked. The `T00:00` pins the reading
 * to the reader's own timezone, which is how the stepper's own review step reads
 * the same value.
 */
const neededByFormatter = new Intl.DateTimeFormat(undefined, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
});

const formatNeededBy = (value: string | undefined): string | undefined =>
    value === undefined
        ? undefined
        : neededByFormatter.format(new Date(`${value}T00:00`));

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
 * One card of the page.
 *
 * The header row carries the section's title, whatever it has to report about
 * itself — a count, a status — and the chevron that folds the body away, because
 * a reader who has read the notes does not want the notes taking up the screen
 * while they read the timeline. `open` is local rather than lifted: there is no
 * reason for one card to close another, and a reader who folds the stones back
 * after the services should find them folded on the way back too.
 */
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
    /** What the section is, for the chevron, which carries no text of its own. */
    name: string;
    children: ReactNode;
}) => {
    const [open, setOpen] = useState(true);

    return (
        <section className="border border-sidebar-border bg-background">
            <Collapsible open={open} onOpenChange={setOpen}>
                <h2 className="flex items-center gap-3 px-4 py-3 sm:px-5">
                    <span className="min-w-0 flex-1 truncate text-sm font-bold tracking-[0.08em] uppercase">
                        {title}
                    </span>
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
 * The name a project was given while it was being asked for.
 *
 * The one thing on this page that connects a project's stones to the services
 * wanted for them, so it heads a block in both of those cards. The client's own
 * words come first, with its position behind it, because on a request holding
 * several projects the number is the only thing that tells two blocks apart when
 * the names are similar — and the name is what the team will be quoted under.
 */
const ProjectLabel = ({ index, name }: { index: number; name: string }) => (
    <h3 className="text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
        Project {index + 1} · {name}
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
 * One file the inquiry arrived with.
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

const InquiryDetailsSkeleton = () => (
    <div className="mx-auto w-full max-w-6xl">
        <Skeleton className="mb-6 h-11 w-full sm:w-56" />

        <div className="mb-8 space-y-3 border-b border-sidebar-border pb-6 sm:flex sm:items-start sm:justify-between">
            <div className="space-y-3">
                <Skeleton className="h-8 w-64" />
                <Skeleton className="h-4 w-80 max-w-full" />
            </div>
            <Skeleton className="mt-4 h-11 w-full sm:mt-0 sm:w-48" />
        </div>

        <div className="grid gap-x-6 gap-y-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div className="space-y-6 lg:order-1">
                <Skeleton className="h-56 w-full" />
                <Skeleton className="h-72 w-full" />
                <Skeleton className="h-80 w-full" />
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
    const backHref = can('view quotation')
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

    const requirements = inquiry.requirements;
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
    /*
     * Resolved per project rather than as one list over the inquiry, and through
     * a map so that a stone dropped from `stones` since the inquiry was raised
     * falls off the page instead of crashing it. A project's stones, their
     * counts and how each is worked are that project's own answer: the same
     * stone in two projects is two lots of it, cut two ways.
     */
    const inquiryProjects = inquiry.projects
        .map((project) => ({
            id: project.id,
            name: project.name,
            services: project.services,
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
                    (stone): stone is FeedItem & InquiryStoneSelection =>
                        stone !== undefined,
                ),
        }))
        .filter((project) => project.stones.length > 0);
    /*
     * Pieces rather than types: the count beside a stone name on the tiles is
     * how many were asked for, so the band underneath has to add up to the same
     * figure the tiles are showing. The types are counted across the whole
     * inquiry and without duplicates, because the same material in two projects
     * is one material asked about twice.
     */
    const stoneCount = inquiryProjects.reduce(
        (total, project) =>
            total +
            project.stones.reduce(
                (pieces, stone) => pieces + stone.quantity,
                0,
            ),
        0,
    );
    const stoneTypes = new Set(
        inquiry.projects.flatMap((project) =>
            project.stones.map((selection) => selection.stoneId),
        ),
    ).size;
    const neededBy = formatNeededBy(requirements.neededBy);
    const attachments = requirements.attachments;

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
                    <p className="text-sm break-words text-secondary-foreground">
                        Submitted{' '}
                        {dateTimeFormatter.format(new Date(inquiry.createdAt))}{' '}
                        · {inquiryTitle(inquiry)}
                    </p>
                </div>

                {/*
                 * Read-only page: the only thing it offers is a way to ask a
                 * question, and a reader with no message page has nothing to ask
                 * it on.
                 */}
                {can('view message') ? (
                    <Button
                        asChild
                        variant="outline"
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
             * the long main column: where this has got to, who it is for and how
             * it got there are the things worth knowing before the detail. The
             * order classes put the main column back on the left once there is
             * room for both.
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
                    <DetailsCard
                        title="Stones"
                        name="stones"
                        badge={<CountBadge count={stoneTypes} />}
                        footer={`${stoneCount} ${
                            stoneCount === 1 ? 'piece' : 'pieces'
                        } of ${stoneTypes} ${
                            stoneTypes === 1 ? 'stone' : 'stones'
                        }`}
                    >
                        {inquiryProjects.length > 0 ? (
                            /* One block per project, because the edge and the
                               finish are a stone's answer inside its own
                               project: one flat list would have to say one thing
                               about a material wanted two ways.

                               Within a block, the stone is the answer to a
                               picture, so the tiles go across rather than down.
                               Fewer columns than the stepper's, twice over — the
                               card is narrower than the stepper's sheet, and the
                               longest name in the catalogue needs room beside its
                               count rather than under it. */
                            <div className="space-y-8">
                                {inquiryProjects.map((project, index) => (
                                    <div key={project.id}>
                                        {inquiryProjects.length > 1 ? (
                                            <ProjectLabel
                                                index={index}
                                                name={project.name}
                                            />
                                        ) : null}

                                        <div className="mt-4 grid grid-cols-2 gap-x-4 gap-y-5 sm:grid-cols-3">
                                            {project.stones.map((stone) => (
                                                <div
                                                    key={stone.id}
                                                    className="min-w-0"
                                                >
                                                    <img
                                                        src={stone.thumbnail}
                                                        alt=""
                                                        loading="lazy"
                                                        className="aspect-square w-full object-cover"
                                                    />
                                                    {/*
                                                     * The count sits opposite the
                                                     * name rather than under it:
                                                     * how many of a stone is part
                                                     * of which stone, so the two
                                                     * read as one line. It holds
                                                     * its width so a long name
                                                     * truncates instead of pushing
                                                     * the count off the tile.
                                                     */}
                                                    <div className="mt-2 flex items-baseline justify-between gap-x-2">
                                                        <p className="truncate text-sm font-bold">
                                                            {stone.name}
                                                        </p>
                                                        <p className="shrink-0 text-xs font-bold text-secondary-foreground">
                                                            x{stone.quantity}
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
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-secondary-foreground">
                                No stones were chosen for this inquiry.
                            </p>
                        )}
                    </DetailsCard>

                    <DetailsCard
                        title="Services"
                        name="services"
                        badge={<CountBadge count={inquiry.services.length} />}
                    >
                        {inquiry.services.length > 0 ? (
                            /*
                             * Grouped the same way as the stones above, and for
                             * the same reason: a service is wanted for a
                             * particular set of stones, so a flat list would say
                             * a service belongs to the whole inquiry when it
                             * belongs to one project.
                             *
                             * Name and picture only. The bullets are what the
                             * service card in the stepper is for, where a client
                             * is still choosing; here they have already chosen
                             * and the row is a record of what they picked.
                             */
                            <div className="space-y-6">
                                {inquiryProjects.map((project, index) => (
                                    <div key={project.id}>
                                        {inquiryProjects.length > 1 ? (
                                            <ProjectLabel
                                                index={index}
                                                name={project.name}
                                            />
                                        ) : null}

                                        <ul className="mt-4 divide-y divide-dashed divide-sidebar-border">
                                            {project.services.map((name) => {
                                                const service =
                                                    serviceOptions.find(
                                                        (option) =>
                                                            option.name ===
                                                            name,
                                                    );

                                                return (
                                                    <li
                                                        key={name}
                                                        className="flex items-center gap-4 py-4 first:pt-0 last:pb-0"
                                                    >
                                                        <p className="min-w-0 flex-1 text-sm font-bold break-words">
                                                            {name}
                                                        </p>
                                                        {service ? (
                                                            <img
                                                                src={
                                                                    service.image
                                                                }
                                                                alt=""
                                                                loading="lazy"
                                                                className="size-12 shrink-0 object-cover"
                                                            />
                                                        ) : null}
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-secondary-foreground">
                                No services were asked for on this inquiry.
                            </p>
                        )}
                    </DetailsCard>

                    <DetailsCard
                        title="Inquiry details"
                        name="inquiry details"
                        footer={
                            attachments.length > 0
                                ? 'Files are not stored in this preview.'
                                : undefined
                        }
                    >
                        <dl>
                            <SummaryRow
                                label="Location"
                                value={requirements.location}
                            />
                            <SummaryRow
                                label="Landmark"
                                value={requirements.landmark}
                            />
                            <SummaryRow
                                label="Approximate area"
                                value={
                                    requirements.areaSqm === undefined
                                        ? undefined
                                        : `${requirements.areaSqm} sqm`
                                }
                            />
                            <SummaryRow label="Needed by" value={neededBy} />
                        </dl>

                        <div className="mt-6 border-t border-sidebar-border pt-4">
                            <h3 className="text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
                                Notes
                            </h3>
                            {/* Kept verbatim: the client wrote line breaks on purpose. */}
                            <p className="mt-2 text-sm break-words whitespace-pre-wrap">
                                {requirements.notes ? (
                                    requirements.notes
                                ) : (
                                    <NotProvided />
                                )}
                            </p>
                        </div>

                        <div className="mt-6 border-t border-sidebar-border pt-4">
                            <h3 className="text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
                                Attachments
                            </h3>
                            {attachments.length > 0 ? (
                                <ul className="mt-3 divide-y divide-dashed divide-sidebar-border">
                                    {attachments.map((file) => (
                                        <FileRow key={file.id} file={file} />
                                    ))}
                                </ul>
                            ) : (
                                <p className="mt-2 text-sm text-secondary-foreground">
                                    No files were attached to this inquiry.
                                </p>
                            )}
                        </div>
                    </DetailsCard>
                </div>
            </div>
        </div>
    );
};

export default InquiryDetails;
