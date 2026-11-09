import { InfiniteScroll } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import StoneCard from '@/components/stone-card';
import { Skeleton } from '@/components/ui/skeleton';
import type { InquiryStoneOption } from '@/types/inquiry';

/** The row's spacing, the same as the grid it replaced. */
const ROW_GAP_CLASSES = 'gap-3.5 md:gap-4 lg:gap-5';

/**
 * A card's width: two to a view on a phone and three from `md`, as the grid had.
 * Measured against the row itself (`@container`), less the gaps between cards.
 */
const CARD_WIDTH_CLASSES =
    'w-[calc((100cqw-0.875rem)/2)] shrink-0 snap-start md:w-[calc((100cqw-2rem)/3)] lg:w-[calc((100cqw-2.5rem)/3)]';

/** How far an arrow moves the row: most of a view, so one card stays in sight. */
const ARROW_SCROLL_SHARE = 0.8;

/** How far a card may sit past the row's edge and still count as in view. */
const IN_VIEW_SLACK_PX = 2;

/**
 * An arrow over the pictures: a solid circle the size of a question's number,
 * its chevron in the middle.
 */
const ARROW_CLASSES =
    'absolute top-[calc(50%-1.5rem)] flex size-8 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full bg-primary text-primary-foreground shadow-md transition-opacity hover:opacity-90 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

/** Cards still on their way, for the first page, the next or a new filter. */
const SkeletonCards = ({ count }: { count: number }) =>
    Array.from({ length: count }, (_, index) => (
        <div
            key={index}
            className={`border border-sidebar-border ${CARD_WIDTH_CLASSES} ${
                index === 2 ? 'hidden md:block' : ''
            }`}
        >
            <Skeleton className="aspect-square w-full rounded-none" />
            <div className="space-y-2 p-3">
                <Skeleton className="h-4 w-3/4 rounded-none" />
                <Skeleton className="h-3 w-1/2 rounded-none" />
            </div>
        </div>
    ));

/**
 * A view of cards still on their way, in the shape of the row: two on a phone,
 * three from `md`.
 */
export const StoneGridSkeleton = () => (
    <div
        aria-hidden
        className={`@container flex overflow-hidden ${ROW_GAP_CLASSES}`}
        data-testid="stones-loading"
    >
        <SkeletonCards count={3} />
    </div>
);

type NextPageState = {
    hasMore: boolean;
    loading: boolean;
    fetch: () => void;
};

/**
 * What InfiniteScroll knows about the next page, passed up to the row so its
 * next arrow can stay while a page is loading or still to come. Drawn in the
 * slot after the cards, where it is also the skeleton while one loads.
 */
const NextPageSlot = ({
    onState,
    ...state
}: NextPageState & { onState: (state: NextPageState) => void }) => {
    const { hasMore, loading, fetch } = state;

    useEffect(() => {
        onState({ hasMore, loading, fetch });
    }, [hasMore, loading, fetch, onState]);

    return loading ? (
        <div className={`flex pl-3.5 md:pl-4 lg:pl-5 ${ROW_GAP_CLASSES}`}>
            <SkeletonCards count={3} />
        </div>
    ) : null;
};

/**
 * The stones to choose from in one row that scrolls sideways, so the details
 * under it stay where they are however many pages come in. They are fetched from
 * the server a page at a time as the reader reaches the right-hand end, like a
 * brand's Our Collection. The address keeps the filters but not the page: a
 * reload starts again from the first.
 */
