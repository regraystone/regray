/*
 * When the Quote page shows placeholders. Pure, with no imports, so the tests
 * under tests/js can run it straight under Node.
 */

/**
 * Which request is in flight:
 * - `initial`: the page has no data yet.
 * - `filter`: a search, status or sort change asked for the list again.
 * - `loadMore`: infinite scroll asked for the next page.
 */
export type QuoteLoadMode = 'initial' | 'filter' | 'loadMore';

export type SkeletonState = {
    /** Replace the list with placeholder cards. */
    cards: boolean;
    /** Add placeholder cards after the last real one. */
    footer: boolean;
    /** Dim the cards that are already there. */
    dim: boolean;
};

const NOTHING: SkeletonState = { cards: false, footer: false, dim: false };

/**
 * - Nothing is shown unless something is being fetched.
 * - The first load, with nothing to show yet, is all placeholders.
 * - A filter change dims the list that is there, and shows placeholders only
 *   when there is no list to dim.
 * - Loading more leaves the list alone and adds placeholders after it.
 */
export const skeletonState = ({
    isFetching,
    hasCards,
    mode,
}: {
    isFetching: boolean;
    hasCards: boolean;
    mode: QuoteLoadMode;
}): SkeletonState => {
    if (!isFetching) {
        return NOTHING;
    }

    switch (mode) {
        case 'initial':
            return hasCards
                ? NOTHING
                : { cards: true, footer: false, dim: false };
        case 'filter':
            return hasCards
                ? { cards: false, footer: false, dim: true }
                : { cards: true, footer: false, dim: false };
        case 'loadMore':
            return { cards: false, footer: true, dim: false };
    }
};

/** How many placeholder cards a skeleton draws. */
export const SKELETON_CARD_COUNT = 3;
