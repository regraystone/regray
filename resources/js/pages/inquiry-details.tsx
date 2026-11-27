import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, MessageCircle } from 'lucide-react';
import { InquiryFileRow } from '@/components/inquiry-file-row';
import PersonAvatar from '@/components/person-avatar';
import { formatDate } from '@/lib/format-date';
import { formatPeso } from '@/lib/format-money';
import { StatusBadge } from '@/components/inquiry-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import WalkInInquiryCard from '@/components/walk-in-inquiry-card';
import type { InquiryRequirementsRecord } from '@/data/inquiries';
import { usePermissions } from '@/hooks/use-permissions';
import { permissions } from '@/layouts/workspace-layout';
import {
    activityLabel,
    documentGroupLabel,
    DOCUMENT_TYPE_LABELS,
    INQUIRY_SUBMITTED_LABEL,
    startedStatuses,
} from '@/lib/inquiry-statuses';
import { buildCardActions } from '@/lib/quote-actions';
import { index as inquiryIndex } from '@/routes/inquiry';
import { index as inboxIndex } from '@/routes/inbox';
import { index as quoteIndex } from '@/routes/quote';
import type { QuoteInquiryDetails, StoredInquiryFile } from '@/types/quote';

type InquiryDetailsProps = {
    inquiry: QuoteInquiryDetails;
};

/**
 * The project the inquiry is, as the page draws it. One inquiry is one project.
 *
 * The server sends it already resolved — stones with their names and pictures,
 * the service with its name — so the page looks nothing up.
 */
type ResolvedStone = {
    id: string;
    name: string;
    thumbnail: string;
    /** Null where the service does not ask how many pieces. */
    quantity: number | null;
    /** "160 x 320 cm"; null where the service does not ask it. */
    slabSize: string | null;
    /** "20 mm"; null where the service does not ask it. */
    thickness: string | null;
    /** Whether the size and thickness are one of the stone's slabs; null where no thickness is asked. */
    isStandardSlab: boolean | null;
    edge: string | null;
    finish: string | null;
    /** The stone-scope answers its service asks, labelled, in order. */
    details: Array<{ label: string; value: string }>;
};

type ResolvedProject = {
    name: string;
    /** The client asked Regray to recommend a stone instead of choosing one. */
    stoneAdviceRequested: boolean;
    brands: Array<{ id: string; name: string }>;
    brandNoPreference: boolean;
    serviceType: string | null;
    service: string | null;
    /** What a service with an installation is for (Flooring…), null for none. */
    application: string | null;
    requiresStones: boolean;
    stones: ResolvedStone[];
    /** The project-scope answers, labelled and worded by the server; a picture choice with its picture. */
    answers: Array<{ label: string; value: string; image?: string | null }>;
    /** Application names, on inquiries sent while the client was asked for them. */
    requirements: Omit<InquiryRequirementsRecord, 'attachments'> & {
        attachments: StoredInquiryFile[];
    };
};

/** Null when the service asked no piece count, which only the slab services ask. */
const countPieces = (
    selections: Array<{ quantity: number | null }>,
): number | null =>
    selections.some(({ quantity }) => quantity !== null)
        ? selections.reduce(
              (total, selection) => total + (selection.quantity ?? 0),
              0,
          )
        : null;

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
 */
const formatNeededBy = ({
    neededBy,
}: InquiryRequirementsRecord): string | undefined =>
    neededBy === undefined || neededBy === ''
        ? undefined
        : neededByFormatter.format(new Date(`${neededBy}T00:00`));

/** One answer, or the fact that there was none. */
const NotProvided = () => (
    <span className="font-normal text-secondary-foreground">Not provided</span>
);

/**
 * A labelled value, in the shape the order summary uses: the question on the
 * left in muted text, the answer on the right. Read as a description list so a
 * screen reader pairs the two, which the visual alignment alone would not.
 */
const SummaryRow = ({
    label,
    value,
    image = null,
}: {
    label: string;
    value?: string;
    /** A picture choice's picture (a table shape or base), shown before its name. */
    image?: string | null;
}) => (
    <div
        className={`flex justify-between gap-4 border-b border-dashed border-sidebar-border py-3 last:border-b-0 sm:gap-8 ${image ? 'items-center' : 'items-baseline'}`}
    >
        <dt className="text-sm text-secondary-foreground">{label}</dt>
        <dd className="flex min-w-0 items-center justify-end gap-3 text-right text-sm font-bold break-words">
            {image ? (
                <img
                    src={image}
                    alt=""
                    loading="lazy"
                    className="size-12 shrink-0 bg-muted object-cover"
                />
            ) : null}
            {value === undefined || value === '' ? <NotProvided /> : value}
        </dd>
    </div>
);

