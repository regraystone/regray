import { Link, router } from '@inertiajs/react';
import { Download, Plus, Search, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { toast } from 'sonner';
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
    stones,
    type FeedItem,
    type Inquiry,
    type InquiryStatus,
} from '@/data/constant';
import { useInquireRoute } from '@/hooks/use-inquire-route';
import { index as inquiryIndex } from '@/routes/inquiry';

const PAGE_SIZE = 10;
const SEARCH_DEBOUNCE_MS = 200;
const LOAD_MORE_DELAY_MS = 400;
const LOAD_MORE_ROOT_MARGIN = '200px';

type StatusFilter = 'all' | InquiryStatus;
type SortOrder = 'newest' | 'oldest';

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

const EmptyState = ({
    title,
    description,
    action,
}: {
    title: string;
    description: string;
    action: ReactNode;
}) => (
    <div className="flex flex-col items-center justify-center gap-3 border border-sidebar-border bg-background px-6 py-16 text-center">
        <p className="text-lg font-bold tracking-[-0.03em]">{title}</p>
        <p className="max-w-sm text-sm text-secondary-foreground">
            {description}
        </p>
        <div className="mt-1">{action}</div>
    </div>
);

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

const InquiryCard = ({
    inquiry,
    generating,
    onGetInvoice,
}: {
    inquiry: Inquiry;
    generating: boolean;
    onGetInvoice: (inquiry: Inquiry) => void;
}) => {
    const inquiryStones = inquiry.stoneIds
        .map((stoneId) => stones.find((stone) => stone.id === stoneId))
        .filter((stone): stone is FeedItem => stone !== undefined);

    const thumbnails = inquiryStones.slice(0, 3);
    const extraStones = inquiryStones.length - thumbnails.length;
    const canUpdate =
        inquiry.status === 'draft' || inquiry.status === 'pending';
    const detailsHref = inquiryIndex.url({ query: { inquiry: inquiry.id } });
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
                            <p className="truncate text-sm font-bold text-foreground">
                                {inquiry.projectName}
                            </p>
                            <p className="truncate text-xs text-secondary-foreground">
                                {inquiryStones
                                    .map((stone) => stone.name)
                                    .join(', ')}
                            </p>

                            <div className="hidden flex-wrap gap-1.5 sm:flex">
                                {inquiry.services.map((service) => (
                                    <span
                                        key={service}
                                        className="border border-sidebar-border px-2 py-0.5 text-[0.65rem] font-bold tracking-[0.05em] text-secondary-foreground uppercase"
                                    >
                                        {service}
                                    </span>
                                ))}
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
                    ) : (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <span
                                    tabIndex={0}
                                    className={`${actionClassName} focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none`}
                                >
                                    <Button
                                        variant="outline"
                                        disabled
                                        className="w-full"
                                    >
                                        Update
                                    </Button>
                                </span>
                            </TooltipTrigger>
                            <TooltipContent>
                                Locked once quoted. Message the team to request
                                changes.
                            </TooltipContent>
                        </Tooltip>
                    )}

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
    // roles that may raise one, which is what `add inquiry` grants.
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
        () => inquiries.find((inquiry) => inquiry.status === 'draft') ?? null,
        [],
    );

    const normalizedSearch = search.trim().toLowerCase();

    const searchMatches = useMemo(
        () =>
            normalizedSearch === ''
                ? inquiries
                : inquiries.filter((inquiry) =>
                      inquiry.reference
                          .toLowerCase()
                          .includes(normalizedSearch),
                  ),
        [normalizedSearch],
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

    const clearFilters = () => {
        setSearch('');
        setStatus('all');
        setVisibleCount(PAGE_SIZE);
    };

    if (isLoading) {
        return <QuoteSkeleton />;
    }

    const hasInquiries = inquiries.length > 0;
    const shownInquiries = visibleInquiries.slice(0, visibleCount);
    const mayAddInquiry = inquire !== null;

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