const InquiryStoneGrid = ({
    stones,
    selectedIds,
    onToggle,
}: {
    stones: InquiryStoneOption[];
    selectedIds: string[];
    onToggle: (slug: string) => void;
}) => {
    const rowRef = useRef<HTMLDivElement>(null);
    const [canScrollBack, setCanScrollBack] = useState(false);
    const [isLastCardHidden, setIsLastCardHidden] = useState(false);
    const [hasNextPage, setHasNextPage] = useState(false);
    const [isLoadingNextPage, setIsLoadingNextPage] = useState(false);
    const fetchNextPageRef = useRef<() => void>(() => undefined);

    const readNextPage = useCallback((state: NextPageState) => {
        fetchNextPageRef.current = state.fetch;
        setHasNextPage(state.hasMore);
        setIsLoadingNextPage(state.loading);
    }, []);

    const readScroll = useCallback(() => {
        const row = rowRef.current;

        if (row === null) {
            return;
        }

        // An arrow shows only while the card at its end is not wholly in view.
        const cards = row.querySelectorAll<HTMLElement>('[data-stone-slot]');
        const first = cards[0];
        const last = cards[cards.length - 1];

        if (first === undefined || last === undefined) {
            setCanScrollBack(false);
            setIsLastCardHidden(false);

            return;
        }

        const view = row.getBoundingClientRect();

        setCanScrollBack(
            first.getBoundingClientRect().left < view.left - IN_VIEW_SLACK_PX,
        );
        setIsLastCardHidden(
            last.getBoundingClientRect().right > view.right + IN_VIEW_SLACK_PX,
        );
    }, []);

    const firstSlug = stones[0]?.slug;

    // A new filter is a new list, so it starts from its first card.
    useEffect(() => {
        rowRef.current?.scrollTo({ left: 0 });
    }, [firstSlug]);

    useEffect(() => {
        const row = rowRef.current;

        if (row === null) {
            return;
        }

        readScroll();
        const observer = new ResizeObserver(readScroll);
        observer.observe(row);

        return () => observer.disconnect();
    }, [readScroll, stones.length]);

    /*
     * Next stays while there is more to see, here or on the server: only the
     * last card of the last page in view takes it away.
     */
    const canScrollOn = isLastCardHidden || hasNextPage || isLoadingNextPage;

    const scrollOn = () => {
        // At the end of what is here, ask for the next page now.
        if (!isLastCardHidden && hasNextPage && !isLoadingNextPage) {
            fetchNextPageRef.current();
        }

        scrollBy(1);
    };

    const scrollBy = (direction: 1 | -1) => {
        const row = rowRef.current;

        row?.scrollBy({
            left: direction * row.clientWidth * ARROW_SCROLL_SHARE,
            behavior: 'smooth',
        });
    };

    return (
        <div className="relative min-w-0">
            {/*
             * As wide as the filters above it and no wider, so the cards start
             * where the filters do and are cut off at the content's edge rather
             * than in the page's padding: a picked card draws its ring inside
             * itself (StoneCard), so nothing needs room at the sides. Above and
             * below it keeps a little, for the focus ring. InfiniteScroll puts
             * its own start and end markers beside the cards, so the gap is on
             * the cards, not on this row.
             */}
            <div
                ref={rowRef}
                onScroll={readScroll}
                className="@container -my-1 flex snap-x snap-mandatory overflow-x-auto overscroll-x-contain pt-1 pb-3"
                data-testid="stones-row"
            >
                <InfiniteScroll
                    data="stones"
                    preserveUrl
                    className={`flex ${ROW_GAP_CLASSES}`}
                    next={({ hasMore, loading, fetch }) => (
                        <NextPageSlot
                            hasMore={hasMore}
                            loading={loading}
                            fetch={fetch}
                            onState={readNextPage}
                        />
                    )}
                >
                    {stones.map((stone) => (
                        <div
                            key={stone.slug}
                            className={CARD_WIDTH_CLASSES}
                            data-stone-slot
                        >
                            <StoneCard
                                name={stone.name}
                                typeName={stone.type.name}
                                thumbnail={stone.thumbnail}
                                selected={selectedIds.includes(stone.slug)}
                                onToggle={() => onToggle(stone.slug)}
                            />
                        </div>
                    ))}
                </InfiniteScroll>
            </div>

            {/*
             * Arrows on every screen, so it is plain the row goes on: a mouse has
             * no sideways wheel to speak of, and a swipe is not obvious either.
             * Each shows only while there is something its way.
             */}
            {canScrollBack ? (
                <button
                    type="button"
                    aria-label="Earlier stones"
                    onClick={() => scrollBy(-1)}
                    className={`${ARROW_CLASSES} left-2`}
                    data-testid="stones-scroll-back"
                >
                    <ChevronLeft aria-hidden className="size-5" />
                </button>
            ) : null}
            {canScrollOn ? (
                <button
                    type="button"
                    aria-label="More stones"
                    onClick={scrollOn}
                    className={`${ARROW_CLASSES} right-2`}
                    data-testid="stones-scroll-on"
                >
                    <ChevronRight aria-hidden className="size-5" />
                </button>
            ) : null}
        </div>
    );
};

export default InquiryStoneGrid;