type HistoryEntry = QuoteInquiryDetails['history'][number];
type SharedDocument = QuoteInquiryDetails['documents'][number];
type PaymentRecord = QuoteInquiryDetails['payments'][number];
type AssignedStaffMember = QuoteInquiryDetails['assignedStaff'][number];

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
 * The client account the inquiry came from, for staff: their profile photo
 * (or initials), their name and the company they gave on their profile.
 */
const ClientAccountCard = ({
    client,
}: {
    client: NonNullable<QuoteInquiryDetails['clientAccount']>;
}) => (
    <section
        aria-labelledby="inquiry-client-title"
        className="overflow-hidden rounded-md border border-sidebar-border bg-background"
        data-testid="inquiry-client-account"
    >
        <h2
            id="inquiry-client-title"
            className="px-4 py-3 text-sm font-bold tracking-[0.08em] uppercase sm:px-5"
        >
            Client
        </h2>
        <div className="flex items-center gap-3 border-t border-sidebar-border px-4 py-4 sm:px-5">
            <PersonAvatar
                name={client.name}
                photo={client.avatar}
                className="size-12"
            />
            <div className="min-w-0">
                <p className="text-sm font-bold break-words">{client.name}</p>
                {client.company ? (
                    <p className="text-xs break-words text-secondary-foreground">
                        {client.company}
                    </p>
                ) : null}
            </div>
        </div>
    </section>
);

/**
 * Who on the team is handling the inquiry, and as what, in the order the
 * server sends them. Only the assignment as it stood when the page loaded: a
 * reassignment shows on the next visit. Nobody yet is normal for a new
 * inquiry, not something gone wrong.
 */
