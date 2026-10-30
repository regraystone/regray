import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    FileText,
    Image as ImageIcon,
    MessageCircle,
} from 'lucide-react';
import { formatSize } from '@/components/file-upload';
import { formatDate } from '@/lib/format-date';
import { formatPeso } from '@/lib/format-money';
import { StatusBadge } from '@/components/inquiry-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type {
    InquiryFile,
    InquiryRequirementsRecord,
    InquiryStoneSelection,
} from '@/data/inquiries';
import { usePermissions } from '@/hooks/use-permissions';
import { permissions } from '@/layouts/workspace-layout';
import {
    activityLabel,
    documentGroupLabel,
    DOCUMENT_TYPE_LABELS,
    INQUIRY_SUBMITTED_LABEL,
    startedStatuses,
} from '@/lib/inquiry-statuses';
import { index as inquiryIndex } from '@/routes/inquiry';
import { index as messageIndex } from '@/routes/message';
import { index as quoteIndex } from '@/routes/quote';
import type { QuoteInquiryDetails } from '@/types/quote';

type InquiryDetailsProps = {
    inquiry: QuoteInquiryDetails;
};

/**
 * The project the inquiry is, as the page draws it. One inquiry is one project.
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
 * When the inquiry was sent, or a status moved, read the way a reader would
 * say it aloud.
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

type HistoryEntry = QuoteInquiryDetails['history'][number];
type SharedDocument = QuoteInquiryDetails['documents'][number];
type PaymentRecord = QuoteInquiryDetails['payments'][number];

const PAYMENT_RECORD_LABELS: Record<PaymentRecord['kind'], string> = {
    contract: 'Contract total',
    payment: 'Payment',
    refund: 'Refund',
};

/**
 * One line of the aside: what happened on the left, when on the right. The
 * date never wraps under the words; a long label wraps on its own side.
 */
const DatedRow = ({
    label,
    at,
    isCurrent = false,
}: {
    label: string;
    at: string;
    isCurrent?: boolean;
}) => (
    <div className="flex min-w-0 flex-1 items-baseline justify-between gap-4">
        <p
            className={`min-w-0 text-sm font-bold break-words ${
                isCurrent ? 'text-foreground' : 'text-secondary-foreground'
            }`}
        >
            {label}
        </p>
        <time
            dateTime={at}
            className="shrink-0 text-right text-xs whitespace-nowrap text-secondary-foreground"
        >
            {dateTimeFormatter.format(new Date(at))}
        </time>
    </div>
);

/**
 * The client's activity log: what has happened to their inquiry, newest first,
 * ending on it being submitted, in the client's own words ("Quote approved",
 * "Partially paid"). Only sales, payment and logistics, and a cancel: the
 * server never sends anything about the lead, nor who made a change.
 */
const ActivityLogCard = ({
    history,
    createdAt,
}: {
    history: HistoryEntry[];
    createdAt: string;
}) => {
    const entries = [
        ...[...history].reverse().map((entry) => ({
            key: `${entry.track}-${entry.status}-${entry.at}`,
            label: activityLabel(entry),
            at: entry.at,
        })),
        { key: 'submitted', label: INQUIRY_SUBMITTED_LABEL, at: createdAt },
    ];

    return (
        <section
            aria-labelledby="inquiry-activity-log-title"
            className="overflow-hidden rounded-md border border-sidebar-border bg-background"
            data-testid="inquiry-activity-log"
        >
            <h2 className="flex items-center gap-3 px-4 py-3 sm:px-5">
                <span
                    id="inquiry-activity-log-title"
                    className="block min-w-0 flex-1 truncate text-sm font-bold tracking-[0.08em] uppercase"
                >
                    Activity Log
                </span>
                <CountBadge count={entries.length} />
            </h2>

            <ol className="space-y-5 border-t border-sidebar-border p-4 sm:p-5">
                {entries.map((entry, index) => {
                    const isCurrent = index === 0;

                    return (
                        <li key={entry.key} className="flex gap-4">
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
                                {index < entries.length - 1 ? (
                                    <span className="absolute top-4 bottom-[-1.25rem] w-px bg-sidebar-border" />
                                ) : null}
                            </span>
                            <DatedRow
                                label={entry.label}
                                at={entry.at}
                                isCurrent={isCurrent}
                            />
                        </li>
                    );
                })}
            </ol>
        </section>
    );
};

/**
 * The payment records, newest first like the activity log: each payment with
 * the balance owed before it, what it paid and the balance remaining, listed
 * one under another, down to the total contract amount billed when payment
 * started. Not shown before then.
 */
