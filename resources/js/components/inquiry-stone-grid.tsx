import { InfiniteScroll } from '@inertiajs/react';
import StoneCard from '@/components/stone-card';
import { Skeleton } from '@/components/ui/skeleton';
import type { InquiryStoneOption } from '@/types/inquiry';

export const STONE_GRID_CLASSES =
    'grid grid-cols-2 gap-3.5 md:grid-cols-3 md:gap-4 lg:gap-5';

/**
 * A row of cards still on their way, for the next page or a new filter, in the
 * shape of a stone card. As many as a row holds: two on a phone, three from `md`.
 */
export const StoneGridSkeleton = () => (
    <div
        aria-hidden
        className={`mt-4 ${STONE_GRID_CLASSES}`}
        data-testid="stones-loading"
    >
        {Array.from({ length: 3 }, (_, index) => (
            <div
                key={index}
                className={`border border-sidebar-border ${
                    index === 2 ? 'hidden md:block' : ''
                }`}
            >
                <Skeleton className="aspect-square w-full rounded-none" />
                <div className="space-y-2 p-3">
                    <Skeleton className="h-4 w-3/4 rounded-none" />
                    <Skeleton className="h-3 w-1/2 rounded-none" />
                </div>
            </div>
        ))}
    </div>
);

/**
 * The stones to choose from, fetched from the server a page at a time as the
 * reader scrolls, like a brand's Our Collection. The address keeps the filters but
 * not the page: a reload starts again from the first.
 */
const InquiryStoneGrid = ({
    stones,
    selectedIds,
    onToggle,
}: {
    stones: InquiryStoneOption[];
    selectedIds: string[];
    onToggle: (slug: string) => void;
}) => (
    <InfiniteScroll
        data="stones"
        preserveUrl
        next={({ loading }) => (loading ? <StoneGridSkeleton /> : null)}
    >
        <div className={STONE_GRID_CLASSES}>
            {stones.map((stone) => (
                <StoneCard
                    key={stone.slug}
                    name={stone.name}
                    typeName={stone.type.name}
                    thumbnail={stone.thumbnail}
                    selected={selectedIds.includes(stone.slug)}
                    onToggle={() => onToggle(stone.slug)}
                />
            ))}
        </div>
    </InfiniteScroll>
);

export default InquiryStoneGrid;