const AssignedStaffCard = ({ staff }: { staff: AssignedStaffMember[] }) => (
    <section
        aria-labelledby="inquiry-assigned-staff-title"
        className="overflow-hidden rounded-md border border-sidebar-border bg-background"
        data-testid="inquiry-assigned-staff"
    >
        <h2 className="flex items-center gap-3 px-4 py-3 sm:px-5">
            <span
                id="inquiry-assigned-staff-title"
                className="block min-w-0 flex-1 truncate text-sm font-bold tracking-[0.08em] uppercase"
            >
                Assigned Staff
            </span>
            <CountBadge count={staff.length} />
        </h2>

        <div className="border-t border-sidebar-border px-4 sm:px-5">
            {staff.length > 0 ? (
                <ul className="divide-y divide-dashed divide-sidebar-border">
                    {staff.map((member, index) => (
                        // Names are not unique; the server's order is the identity.
                        <li
                            key={`${index}-${member.name}`}
                            className="flex items-center gap-3 py-3"
                        >
                            <PersonAvatar name={member.name} />
                            <div className="min-w-0">
                                <p className="text-sm font-bold break-words">
                                    {member.name}
                                </p>
                                {member.roleLabel !== null ? (
                                    <p className="text-xs text-secondary-foreground">
                                        {member.roleLabel}
                                    </p>
                                ) : null}
                            </div>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="py-4 text-sm text-secondary-foreground sm:py-5">
                    No staff have been assigned to this inquiry yet.
                </p>
            )}
        </div>
    </section>
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
 * latest), and each payment receipt, newest first. Staff also see the job
 * order, which never reaches the client. Each can be previewed or downloaded.
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
                            <ul className="mt-2 space-y-4">
                                {group.files.map((file) => (
                                    <InquiryFileRow
                                        key={file.id}
                                        file={file}
                                        heading={
                                            DOCUMENT_TYPE_LABELS[file.type]
                                        }
                                    />
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

    const footer = [
        [project.service, project.application].filter(Boolean).join(' · ') ||
            null,
        stoneTypes === 0
            ? null
            : `${pieces === null ? '' : `${pieces} ${pieces === 1 ? 'piece' : 'pieces'} of `}${stoneTypes} ${
                  stoneTypes === 1 ? 'stone' : 'stones'
              }`,
    ]
        .filter(Boolean)
        .join(' · ');

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
                {project.requiresStones ? (
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
                                            {stone.quantity === null ? null : (
                                                <p className="shrink-0 text-xs font-bold text-secondary-foreground">
                                                    {stone.quantity} pcs
                                                </p>
                                            )}
                                        </div>
                                        {/* Only what the service asks of a stone. */}
                                        {stone.details.map((detail) => (
                                            <p
                                                key={detail.label}
                                                className="mt-1 text-xs break-words text-secondary-foreground"
                                            >
                                                {detail.label}: {detail.value}
                                            </p>
                                        ))}
                                        {/*
                                         * Whether the size and thickness are one of
                                         * the stone's own slabs, or cut to order.
                                         */}
                                        {stone.isStandardSlab ===
                                        null ? null : (
                                            <p
                                                className="mt-1.5 inline-block rounded-sm border border-sidebar-border px-1.5 py-0.5 text-[11px] font-bold text-secondary-foreground"
                                                data-testid="slab-kind"
                                            >
                                                {stone.isStandardSlab
                                                    ? 'Standard slab'
                                                    : 'Custom size/thickness'}
                                            </p>
                                        )}
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
                            <p
                                className="mt-2 text-sm text-secondary-foreground"
                                data-testid="project-no-stones"
                            >
                                {project.stoneAdviceRequested
                                    ? 'Stone to be recommended by Regray.'
                                    : 'No stones were chosen for this project.'}
                            </p>
                        )}
                    </section>
                ) : null}

                <section>
                    <SectionLabel>What you need</SectionLabel>

                    <dl>
                        <SummaryRow
                            label="Brands"
                            value={
                                project.brandNoPreference
                                    ? 'No preference'
                                    : project.brands
                                          .map(({ name }) => name)
                                          .join(', ') || undefined
                            }
                        />
                        <SummaryRow
                            label="Service type"
                            value={project.serviceType ?? undefined}
                        />
                        <SummaryRow
                            label="Service"
                            value={project.service ?? undefined}
                        />
                        {project.application === null ? null : (
                            <SummaryRow
                                label="Application"
                                value={project.application}
                            />
                        )}
                    </dl>
                </section>

                {project.answers.length > 0 ? (
                    <section data-testid="project-answers">
                        <SectionLabel>Details</SectionLabel>

                        <dl>
                            {project.answers.map((answer) => (
                                <SummaryRow
                                    key={answer.label}
                                    label={answer.label}
                                    value={answer.value}
                                    image={answer.image}
                                />
                            ))}
                        </dl>
                    </section>
                ) : null}

                <section>
                    <SectionLabel>Requirements</SectionLabel>

                    <dl>
                        <SummaryRow
                            label="How you'll get it"
                            value={project.requirements.fulfillment}
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
                                        <InquiryFileRow
                                            key={file.id}
                                            file={file}
                                            className="py-3 first:pt-0 last:pb-0"
                                        />
                                    ))}
                                </ul>
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
): ResolvedProject['requirements'] => ({
    deliveryMethod: requirements.deliveryMethod ?? undefined,
    fulfillment: requirements.fulfillment,
    neededBy: requirements.neededBy ?? undefined,
    notes: requirements.notes ?? undefined,
    attachments: requirements.attachments,
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
        stoneAdviceRequested: inquiry.project.stoneAdviceRequested,
        brands: inquiry.project.brands,
        brandNoPreference: inquiry.project.brandNoPreference,
        serviceType: inquiry.project.serviceType,
        service: inquiry.project.service,
        application: inquiry.project.application ?? null,
        requiresStones: inquiry.project.requiresStones,
        stones: inquiry.project.stones,
        answers: inquiry.project.answers,
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
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                    {buildCardActions(inquiry.actions).map((button) =>
                        button.key === 'invoice' ? (
                            <Button
                                asChild
                                key={button.key}
                                variant={button.variant}
                                className="shrink-0 self-start"
                            >
                                <Link href="#">{button.label}</Link>
                            </Button>
                        ) : null,
                    )}

                    {inquiry.conversation ? (
                        <Button
                            asChild
                            variant="default"
                            className="shrink-0 self-start"
                        >
                            <Link
                                href={inboxIndex.url({
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
            </div>

            {/*
             * The aside (the assigned staff, the activity log and the documents) is written first so that below `lg` it reads
             * before the project: where the inquiry has got to is worth knowing
             * before the detail. The order classes put the project back on the
             * left once there is room for both.
             */}
            <div className="grid gap-x-6 gap-y-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <aside className="space-y-6 lg:order-2">
                    {inquiry.walkIn ? (
                        <WalkInInquiryCard
                            reference={inquiry.reference}
                            walkIn={inquiry.walkIn}
                        />
                    ) : null}
                    {inquiry.clientAccount ? (
                        <ClientAccountCard client={inquiry.clientAccount} />
                    ) : null}
                    <AssignedStaffCard staff={inquiry.assignedStaff} />
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
