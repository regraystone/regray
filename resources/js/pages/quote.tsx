import { InfiniteScroll, Link, router, usePage } from '@inertiajs/react';
import { Plus, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import EmptyState from '@/components/empty-state';
import { StatusBadge } from '@/components/inquiry-status-badge';
import PageHeading from '@/components/page-heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { useInquiryEntry } from '@/hooks/use-inquiry-entry';
import {
    buildQuoteQuery,
    QUOTE_DEFAULT_FILTERS,
    tally,
    type QuoteFilters,
    type QuoteSort,
} from '@/lib/quote-filters';
import { SKELETON_CARD_COUNT, skeletonState } from '@/lib/quote-skeleton';
import { index as quoteIndex, show as quoteShow } from '@/routes/quote';
import type { QuoteInquiry, QuotePageProps } from '@/types/quote';

const SEARCH_DEBOUNCE_MS = 200;

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
    'rounded-md border border-sidebar-border px-2 py-0.5 text-[0.65rem] font-bold tracking-[0.05em] text-secondary-foreground uppercase';

const dateFormatter = new Intl.DateTimeFormat(undefined, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
});

/**
 * A placeholder shaped like `InquiryCard`: the same border, radius and padding,
 * the same rows in the same order — status and date, the reference, thumbnails
 * beside the stone names and service tags, and the View details button — so
 * nothing moves when the real cards arrive.
 */
const InquiryCardSkeleton = () => (
    <article className="rounded-md border border-sidebar-border bg-background p-4 sm:p-5">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between lg:gap-6">
            <div className="min-w-0 flex-1 space-y-3">
                <div className="flex items-center justify-between gap-3">
                    <Skeleton className="h-5 w-20" />
                    <Skeleton className="h-4 w-36" />
                </div>

                <Skeleton className="h-5 w-44" />

                <div className="flex flex-col gap-4 sm:flex-row">
                    <div className="flex shrink-0 items-start gap-1.5">
                        <Skeleton className="size-14 rounded-md" />
                        <Skeleton className="size-14 rounded-md" />
                        <Skeleton className="size-14 rounded-md" />
                    </div>

                    <div className="min-w-0 flex-1 space-y-2">
                        <Skeleton className="h-5 w-3/5" />
                        <div className="hidden flex-wrap gap-1.5 sm:flex">
                            <Skeleton className="h-[1.4rem] w-28 rounded-md" />
                            <Skeleton className="h-[1.4rem] w-24 rounded-md" />
                            <Skeleton className="h-[1.4rem] w-10 rounded-md" />
                        </div>
                        <Skeleton className="h-3 w-20 sm:hidden" />
                    </div>
                </div>
            </div>

            <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap lg:w-52 lg:shrink-0 lg:flex-col">
                <Skeleton className="h-11 w-full rounded-md sm:w-auto sm:min-w-28 lg:w-full" />
            </div>
        </div>
    </article>
);

/**
 * Placeholder cards, announced once. The boxes say nothing on their own, so the
 * one line a screen reader is given is the only thing to hear.
 */
const InquiryCardSkeletons = () => (
    <div role="status" aria-busy="true" className="flex flex-col gap-4">
        <span className="sr-only">Loading inquiries</span>
        {Array.from({ length: SKELETON_CARD_COUNT }, (_, index) => (
            <InquiryCardSkeleton key={index} />
        ))}
    </div>
);

/** The page before its first response: the heading, the controls and the cards. */
const QuoteSkeleton = () => (
    <div className="w-full">
        <div className="mb-4 flex flex-col gap-3 border-b border-sidebar-border pb-4 sm:mb-8 sm:flex-row sm:items-end sm:justify-between">
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
        </div>

        <InquiryCardSkeletons />
    </div>
);

