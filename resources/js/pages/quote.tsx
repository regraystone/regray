import { Link, router } from '@inertiajs/react';
import { Download, Plus, Search, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import EmptyState from '@/components/empty-state';
import { STATUS_LABELS, StatusBadge } from '@/components/inquiry-status-badge';
import PageHeading from '@/components/page-heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    inquiries,
    inquiryTitle,
    type Inquiry,
    type InquiryStatus,
} from '@/data/inquiries';
import { stones, type Stone } from '@/data/stones';
import { useInquireRoute } from '@/hooks/use-inquire-route';
import { show as quoteShow } from '@/routes/quote';

const PAGE_SIZE = 10;
const SEARCH_DEBOUNCE_MS = 200;
const LOAD_MORE_DELAY_MS = 400;
const LOAD_MORE_ROOT_MARGIN = '200px';

/** How many service tags a card shows before it tallies the rest. */
const COLLAPSED_SERVICE_COUNT = 2;

/*
 * How many stones a card names outright before it tallies the rest.
 *
 * The same three also appear as thumbnails above the names, so the two rows
 * describe one set of stones rather than two: the pictures show the first three,
 * the names spell out the first three, and the count beside each is the same
 * number of stones left out.
 */
const COLLAPSED_STONE_COUNT = 3;

/*
 * The service tag. Named once because the tally of hidden tags sits in the same
 * row and has to look like one of them — a differently sized or coloured thing
 * in the middle of a set of tags reads as a fourth service rather than as a
 * count of what the row is not showing.
 */
const SERVICE_TAG_CLASS =
    'border border-sidebar-border px-2 py-0.5 text-[0.65rem] font-bold tracking-[0.05em] text-secondary-foreground uppercase';

type StatusFilter = 'all' | InquiryStatus;
type SortOrder = 'newest' | 'oldest';

/*
 * Why an action is unavailable, per status.
 *
 * Written as two small tables rather than a chain of ternaries so the buttons
 * can be read side by side: what each status may still do, and what it may not.
 * A status with no entry renders no button at all, which is how `paid` and
 * `completed` end up with Get invoice instead of Update and Cancel.
 */
const UPDATE_BLOCKED_REASON: Partial<Record<InquiryStatus, string>> = {
    quoted: 'Locked once quoted. Message the team to request changes.',
    cancelled: 'This inquiry was cancelled.',
};

const CANCEL_BLOCKED_REASON: Partial<Record<InquiryStatus, string>> = {
    draft: 'Only pending or quoted inquiries can be cancelled.',
    cancelled: 'This inquiry is already cancelled.',
};

/*
 * The statuses a cancellation is allowed to land on.
 *
 * Named once because the button that offers the action and the handler that
 * applies it have to agree. If they ever drifted apart the page would offer a
 * cancellation the handler then refuses, or quietly apply one it never offered.
 */
const CANCELLABLE_STATUSES: InquiryStatus[] = ['pending', 'quoted'];

const STATUS_TABS: Array<{ value: StatusFilter; label: string }> = [
    { value: 'all', label: 'All' },
    { value: 'pending', label: STATUS_LABELS.pending },
    { value: 'quoted', label: STATUS_LABELS.quoted },
    { value: 'paid', label: STATUS_LABELS.paid },
    { value: 'completed', label: STATUS_LABELS.completed },
    { value: 'cancelled', label: STATUS_LABELS.cancelled },
    { value: 'draft', label: STATUS_LABELS.draft },
];

const dateFormatter = new Intl.DateTimeFormat(undefined, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
});

const readQueryState = (): { search: string; status: StatusFilter } => {
    const params = new URLSearchParams(window.location.search);
    const status = params.get('status');

    return {
        search: params.get('q') ?? '',
        status: STATUS_TABS.some((tab) => tab.value === status)
            ? (status as StatusFilter)
            : 'all',
    };
};