const PaymentRecordsCard = ({ payments }: { payments: PaymentRecord[] }) => (
    <section
        aria-labelledby="inquiry-payment-records-title"
        className="overflow-hidden rounded-md border border-sidebar-border bg-background"
        data-testid="inquiry-payment-records"
    >
        <h2 className="flex items-center gap-3 px-4 py-3 sm:px-5">
            <span
                id="inquiry-payment-records-title"
                className="block min-w-0 flex-1 truncate text-sm font-bold tracking-[0.08em] uppercase"
            >
                Payment Records
            </span>
            <CountBadge count={payments.length} />
        </h2>

        <ul className="divide-y divide-dashed divide-sidebar-border border-t border-sidebar-border px-4 sm:px-5">
            {[...payments].reverse().map((record, index) => (
                <li key={record.id} className="space-y-3 py-3">
                    <div className="flex items-baseline justify-between gap-4">
                        <p
                            className={`min-w-0 text-sm font-bold break-words ${
                                index === 0
                                    ? 'text-foreground'
                                    : 'text-secondary-foreground'
                            }`}
                        >
                            {PAYMENT_RECORD_LABELS[record.kind]}
                            {record.kind === 'contract'
                                ? ` ${formatPeso(record.amount)}`
                                : null}
                        </p>
                        <time
                            dateTime={record.at}
                            className="shrink-0 text-right text-xs whitespace-nowrap text-secondary-foreground"
                        >
                            {dateTimeFormatter.format(new Date(record.at))}
                        </time>
                    </div>
                    {record.kind === 'contract' ? null : (
                        <dl className="space-y-1">
                            {[
                                {
                                    label:
                                        record.kind === 'refund'
                                            ? 'Held before'
                                            : 'Current balance',
                                    value: record.currentBalance,
                                },
                                {
                                    label:
                                        record.kind === 'refund'
                                            ? 'Refunded'
                                            : 'Paid',
                                    value: record.amount,
                                },
                                {
                                    label:
                                        record.kind === 'refund'
                                            ? 'Still held'
                                            : 'Remaining balance',
                                    value: record.remainingBalance,
                                },
                            ].map((figure) => (
                                <div
                                    key={figure.label}
                                    className="flex items-baseline justify-between gap-4"
                                >
                                    <dt className="text-xs text-secondary-foreground">
                                        {figure.label}
                                    </dt>
                                    <dd className="text-right text-xs font-bold whitespace-nowrap">
                                        {figure.value === null
                                            ? '—'
                                            : formatPeso(figure.value)}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    )}
                </li>
            ))}
        </ul>
    </section>
);

/**
 * The documents the team has shared with the client: the quotation, with its
 * revision (sales and the admin see every revision, everyone else only the
 * latest), and each
 * payment receipt, newest first. The job order is internal
 * and never reaches the page. Name, size and date only: there is no download
 * endpoint yet, so nothing here pretends to open a file.
 */
const DocumentsCard = ({ documents }: { documents: SharedDocument[] }) => (
    <section
        aria-labelledby="inquiry-documents-title"
        className="overflow-hidden rounded-md border border-sidebar-border bg-background"
        data-testid="inquiry-documents"
    >
        <h2 className="flex items-center gap-3 px-4 py-3 sm:px-5">
            <span
                id="inquiry-documents-title"
                className="block min-w-0 flex-1 truncate text-sm font-bold tracking-[0.08em] uppercase"
            >
                Documents
            </span>
            <CountBadge
                count={documents.reduce(
                    (total, group) => total + group.files.length,
                    0,
                )}
            />
        </h2>

        <div className="border-t border-sidebar-border p-4 sm:p-5">
            {documents.length > 0 ? (
                <ul className="divide-y divide-dashed divide-sidebar-border">
                    {[...documents].reverse().map((group) => (
                        <li
                            key={group.id}
                            className="py-3 first:pt-0 last:pb-0"
                            data-testid="inquiry-document-group"
                        >
                            <p className="text-sm font-bold">
                                {documentGroupLabel(
                                    group.status,
                                    group.revision,
                                )}{' '}
                                · {formatDate(group.at)}
                            </p>
                            <ul className="mt-2 space-y-2">
                                {group.files.map((file) => (
                                    <li
                                        key={file.id}
                                        className="flex items-start gap-3"
                                    >
                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-md border border-sidebar-border">
                                            {file.mimeType ===
                                            'application/pdf' ? (
                                                <FileText
                                                    className="size-4"
                                                    aria-hidden
                                                />
                                            ) : (
                                                <ImageIcon
                                                    className="size-4"
                                                    aria-hidden
                                                />
                                            )}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm">
                                                {
                                                    DOCUMENT_TYPE_LABELS[
                                                        file.type
                                                    ]
                                                }
                                            </p>
                                            <p
                                                className="truncate text-xs text-secondary-foreground"
                                                title={file.name}
                                            >
                                                {file.name} ·{' '}
                                                {formatSize(file.sizeBytes)}
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="text-sm text-secondary-foreground">
                    Quotations, billing statements, receipts and invoices from
                    the team will appear here.
                </p>
            )}
        </div>
    </section>
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
 * The project, whole: services, stones and requirements in one section,
 * because that is the shape the reader built them in and the shape they answer
 * for. Its name is the heading. Always open: there is one project, and nothing
 * on the page competes with it for room.
 *
 * Within it the order is what the reader entered it in: what they want done,
 * what it is made of, what it is for, and then where and when. A project
 * that was named only by its services reads in that order too, which is the
 * whole of what it is.
 */
const ProjectSection = ({ project }: { project: ResolvedProject }) => {
    const pieces = countPieces(project.stones);
    const stoneTypes = new Set(project.stones.map((stone) => stone.id)).size;
    const files = project.requirements.attachments;
    const neededBy = formatNeededBy(project.requirements);
    const serviceCount = project.services.length;

    const footer =
        stoneTypes > 0
            ? `${serviceCount} ${
                  serviceCount === 1 ? 'service' : 'services'
              } · ${pieces} ${pieces === 1 ? 'piece' : 'pieces'} of ${stoneTypes} ${
                  stoneTypes === 1 ? 'stone' : 'stones'
              }`
            : `${serviceCount} ${serviceCount === 1 ? 'service' : 'services'}`;

    return (
        <section
            aria-labelledby="inquiry-project-name"
            className="overflow-hidden rounded-md border border-sidebar-border bg-background"
            data-testid="inquiry-project"
        >
            <h2 className="flex items-center gap-3 px-4 py-3 sm:px-5">
                <span
                    id="inquiry-project-name"
                    className="block min-w-0 flex-1 text-sm font-bold break-words"
                >
                    {project.name}
                </span>
                {stoneTypes > 0 ? <CountBadge count={stoneTypes} /> : null}
            </h2>

            <div className="space-y-7 border-t border-sidebar-border p-4 sm:p-5">
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

            <div className="border-t border-sidebar-border bg-secondary px-4 py-3 text-sm text-secondary-foreground">
                {footer}
            </div>
        </section>
    );
};

/** The server's nulls are the page's absent answers. */
const toRequirements = (
    requirements: QuoteInquiryDetails['project']['requirements'],
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

    const project: ResolvedProject = {
        name: inquiry.project.name,
        services: inquiry.project.services,
        stones: inquiry.project.stones,
        applications: inquiry.project.applications,
        requirements: toRequirements(inquiry.project.requirements),
    };

    return (
        <div className="w-full">
            <Button asChild variant="outline" className="mb-6 w-full sm:w-auto">
                <Link href={backHref}>
                    <ArrowLeft aria-hidden />
                    Back to inquiries
                </Link>
            </Button>

            <div className="mb-8 flex flex-col gap-4 border-b border-sidebar-border pb-6 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0 space-y-3">
                    {/*
                     * The reference, then at most two statuses beside it: the
                     * inquiry status, the one status shown everywhere (worked
                     * out from the role statuses), and accounting's payment
                     * status once its stage has started. The role statuses
                     * themselves are in the Activity Log.
                     */}
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-3">
                        <h1 className="text-2xl font-bold tracking-[-0.03em]">
                            {inquiry.reference}
                        </h1>

                        <div
                            className="flex flex-row flex-nowrap items-center gap-1.5 whitespace-nowrap"
                            data-testid="inquiry-statuses"
                        >
                            {startedStatuses([
                                { group: 'inquiry', status: inquiry.status },
                                {
                                    group: 'payment',
                                    status: inquiry.paymentStatus,
                                },
                            ]).map((entry) => (
                                <StatusBadge
                                    key={entry.group}
                                    group={entry.group}
                                    status={entry.status}
                                />
                            ))}
                        </div>
                    </div>
                    {/*
                     * When it was submitted, and beside it, once accounting has
                     * sent the billing statement, the total contract amount.
                     */}
                    <div className="flex flex-wrap items-baseline gap-x-8 gap-y-1 text-sm text-secondary-foreground">
                        <p>
                            Submitted{' '}
                            {dateTimeFormatter.format(
                                new Date(inquiry.createdAt),
                            )}
                        </p>
                        {inquiry.contractTotal !== null ? (
                            <p>
                                Total amount -{' '}
                                <span className="font-bold text-foreground">
                                    {formatPeso(inquiry.contractTotal)}
                                </span>
                            </p>
                        ) : null}
                    </div>
                </div>

                {/*
                 * Read-only page: the only thing it offers is a way to ask a
                 * question, and only where there is a group chat for this
                 * inquiry that the reader is in.
                 */}
                {inquiry.conversation ? (
                    <Button
                        asChild
                        variant="default"
                        className="shrink-0 self-start"
                    >
                        <Link
                            href={messageIndex.url({
                                query: {
                                    conversation: String(
                                        inquiry.conversation.id,
                                    ),
                                },
                            })}
                        >
                            <MessageCircle aria-hidden />
                            Message the team
                        </Link>
                    </Button>
                ) : null}
            </div>

            {/*
             * The aside (the activity log and the documents) is written first so that below `lg` it reads
             * before the project: where the inquiry has got to is worth knowing
             * before the detail. The order classes put the project back on the
             * left once there is room for both.
             */}
            <div className="grid gap-x-6 gap-y-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <aside className="space-y-6 lg:order-2">
                    <ActivityLogCard
                        history={inquiry.history}
                        createdAt={inquiry.createdAt}
                    />
                    {inquiry.payments.length > 0 ? (
                        <PaymentRecordsCard payments={inquiry.payments} />
                    ) : null}
                    <DocumentsCard documents={inquiry.documents} />
                </aside>

                <div className="min-w-0 lg:order-1">
                    <ProjectSection project={project} />
                </div>
            </div>
        </div>
    );
};

export default InquiryDetails;
