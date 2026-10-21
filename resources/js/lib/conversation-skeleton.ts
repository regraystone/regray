/*
 * When the conversation list and the thread show placeholders. Pure, with no
 * imports, so the tests under tests/js can run it straight under Node. It
 * mirrors lib/quote-skeleton.ts.
 */

/**
 * Which list request is in flight:
 * - `initial`: the page has no list yet.
 * - `filter`: a search or the All/Unread filter asked for the list again.
 * - `loadMore`: infinite scroll asked for the next page.
 */
export type ConversationLoadMode = 'initial' | 'filter' | 'loadMore';

export type ListSkeletonState = {
    /** Replace the list with placeholder rows. */
    rows: boolean;
    /** Add placeholder rows after the last real one. */
    footer: boolean;
    /** Dim the rows that are already there. */
    dim: boolean;
};

const NOTHING: ListSkeletonState = { rows: false, footer: false, dim: false };

/** How many placeholder rows a skeleton draws. */
export const SKELETON_ROW_COUNT = 6;

export const conversationListSkeleton = ({
    isFetching,
    hasRows,
    mode,
}: {
    isFetching: boolean;
    hasRows: boolean;
    mode: ConversationLoadMode;
}): ListSkeletonState => {
    if (!isFetching) {
        return NOTHING;
    }

    switch (mode) {
        case 'initial':
            return hasRows
                ? NOTHING
                : { rows: true, footer: false, dim: false };
        case 'filter':
            return hasRows
                ? { rows: false, footer: false, dim: true }
                : { rows: true, footer: false, dim: false };
        case 'loadMore':
            return { rows: false, footer: true, dim: false };
    }
};

/**
 * What the thread shows while it loads:
 * - the whole thread is a skeleton until its first page arrives,
 * - a small spinner sits at the top while older messages load,
 * - and nothing once the oldest message is in.
 */
export const threadLoadingState = ({
    hasLoaded,
    isLoadingOlder,
    hasMore,
}: {
    hasLoaded: boolean;
    isLoadingOlder: boolean;
    hasMore: boolean;
}): 'skeleton' | 'olderSpinner' | 'none' => {
    if (!hasLoaded) {
        return 'skeleton';
    }

    return isLoadingOlder && hasMore ? 'olderSpinner' : 'none';
};