const QuoteSkeleton = () => (
    <div className="mx-auto w-full max-w-6xl">
        <div className="mb-8 flex flex-col gap-3 border-b border-sidebar-border pb-4 sm:mb-4 sm:flex-row sm:items-end sm:justify-between">
            <div className="space-y-2">
                <Skeleton className="h-8 w-40" />
                <Skeleton className="h-4 w-56" />
            </div>
            <Skeleton className="h-11 w-full sm:w-32" />
        </div>

        <div className="mb-6 flex flex-col gap-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <Skeleton className="h-9 w-full sm:max-w-xs" />
                <Skeleton className="h-9 w-full sm:w-44" />
            </div>
            <div className="hidden gap-5 sm:flex">
                {Array.from({ length: 5 }, (_, index) => (
                    <Skeleton key={index} className="h-4 w-16" />
                ))}
            </div>
        </div>

        <div className="flex flex-col gap-4">
            {Array.from({ length: 3 }, (_, index) => (
                <div
                    key={index}
                    className="border border-sidebar-border bg-background p-4 sm:p-5"
                >
                    <div className="flex items-center justify-between gap-3">
                        <Skeleton className="h-5 w-20" />
                        <Skeleton className="h-4 w-36" />
                    </div>
                    <Skeleton className="mt-3 h-4 w-40" />
                    <div className="mt-3 flex flex-col gap-4 sm:flex-row">
                        <div className="flex shrink-0 gap-1.5">
                            <Skeleton className="size-14 rounded-none" />
                            <Skeleton className="size-14 rounded-none" />
                        </div>
                        <div className="min-w-0 flex-1 space-y-2">
                            <Skeleton className="h-4 w-1/2" />
                            <Skeleton className="h-3 w-2/3" />
                            <Skeleton className="h-4 w-28" />
                        </div>
                    </div>
                </div>
            ))}
        </div>
    </div>
);

/**
 * A disabled action that still has to explain itself.
 *
 * The button cannot take focus, so the wrapper does: without it a keyboard user
 * would meet a dead control with no way of finding out why it is dead. Every
 * locked Update and Cancel on the page goes through here rather than repeating
 * the wrapper, so the focus ring and the tooltip behave identically everywhere.
 */
const LockedAction = ({
    label,
    reason,
    className,
}: {
    label: string;
    reason: string;
    className: string;
}) => (
    <Tooltip>
        <TooltipTrigger asChild>
            <span
                tabIndex={0}
                className={`${className} focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none`}
            >
                <Button variant="outline" disabled className="w-full">
                    {label}
                </Button>
            </span>
        </TooltipTrigger>
        <TooltipContent>{reason}</TooltipContent>
    </Tooltip>
);

