import { InfiniteScroll, Link, router, usePage } from '@inertiajs/react';
import { MoreHorizontal, Package, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import CancelInquiryDialog from '@/components/cancel-inquiry-button';
import EmptyState from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import { buildCardActions, mainButtonLabel } from '@/lib/quote-actions';
import {
    buildQuoteQuery,
    QUOTE_DEFAULT_FILTERS,
    QUOTE_TABS,
    tally,
    type QuoteFilters,
    type QuoteSort,
    type QuoteTab,
} from '@/lib/quote-filters';
import { INQUIRY_STATUS_LABELS } from '@/lib/inquiry-statuses';
import { SKELETON_CARD_COUNT, skeletonState } from '@/lib/quote-skeleton';
import {
    index as quoteIndex,
    show as quoteShow,
    edit as quoteEdit,
} from '@/routes/quote';
import type { QuoteInquiry, QuotePageProps } from '@/types/quote';

const SEARCH_DEBOUNCE_MS = 200;

/** How many stones a card names before it tallies the rest. */
const NAMED_STONE_COUNT = 2;

/** How many services a card names before it tallies the rest. */
const NAMED_SERVICE_COUNT = 1;

const dateFormatter = new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
});

/**
 * The status pill's colours, by where the inquiry stands: peach while it waits
 * for a quotation, blue once quoted, green once accepted, grey when it is
 * cancelled or being refunded.
 */
const STAGE_PILL: Record<NonNullable<QuoteInquiry['stage']> | 'none', string> =
    {
        pending: 'bg-[#fbe8df] text-[#9a4a26]',
        quoted: 'bg-[#e1ecfa] text-[#2c5a94]',
        accepted: 'bg-[#dff1e4] text-[#2b6d3f]',
        none: 'bg-muted text-secondary-foreground',
    };

/** A list cut to its first few, with "+N" for the rest. */
const named = (names: string[], limit: number): string => {
    const { shown, hidden } = tally(names, limit);

    return hidden > 0 ? `${shown.join(', ')} +${hidden}` : shown.join(', ');
};