const InquiryCard = ({ inquiry }: { inquiry: QuoteInquiry }) => {
    /*
     * The server sends each inquiry's stones once each, in the order the client
     * first picked them: an inquiry can hold several projects and one stone can be
     * in more than one of them, but this row is asking which materials are
     * involved, and a stone wanted twice is still one material. The counts and the
     * specifications belong to the details page.
     */
    const inquiryStones = inquiry.stones;

    const { shown: thumbnails, hidden: extraStones } = tally(
        inquiryStones,
        COLLAPSED_STONE_COUNT,
    );

    /*
     * The same stones, spelled out. A multi-project inquiry can run to most of the
     * catalogue, and a line naming all of them stops being a line a reader reads
     * and starts being a wall, so it is cut at the same three the thumbnails
     * above it show — the pictures and the words then account for the same set of
     * stones, and the count beside each is the same number of names left out.
     */
    const hasHiddenStones = extraStones > 0;
    const visibleStoneNames = thumbnails.map((stone) => stone.name);

    /*
     * An inquiry can ask for most of the catalogue, and every service is a tag on
     * the card. Past the first few, that row grows until it is the tallest thing
     * in the card and pushes the actions out of step with the rest of the list, so
     * the remainder is tallied instead. The count is never hidden though, and the
     * line below the row keeps saying the true total on narrow screens where the
     * tags themselves do not fit.
     */
    const { shown: visibleServices, hidden: hiddenServiceCount } = tally(
        inquiry.services,
        COLLAPSED_SERVICE_COUNT,
    );
    const hasHiddenServices = hiddenServiceCount > 0;

    const detailsHref = quoteShow.url({ reference: inquiry.reference });

    return (
        <article className="rounded-md border border-sidebar-border bg-background p-4 sm:p-5">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between lg:gap-6">
                <div className="min-w-0 flex-1 space-y-3">
                    <div className="flex items-center justify-between gap-3">
                        {/*
                         * The inquiry status, which is the one status the
                         * inquiry is shown at, then accounting's once the
                         * client has approved the quotation: before that there
                         * is nothing to pay, so the server sends no payment
                         * status.
                         */}
                        <span
                            className="flex min-w-0 flex-wrap items-center gap-1.5"
                            data-testid="quote-card-statuses"
                        >
                            <StatusBadge
                                group="inquiry"
                                status={inquiry.status}
                            />
                            {inquiry.paymentStatus ? (
                                <StatusBadge
                                    group="payment"
                                    status={inquiry.paymentStatus}
                                />
                            ) : null}
                        </span>
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
                                        key={stone.slug}
                                        className="relative size-14 overflow-hidden rounded-md border border-sidebar-border bg-background"
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
                             * inquiry is recognised by here. The count runs on at
                             * the end of the sentence rather than sitting in a box
                             * of its own: it is a tally of what the line leaves
                             * out, not a stone, and nothing here is meant to be
                             * pressed.
                             */}
                            <p className="truncate text-sm font-bold text-foreground">
                                {visibleStoneNames.join(', ')}
                                {hasHiddenStones ? (
                                    <span className="font-normal text-secondary-foreground">
                                        {', +'}
                                        {extraStones}
                                    </span>
                                ) : null}
                            </p>

                            <div className="hidden flex-wrap gap-1.5 sm:flex">
                                {visibleServices.map((service) => (
                                    <span
                                        key={service.id}
                                        className={SERVICE_TAG_CLASS}
                                    >
                                        {service.name}
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
                    <Button asChild className="w-full sm:w-auto lg:w-full">
                        <Link href={detailsHref}>View details</Link>
                    </Button>
                </div>
            </div>
        </article>
    );
};

const Quote = () => {
    // The stepper is an action rather than a page, so it is offered only to the
    // roles that may raise one, which is what `inquiries.create` grants. The
    // entry hook owns the click: a saved draft is offered the choice of
    // continuing it or starting fresh before the stepper is opened.
    const { inquire, isPromptOpen, onInquireClick, draftPrompt } =
        useInquiryEntry();
    const { inquiries, filters } = usePage<QuotePageProps>().props;
    const items = inquiries?.data ?? [];

    const [search, setSearch] = useState(filters.search);
    const [sort, setSort] = useState<QuoteSort>(filters.sort);
    const [isUpdating, setIsUpdating] = useState(false);
    const [showFloatingInquire, setShowFloatingInquire] = useState(false);
    const inquireRef = useRef<HTMLSpanElement | null>(null);
    const debounceRef = useRef<number | null>(null);

    /*
     * What the search timer reads when it fires: the sort as it is then, not as
     * it was when the key was pressed.
     */
    const latestRef = useRef<QuoteFilters>({ search, sort });
    latestRef.current = { search, sort };

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
    }, []);

    useEffect(
        () => () => {
            if (debounceRef.current !== null) {
                window.clearTimeout(debounceRef.current);
            }
        },
        [],
    );

    /*
     * The list is the server's: a changed filter asks for page one again and
     * replaces what was loaded, rather than filtering what is already here.
     * `replace` so a search does not stack a history entry per keystroke.
     */
    const applyFilters = (next: QuoteFilters) => {
        setIsUpdating(true);

        router.get(
            quoteIndex.url({ query: buildQuoteQuery(next) }),
            undefined,
            {
                onStart: () => setIsUpdating(true),
                replace: true,
                preserveScroll: true,
                preserveState: true,
                only: ['inquiries', 'hasMore', 'filters'],
                reset: ['inquiries'],
                onFinish: () => setIsUpdating(false),
            },
        );
    };

    const handleSearchChange = (value: string) => {
        setSearch(value);

        if (debounceRef.current !== null) {
            window.clearTimeout(debounceRef.current);
        }

        debounceRef.current = window.setTimeout(() => {
            applyFilters({ ...latestRef.current, search: value });
        }, SEARCH_DEBOUNCE_MS);
    };

    const handleSortChange = (value: QuoteSort) => {
        setSort(value);
        applyFilters({ ...latestRef.current, sort: value });
    };

    const clearFilters = () => {
        setSearch('');
        setSort('newest');
        applyFilters(QUOTE_DEFAULT_FILTERS);
    };

    const filterSkeleton = skeletonState({
        isFetching: isUpdating,
        hasCards: items.length > 0,
        mode: 'filter',
    });
    const initialSkeleton = skeletonState({
        isFetching: inquiries === undefined,
        hasCards: false,
        mode: 'initial',
    });

    const isFiltered = filters.search !== '';
    const mayAddInquiry = inquire !== null;

    if (initialSkeleton.cards) {
        return <QuoteSkeleton />;
    }

    return (
        <>
            <div className="w-full">
                <PageHeading
                    title="Quote"
                    description="Your inquiries and their progress"
                    action={
                        mayAddInquiry ? (
                            <span ref={inquireRef} className="inline-flex">
                                <Button type="button" onClick={onInquireClick}>
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
                                placeholder="Search inquiries"
                                aria-label="Search by inquiry number, project or stone"
                                className="rounded-none pr-9 pl-9 [&::-webkit-search-cancel-button]:appearance-none"
                            />
                            {search ? (
                                <button
                                    type="button"
                                    onClick={() => handleSearchChange('')}
                                    aria-label="Clear search"
                                    className="absolute top-1/2 right-2 flex size-7 -translate-y-1/2 items-center justify-center rounded-md border border-transparent text-secondary-foreground transition-opacity hover:opacity-70 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                >
                                    <X className="size-4" aria-hidden />
                                </button>
                            ) : null}
                        </div>

                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <div className="w-full sm:w-44">
                                <Select
                                    value={sort}
                                    onValueChange={(value) =>
                                        handleSortChange(value as QuoteSort)
                                    }
                                >
                                    <SelectTrigger
                                        className="w-full rounded-none"
                                        aria-label="Sort inquiries"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
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
                </div>

                {items.length === 0 ? (
                    filterSkeleton.cards ? (
                        <InquiryCardSkeletons />
                    ) : isFiltered ? (
                        <EmptyState
                            title="No inquiries found"
                            description="No inquiry matches this search."
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
                        <EmptyState
                            title="No inquiries yet"
                            description="Start an inquiry and it will show up here with its progress."
                            action={
                                inquire ? (
                                    <Button
                                        type="button"
                                        onClick={onInquireClick}
                                    >
                                        Inquire
                                    </Button>
                                ) : null
                            }
                        />
                    )
                ) : (
                    <InfiniteScroll
                        data="inquiries"
                        next={({ loading }) =>
                            skeletonState({
                                isFetching: loading,
                                hasCards: true,
                                mode: 'loadMore',
                            }).footer ? (
                                <div className="mt-4">
                                    <InquiryCardSkeletons />
                                </div>
                            ) : null
                        }
                    >
                        <div
                            aria-busy={filterSkeleton.dim}
                            aria-live="polite"
                            className={`flex flex-col gap-4 transition-opacity ${
                                filterSkeleton.dim ? 'opacity-60' : ''
                            }`}
                        >
                            {items.map((inquiry) => (
                                <InquiryCard
                                    key={inquiry.reference}
                                    inquiry={inquiry}
                                />
                            ))}
                        </div>
                    </InfiniteScroll>
                )}
            </div>

            {draftPrompt}

            {showFloatingInquire && mayAddInquiry && !isPromptOpen ? (
                <Button
                    type="button"
                    className="fixed right-4 bottom-4 z-50 sm:right-5 sm:bottom-5"
                    onClick={onInquireClick}
                >
                    <Plus aria-hidden />
                    Inquire
                </Button>
            ) : null}
        </>
    );
};

export default Quote;