const InquiryCard = ({
    inquiry,
    generating,
    onGetInvoice,
    onCancel,
}: {
    inquiry: Inquiry;
    generating: boolean;
    onGetInvoice: (inquiry: Inquiry) => void;
    onCancel: (inquiry: Inquiry) => void;
}) => {
    /*
     * Every stone the inquiry mentions, in the order the client first picked it.
     * An inquiry can hold several projects and one stone can be in more than one
     * of them, so the ids are flattened and deduplicated: this row is asking which
     * materials are involved, and a stone wanted twice is still one material. The
     * counts and the specifications belong to the details page.
     */
    const inquiryStones = [
        ...new Set(
            inquiry.projects.flatMap((project) =>
                project.stones.map((selection) => selection.stoneId),
            ),
        ),
    ]
        .map((stoneId) => stones.find((stone) => stone.id === stoneId))
        .filter((stone): stone is Stone => stone !== undefined);

    const thumbnails = inquiryStones.slice(0, COLLAPSED_STONE_COUNT);
    const extraStones = inquiryStones.length - thumbnails.length;

    /*
     * The same stones, spelled out. A multi-project inquiry can run to most of the
     * catalogue, and a line naming all of them stops being a line a reader reads
     * and starts being a wall, so it is cut at the same three the thumbnails
     * above it show — the pictures and the words then account for the same set of
     * stones, and the count beside each is the same number of names left out.
     */
    const hasHiddenStones = inquiryStones.length > COLLAPSED_STONE_COUNT;
    const hiddenStoneCount = inquiryStones.length - COLLAPSED_STONE_COUNT;
    const visibleStoneNames = (
        hasHiddenStones
            ? inquiryStones.slice(0, COLLAPSED_STONE_COUNT)
            : inquiryStones
    ).map((stone) => stone.name);

    /*
     * An inquiry can ask for most of the catalogue, and every service is a tag on
     * the card. Past the first few, that row grows until it is the tallest thing
     * in the card and pushes the actions out of step with the rest of the list, so
     * the remainder is tallied instead.
     *
     * A tally rather than something to open: the card's job is to say what an
     * inquiry is, not to be the whole of it, and the details page is one click
     * away with the full list on it. The count is never hidden though, and the
     * line below the row keeps saying the true total on narrow screens where the
     * tags themselves do not fit.
     */
    const hasHiddenServices = inquiry.services.length > COLLAPSED_SERVICE_COUNT;
    const hiddenServiceCount =
        inquiry.services.length - COLLAPSED_SERVICE_COUNT;
    const visibleServices = hasHiddenServices
        ? inquiry.services.slice(0, COLLAPSED_SERVICE_COUNT)
        : inquiry.services;

    const canUpdate =
        inquiry.status === 'draft' || inquiry.status === 'pending';
    const updateBlockedReason = UPDATE_BLOCKED_REASON[inquiry.status];
    const cancelBlockedReason = CANCEL_BLOCKED_REASON[inquiry.status];
    const detailsHref = quoteShow.url({ inquiry: inquiry.id });
    const actionClassName = 'w-full sm:w-auto lg:w-full';

    return (
        <article className="border border-sidebar-border bg-background p-4 sm:p-5">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between lg:gap-6">
                <div className="min-w-0 flex-1 space-y-3">
                    <div className="flex items-center justify-between gap-3">
                        <StatusBadge status={inquiry.status} />
                        <time
                            dateTime={inquiry.createdAt}
                            className="shrink-0 text-xs text-secondary-foreground"
                        >
                            {dateFormatter.format(new Date(inquiry.createdAt))}
                        </time>
                    </div>

                    <p className="text-sm">
                        <span className="text-secondary-foreground">
                            Inquiry No:{' '}
                        </span>
                        <span className="font-bold text-foreground">
                            {inquiry.reference}
                        </span>
                    </p>

                    <div className="flex flex-col gap-4 sm:flex-row">
                        <div className="flex shrink-0 items-start gap-1.5">
                            {thumbnails.map((stone, index) => {
                                const isLastWithOverflow =
                                    extraStones > 0 &&
                                    index === thumbnails.length - 1;

                                return (
                                    <span
                                        key={stone.id}
                                        className="relative size-14 overflow-hidden border border-sidebar-border bg-background"
                                    >
                                        <img
                                            src={stone.thumbnail}
                                            alt=""
                                            loading="lazy"
                                            className="size-full object-cover"
                                        />
                                        {isLastWithOverflow ? (
                                            <span
                                                aria-hidden="true"
                                                className="absolute inset-0 flex items-center justify-center bg-foreground/60 text-xs font-bold text-background"
                                            >
                                                +{extraStones}
                                            </span>
                                        ) : null}
                                    </span>
                                );
                            })}
                        </div>

                        <div className="min-w-0 flex-1 space-y-2">
                            {/*
                             * The stones lead the card, and they are what an
                             * inquiry is recognised by here. A title above them
                             * would only have repeated the project name, which
                             * the details page already says in full, and pushed
                             * the line the reader scans for a row further down.
                             *
                             * The count runs on at the end of the sentence rather
                             * than sitting in a box of its own: it is a tally of
                             * what the line leaves out, not a stone, and nothing
                             * here is meant to be pressed. It is also set back to
                             * the quiet weight so it reads as arithmetic on the
                             * names rather than as one more name.
                             */}
                            <p className="truncate text-sm font-bold text-foreground">
                                {visibleStoneNames.join(', ')}
                                {hasHiddenStones ? (
                                    <span className="font-normal text-secondary-foreground">
                                        {', +'}
                                        {hiddenStoneCount}
                                    </span>
                                ) : null}
                            </p>

                            <div className="hidden flex-wrap gap-1.5 sm:flex">
                                {visibleServices.map((service) => (
                                    <span
                                        key={service}
                                        className={SERVICE_TAG_CLASS}
                                    >
                                        {service}
                                    </span>
                                ))}

                                {/* A tally of the rest, in the row's own shape. */}
                                {hasHiddenServices ? (
                                    <span className={SERVICE_TAG_CLASS}>
                                        +{hiddenServiceCount}
                                    </span>
                                ) : null}
                            </div>

                            <p className="text-[0.65rem] font-bold tracking-[0.05em] text-secondary-foreground uppercase sm:hidden">
                                {inquiry.services.length}{' '}
                                {inquiry.services.length === 1
                                    ? 'service'
                                    : 'services'}
                            </p>
                        </div>
                    </div>
                </div>

                <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap lg:w-52 lg:shrink-0 lg:flex-col">
                    <Button
                        asChild
                        variant="default"
                        className={actionClassName}
                    >
                        <Link href={detailsHref}>View details</Link>
                    </Button>

                    {canUpdate ? (
                        <Button
                            asChild
                            variant="outline"
                            className={actionClassName}
                        >
                            <Link href={detailsHref}>
                                {inquiry.status === 'draft'
                                    ? 'Continue'
                                    : 'Update'}
                            </Link>
                        </Button>
                    ) : updateBlockedReason ? (
                        <LockedAction
                            label="Update"
                            reason={updateBlockedReason}
                            className={actionClassName}
                        />
                    ) : null}

                    {/*
                     * Cancel sits directly under Update, and is offered for as
                     * long as the inquiry may still be pulled back. No
                     * destructive colour: the button reads as an ordinary outline
                     * action and the dialog is what makes the consequence clear
                     * before anything is committed.
                     */}
                    {CANCELLABLE_STATUSES.includes(inquiry.status) ? (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onCancel(inquiry)}
                            aria-label={`Cancel inquiry ${inquiry.reference}`}
                            className={actionClassName}
                        >
                            <X aria-hidden />
                            Cancel
                        </Button>
                    ) : cancelBlockedReason ? (
                        <LockedAction
                            label="Cancel"
                            reason={cancelBlockedReason}
                            className={actionClassName}
                        />
                    ) : null}

                    {inquiry.status === 'paid' ||
                    inquiry.status === 'completed' ? (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={generating}
                            onClick={() => onGetInvoice(inquiry)}
                            className={actionClassName}
                        >
                            {generating ? (
                                <Spinner className="motion-reduce:animate-none" />
                            ) : (
                                <Download aria-hidden />
                            )}
                            Get invoice
                        </Button>
                    ) : null}
                </div>
            </div>
        </article>
    );
};