/** A placeholder shaped like `InquiryCard`, so nothing moves when the cards arrive. */
const InquiryCardSkeleton = () => (
    <article className="rounded-md border border-sidebar-border bg-background p-4 sm:p-5">
        <div className="flex items-center justify-between gap-3">
            <Skeleton className="h-4 w-20" />
            <Skeleton className="h-5 w-20 rounded-full" />
        </div>
        <div className="mt-3 flex gap-4">
            <Skeleton className="size-24 shrink-0 rounded-md" />
            <div className="min-w-0 flex-1 space-y-2">
                <Skeleton className="h-5 w-3/5" />
                <Skeleton className="h-4 w-2/5" />
                <Skeleton className="h-4 w-1/3" />
                <Skeleton className="h-4 w-24" />
            </div>
        </div>
        <div className="mt-4 flex items-center justify-between gap-3">
            <Skeleton className="h-11 w-44" />
            <Skeleton className="size-9" />
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

/** The page before its first response: the heading, the tabs and the cards. */
const QuoteSkeleton = () => (
    <div className="w-full">
        <div className="mb-5 flex items-start justify-between gap-4">
            <div className="space-y-2">
                <Skeleton className="h-8 w-44" />
                <Skeleton className="h-4 w-64" />
            </div>
            <Skeleton className="h-11 w-36" />
        </div>
        <Skeleton className="mb-5 h-11 w-full" />
        <InquiryCardSkeletons />
    </div>
);

/**
 * One inquiry, as the Quotations design draws it: its number and a coloured
 * status, the first stone's photo beside its title, its stones, its service
 * and the day it was sent, then one main button (named for where it stands,
 * opening its details) and a ⋯ menu holding the rest of what the client may
 * do to it (Update, Cancel, Get invoice), as the server allows.
 */
const InquiryCard = ({ inquiry }: { inquiry: QuoteInquiry }) => {
    const [isCancelling, setIsCancelling] = useState(false);
    const photo = inquiry.stones[0]?.thumbnail ?? null;
    const stones = named(
        inquiry.stones.map((stone) => stone.name),
        NAMED_STONE_COUNT,
    );
    const services = named(
        inquiry.services.map((service) => service.name),
        NAMED_SERVICE_COUNT,
    );
    const more = buildCardActions(inquiry.actions).filter(
        (action) => action.key !== 'view',
    );
    const canView =
        inquiry.actions.view.visible && inquiry.actions.view.enabled;

    return (
        <article
            className="rounded-md border border-sidebar-border bg-background p-4 sm:p-5"
            data-testid="quote-card"
        >
            <div className="flex items-center justify-between gap-3">
                <p className="text-sm font-medium tracking-[0.02em]">
                    {inquiry.reference}
                </p>
                <span
                    className={`rounded-full px-2.5 py-0.5 text-[0.6875rem] font-semibold tracking-[0.08em] uppercase ${
                        STAGE_PILL[inquiry.stage ?? 'none']
                    }`}
                    data-testid="quote-card-status"
                >
                    {INQUIRY_STATUS_LABELS[inquiry.status]}
                </span>
            </div>

            <div className="mt-3 flex gap-4">
                <div className="grid size-24 shrink-0 place-items-center overflow-hidden rounded-md bg-muted">
                    {photo ? (
                        <img
                            src={photo}
                            alt=""
                            loading="lazy"
                            className="size-full object-cover"
                        />
                    ) : (
                        <Package
                            aria-hidden
                            strokeWidth={1.5}
                            className="size-7 text-secondary-foreground"
                        />
                    )}
                </div>
                <div className="min-w-0 flex-1 space-y-1">
                    <h2 className="truncate text-lg leading-snug font-medium">
                        {inquiry.title}
                    </h2>
                    {stones ? (
                        <p className="truncate text-sm text-secondary-foreground">
                            {stones}
                        </p>
                    ) : null}
                    {services ? (
                        <p className="truncate text-sm text-secondary-foreground">
                            {services}
                        </p>
                    ) : null}
                    <p className="text-sm text-secondary-foreground">
                        <time dateTime={inquiry.createdAt}>
                            {dateFormatter.format(new Date(inquiry.createdAt))}
                        </time>
                    </p>
                </div>
            </div>

            <div className="mt-4 flex items-center justify-between gap-3">
                {canView ? (
                    <Button
                        asChild
                        variant="outline"
                        className="h-11 min-w-44 rounded-none px-6 text-[0.8125rem] tracking-[0.06em] uppercase"
                    >
                        <Link
                            href={quoteShow.url({
                                reference: inquiry.reference,
                            })}
                        >
                            {mainButtonLabel(inquiry.stage)}
                        </Link>
                    </Button>
                ) : (
                    <span />
                )}

                {more.length > 0 ? (
                    <DropdownMenu modal={false}>
                        <DropdownMenuTrigger asChild>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-10"
                                aria-label={`More for ${inquiry.reference}`}
                                data-testid="quote-card-more"
                            >
                                <MoreHorizontal
                                    className="size-5"
                                    aria-hidden
                                />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-44">
                            {more.map((action) =>
                                action.key === 'update' ? (
                                    <DropdownMenuItem key={action.key} asChild>
                                        <Link
                                            href={quoteEdit.url({
                                                reference: inquiry.reference,
                                            })}
                                        >
                                            {action.label}
                                        </Link>
                                    </DropdownMenuItem>
                                ) : action.key === 'cancel' ? (
                                    <DropdownMenuItem
                                        key={action.key}
                                        variant="destructive"
                                        onSelect={() => setIsCancelling(true)}
                                    >
                                        {action.label}
                                    </DropdownMenuItem>
                                ) : (
                                    <DropdownMenuItem key={action.key} asChild>
                                        <Link href="#">{action.label}</Link>
                                    </DropdownMenuItem>
                                ),
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                ) : null}
            </div>

            <CancelInquiryDialog
                reference={inquiry.reference}
                open={isCancelling}
                onOpenChange={setIsCancelling}
            />
        </article>
    );
};

/**
 * Quotations: the client's inquiries and their quotations, as Sir Mark's
 * design draws it. The title and New inquiry, then the tabs (All, Pending,
 * Quoted, Accepted, filtered on the server by App\Enums\QuoteTab), a slim row
 * to search and sort, and the cards.
 */
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
    const [tab, setTab] = useState<QuoteTab>(filters.tab ?? 'all');
    const [isUpdating, setIsUpdating] = useState(false);
    const [showFloatingInquire, setShowFloatingInquire] = useState(false);
    const inquireRef = useRef<HTMLSpanElement | null>(null);
    const debounceRef = useRef<number | null>(null);

    /*
     * What the search timer reads when it fires: the sort and tab as they are
     * then, not as they were when the key was pressed.
     */
    const latestRef = useRef<QuoteFilters>({ search, sort, tab });
    latestRef.current = { search, sort, tab };

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

    const handleTabChange = (value: QuoteTab) => {
        setTab(value);
        applyFilters({ ...latestRef.current, tab: value });
    };

    const clearFilters = () => {
        setSearch('');
        setSort('newest');
        setTab('all');
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

    const isFiltered =
        filters.search !== '' || (filters.tab ?? 'all') !== 'all';
    const mayAddInquiry = inquire !== null;

    if (initialSkeleton.cards) {
        return <QuoteSkeleton />;
    }

    return (
        <>
            <div className="w-full">
                <div className="mb-5 flex items-start justify-between gap-4">
                    <div className="min-w-0">
                        <h1 className="text-[clamp(1.75rem,3vw,2.25rem)] leading-tight font-medium tracking-[-0.01em]">
                            Quotations
                        </h1>
                        <p className="mt-1 text-sm text-secondary-foreground">
                            Manage your inquiries and quotations.
                        </p>
                    </div>
                    {mayAddInquiry ? (
                        <span ref={inquireRef} className="inline-flex shrink-0">
                            <Button
                                type="button"
                                className="h-11 rounded-md px-5 text-[0.8125rem] tracking-[0.06em] uppercase"
                                onClick={onInquireClick}
                            >
                                New inquiry
                            </Button>
                        </span>
                    ) : null}
                </div>

                <div
                    role="tablist"
                    aria-label="Show inquiries"
                    className="mb-4 grid grid-cols-4 gap-2"
                >
                    {QUOTE_TABS.map((option) => {
                        const isActive = tab === option.value;

                        return (
                            <button
                                key={option.value}
                                type="button"
                                role="tab"
                                aria-selected={isActive}
                                onClick={() => handleTabChange(option.value)}
                                className={`cursor-pointer border-b-2 pt-1 pb-2.5 text-[0.9375rem] transition-colors ${
                                    isActive
                                        ? 'border-foreground font-semibold text-foreground'
                                        : 'border-sidebar-border text-secondary-foreground hover:text-foreground'
                                }`}
                            >
                                {option.label}
                            </button>
                        );
                    })}
                </div>

                <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative w-full sm:flex-1">
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
                            className="h-10 rounded-none pr-9 pl-9 [&::-webkit-search-cancel-button]:appearance-none"
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

                    <div className="w-full sm:w-44">
                        <Select
                            value={sort}
                            onValueChange={(value) =>
                                handleSortChange(value as QuoteSort)
                            }
                        >
                            <SelectTrigger
                                className="w-full rounded-none data-[size=default]:h-10"
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

                {items.length === 0 ? (
                    filterSkeleton.cards ? (
                        <InquiryCardSkeletons />
                    ) : isFiltered ? (
                        <EmptyState
                            title="No inquiries found"
                            description="No inquiry matches this search or tab."
                            action={
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={clearFilters}
                                >
                                    Show all
                                </Button>
                            }
                        />
                    ) : (
                        <EmptyState
                            title="No inquiries yet"
                            description="Start an inquiry and it will show up here with its progress."
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
                    New inquiry
                </Button>
            ) : null}
        </>
    );
};

export default Quote;