const Quote = () => {
    // The stepper is an action rather than a page, so it is offered only to the
    // roles that may raise one, which is what `inquiries.create` grants.
    const inquire = useInquireRoute();
    const [isLoading, setIsLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState<StatusFilter>('all');
    const [sort, setSort] = useState<SortOrder>('newest');
    const [visibleCount, setVisibleCount] = useState(PAGE_SIZE);
    const [isLoadingMore, setIsLoadingMore] = useState(false);
    const [showFloatingInquire, setShowFloatingInquire] = useState(false);
    const [draftPromptOpen, setDraftPromptOpen] = useState(false);
    const [generatingId, setGeneratingId] = useState<string | null>(null);
    // The mock list is a module constant, so it is copied into state: a cancel
    // has to be able to change what is on screen, and the exported array must
    // never be mutated.
    const [items, setItems] = useState<Inquiry[]>(inquiries);
    const [cancelTarget, setCancelTarget] = useState<Inquiry | null>(null);
    const [cancellingId, setCancellingId] = useState<string | null>(null);
    /*
     * The dialog's wording is snapshotted when it opens rather than read off
     * `cancelTarget`, which is cleared the moment the dialog closes. The words
     * therefore never change on the way out, even for the frame in between.
     */
    const [cancelLabel, setCancelLabel] = useState({
        reference: '',
        title: '',
    });
    const loadMoreRef = useRef<HTMLDivElement | null>(null);
    const inquireRef = useRef<HTMLSpanElement | null>(null);

    useEffect(() => {
        const timer = window.setTimeout(() => setIsLoading(false), 700);

        return () => window.clearTimeout(timer);
    }, []);

    // Restore ?q= and ?status= after mount so a refresh or Back keeps the view.
    useEffect(() => {
        const { search: initialSearch, status: initialStatus } =
            readQueryState();

        if (initialSearch) {
            setSearch(initialSearch);
        }

        if (initialStatus !== 'all') {
            setStatus(initialStatus);
        }
    }, []);

    // Mirror the filters into the URL without stacking history entries.
    useEffect(() => {
        const timer = window.setTimeout(() => {
            const params = new URLSearchParams(window.location.search);

            if (search) {
                params.set('q', search);
            } else {
                params.delete('q');
            }

            if (status !== 'all') {
                params.set('status', status);
            } else {
                params.delete('status');
            }

            const queryString = params.toString();

            window.history.replaceState(
                window.history.state,
                '',
                `${window.location.pathname}${queryString ? `?${queryString}` : ''}`,
            );
        }, SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
    }, [search, status]);

    const draftInquiry = useMemo(
        () => items.find((inquiry) => inquiry.status === 'draft') ?? null,
        [items],
    );

    const normalizedSearch = search.trim().toLowerCase();

    const searchMatches = useMemo(
        () =>
            normalizedSearch === ''
                ? items
                : items.filter((inquiry) =>
                      inquiry.reference
                          .toLowerCase()
                          .includes(normalizedSearch),
                  ),
        [normalizedSearch, items],
    );

    const visibleInquiries = useMemo(() => {
        const filtered =
            status === 'all'
                ? searchMatches
                : searchMatches.filter((inquiry) => inquiry.status === status);

        return [...filtered].sort((a, b) =>
            sort === 'newest'
                ? b.createdAt.localeCompare(a.createdAt)
                : a.createdAt.localeCompare(b.createdAt),
        );
    }, [searchMatches, status, sort]);

    const remainingCount = Math.max(visibleInquiries.length - visibleCount, 0);

    // Grow the list as the sentinel scrolls into view instead of a manual button.
    useEffect(() => {
        const sentinel = loadMoreRef.current;

        if (!sentinel || remainingCount === 0 || isLoadingMore) {
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    setIsLoadingMore(true);
                }
            },
            { rootMargin: LOAD_MORE_ROOT_MARGIN },
        );

        observer.observe(sentinel);

        return () => observer.disconnect();
    }, [isLoading, remainingCount, isLoadingMore]);

    useEffect(() => {
        if (!isLoadingMore) {
            return;
        }

        const timer = window.setTimeout(() => {
            if (remainingCount > 0) {
                setVisibleCount((count) => count + PAGE_SIZE);
            }

            setIsLoadingMore(false);
        }, LOAD_MORE_DELAY_MS);

        return () => window.clearTimeout(timer);
    }, [isLoadingMore, remainingCount]);

    // Mirror the heading action into a floating button once it scrolls away.
    useEffect(() => {
        const trigger = inquireRef.current;

        if (!trigger) {
            return;
        }

        const observer = new IntersectionObserver(
            ([entry]) => setShowFloatingInquire(!entry.isIntersecting),
            { threshold: 0 },
        );

        observer.observe(trigger);

        return () => observer.disconnect();
    }, [isLoading]);

    const handleSearchChange = (value: string) => {
        setSearch(value);
        setVisibleCount(PAGE_SIZE);
    };

    const handleStatusChange = (value: StatusFilter) => {
        setStatus(value);
        setVisibleCount(PAGE_SIZE);
    };

    const handleSortChange = (value: SortOrder) => {
        setSort(value);
        setVisibleCount(PAGE_SIZE);
    };

    const handleInquire = () => {
        if (draftInquiry) {
            setDraftPromptOpen(true);

            return;
        }

        if (inquire) {
            router.visit(inquire.url());
        }
    };

    const handleGetInvoice = async (inquiry: Inquiry) => {
        setGeneratingId(inquiry.id);

        try {
            // TODO: wire to the quote.invoice route once the PDF endpoint exists.
            await new Promise((resolve) => window.setTimeout(resolve, 900));
            toast.info('Invoice generation is not available yet.');
        } finally {
            setGeneratingId(null);
        }
    };

    const openCancelPrompt = (inquiry: Inquiry) => {
        setCancelLabel({
            reference: inquiry.reference,
            title: inquiryTitle(inquiry),
        });
        setCancelTarget(inquiry);
    };

    const handleCancel = async (inquiry: Inquiry) => {
        // The dialog disables its own button, but a second click can still land
        // from a fast double press or a stale render.
        if (cancellingId !== null) {
            return;
        }

        const current = items.find((item) => item.id === inquiry.id);

        // Re-read the status instead of trusting the one the card was rendered
        // with: an inquiry that was paid or already cancelled while this dialog
        // was open must not be cancelled by a request made against a live one.
        if (!current || !CANCELLABLE_STATUSES.includes(current.status)) {
            setCancelTarget(null);
            toast.error('Only pending or quoted inquiries can be cancelled.');

            return;
        }

        setCancellingId(inquiry.id);

        try {
            /*
             * TODO: replace with a call to the cancel endpoint once it exists.
             * This wait is the only thing standing in for it, so swapping this
             * one function for a real request is the whole change.
             */
            await new Promise((resolve) => window.setTimeout(resolve, 900));

            setItems((previous) =>
                previous.map((item) =>
                    item.id === inquiry.id
                        ? { ...item, status: 'cancelled' }
                        : item,
                ),
            );
            setCancelTarget(null);
            toast.success(`Inquiry ${inquiry.reference} cancelled`);
        } catch {
            // Nothing changed, so the dialog stays open and the user can simply
            // press the button again rather than start the flow from scratch.
            toast.error("We couldn't cancel this inquiry. Please try again.");
        } finally {
            setCancellingId(null);
        }
    };

    const clearFilters = () => {
        setSearch('');
        setStatus('all');
        setVisibleCount(PAGE_SIZE);
    };

    if (isLoading) {
        return <QuoteSkeleton />;
    }

    const hasInquiries = items.length > 0;
    const shownInquiries = visibleInquiries.slice(0, visibleCount);
    const mayAddInquiry = inquire !== null;
    const isCancelling = cancellingId !== null;

    return (
        <div className="mx-auto w-full max-w-6xl">
            <PageHeading
                title="Quote"
                description="Your inquiries and their progress"
                action={
                    mayAddInquiry ? (
                        <span ref={inquireRef} className="inline-flex">
                            <Button type="button" onClick={handleInquire}>
                                <Plus aria-hidden />
                                Inquire
                            </Button>
                        </span>
                    ) : null
                }
            />

            <div className="mb-6 flex flex-col gap-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="relative w-full sm:max-w-xs">
                        <Search
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-secondary-foreground"
                            aria-hidden
                        />
                        <Input
                            type="search"
                            value={search}
                            onChange={(event) =>
                                handleSearchChange(event.target.value)
                            }
                            placeholder="Search by inquiry no."
                            aria-label="Search by inquiry number"
                            className="rounded-none pr-9 pl-9 [&::-webkit-search-cancel-button]:appearance-none"
                        />
                        {search ? (
                            <button
                                type="button"
                                onClick={() => handleSearchChange('')}
                                aria-label="Clear search"
                                className="absolute top-1/2 right-2 flex size-7 -translate-y-1/2 items-center justify-center border border-transparent text-secondary-foreground transition-opacity hover:opacity-70 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                            >
                                <X className="size-4" aria-hidden />
                            </button>
                        ) : null}
                    </div>

                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div className="sm:hidden">
                            <Select
                                value={status}
                                onValueChange={(value) =>
                                    handleStatusChange(value as StatusFilter)
                                }
                            >
                                <SelectTrigger
                                    className="w-full rounded-none"
                                    aria-label="Filter by status"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent className="rounded-none">
                                    {STATUS_TABS.map((tab) => (
                                        <SelectItem
                                            key={tab.value}
                                            value={tab.value}
                                            className="rounded-none"
                                        >
                                            {tab.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="w-full sm:w-44">
                            <Select
                                value={sort}
                                onValueChange={(value) =>
                                    handleSortChange(value as SortOrder)
                                }
                            >
                                <SelectTrigger
                                    className="w-full rounded-none"
                                    aria-label="Sort inquiries"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent className="rounded-none">
                                    <SelectItem
                                        value="newest"
                                        className="rounded-none"
                                    >
                                        Newest first
                                    </SelectItem>
                                    <SelectItem
                                        value="oldest"
                                        className="rounded-none"
                                    >
                                        Oldest first
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </div>

                <div
                    className="hidden items-center gap-5 overflow-x-auto py-1 text-sm sm:flex"
                    role="tablist"
                    aria-label="Filter by status"
                >
                    {STATUS_TABS.map((tab) => {
                        const isActive = status === tab.value;

                        return (
                            <button
                                key={tab.value}
                                type="button"
                                role="tab"
                                aria-selected={isActive}
                                onClick={() => handleStatusChange(tab.value)}
                                className={`flex shrink-0 items-center gap-1.5 whitespace-nowrap transition-opacity hover:opacity-70 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none ${
                                    isActive
                                        ? 'font-bold text-foreground'
                                        : 'text-secondary-foreground'
                                }`}
                            >
                                <span
                                    className={
                                        isActive
                                            ? 'underline decoration-foreground underline-offset-4'
                                            : ''
                                    }
                                >
                                    {tab.label}
                                </span>
                            </button>
                        );
                    })}
                </div>
            </div>

            {!hasInquiries ? (
                <EmptyState
                    title="No inquiries yet"
                    description="Start an inquiry and it will show up here with its progress."
                    action={
                        inquire ? (
                            <Button asChild>
                                <Link href={inquire.url()}>Inquire</Link>
                            </Button>
                        ) : null
                    }
                />
            ) : visibleInquiries.length === 0 ? (
                <EmptyState
                    title="No inquiries found"
                    description="No inquiry matches this search or status filter."
                    action={
                        <Button
                            type="button"
                            variant="outline"
                            onClick={clearFilters}
                        >
                            Clear filters
                        </Button>
                    }
                />
            ) : (
                <>
                    <div className="flex flex-col gap-4">
                        {shownInquiries.map((inquiry) => (
                            <InquiryCard
                                key={inquiry.id}
                                inquiry={inquiry}
                                generating={generatingId === inquiry.id}
                                onGetInvoice={handleGetInvoice}
                                onCancel={openCancelPrompt}
                            />
                        ))}
                    </div>

                    {isLoadingMore ? (
                        <div
                            role="status"
                            className="mt-6 flex items-center justify-center gap-2 text-sm text-secondary-foreground"
                        >
                            <Spinner
                                aria-hidden
                                className="motion-reduce:animate-none"
                            />
                            Loading more inquiries
                        </div>
                    ) : null}

                    {remainingCount > 0 ? (
                        <div
                            ref={loadMoreRef}
                            aria-hidden="true"
                            className="h-px"
                        />
                    ) : null}
                </>
            )}

            <Dialog
                open={cancelTarget !== null}
                onOpenChange={(open) => {
                    // Closing is refused while the request is in flight, which
                    // covers Escape, an outside click and the close button alike.
                    // Dismissing it then would leave the user unable to tell
                    // whether the cancel had gone through.
                    if (!open && !isCancelling) {
                        setCancelTarget(null);
                    }
                }}
            >
                <DialogContent className="rounded-none sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-lg font-bold tracking-[-0.03em]">
                            Cancel inquiry {cancelLabel.reference}?
                        </DialogTitle>
                        <DialogDescription>
                            This will cancel {cancelLabel.title}. A cancelled
                            inquiry can&apos;t be reopened. To continue,
                            you&apos;d need to start a new inquiry.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={isCancelling}
                            onClick={() => setCancelTarget(null)}
                        >
                            Keep inquiry
                        </Button>
                        <Button
                            type="button"
                            disabled={isCancelling}
                            onClick={() => {
                                // `handleCancel` settles its own failures, so
                                // there is nothing here for the caller to await.
                                if (cancelTarget) {
                                    void handleCancel(cancelTarget);
                                }
                            }}
                        >
                            {isCancelling ? (
                                <Spinner className="motion-reduce:animate-none" />
                            ) : (
                                <X aria-hidden />
                            )}
                            Cancel inquiry
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={draftPromptOpen} onOpenChange={setDraftPromptOpen}>
                <DialogContent className="rounded-none sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-lg font-bold tracking-[-0.03em]">
                            Continue your draft?
                        </DialogTitle>
                        <DialogDescription>
                            You have a saved draft
                            {draftInquiry ? ` (${draftInquiry.reference})` : ''}
                            . Continue where you left off, or start a new
                            inquiry.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setDraftPromptOpen(false);

                                if (inquire) {
                                    router.visit(inquire.url());
                                }
                            }}
                        >
                            Start over
                        </Button>
                        {inquire && (
                            <Button asChild>
                                <Link
                                    href={inquire.url({
                                        query: { draft: draftInquiry?.id },
                                    })}
                                    onClick={() => setDraftPromptOpen(false)}
                                >
                                    Continue draft
                                </Link>
                            </Button>
                        )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {showFloatingInquire && mayAddInquiry && !draftPromptOpen ? (
                <Button
                    type="button"
                    className="fixed right-4 bottom-4 z-50 sm:right-5 sm:bottom-5"
                    onClick={handleInquire}
                >
                    <Plus aria-hidden />
                    Inquire
                </Button>
            ) : null}
        </div>
    );
};

export default Quote;
