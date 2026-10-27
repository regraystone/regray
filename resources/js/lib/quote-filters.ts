/*
 * The Quote page's pure logic: the status tabs, what goes into the URL when the
 * list is filtered, and how a long row is cut down to a tally.
 *
 * No imports, on purpose. The tests under tests/js run this file straight under
 * Node, where the `@/` alias does not exist.
 */

/**
 * The Quote list's status tabs. Pending, closed and cancelled are the display
 * statuses of the same name; every other display status is in progress
 * (App\Enums\InquiryStatus::group()).
 */
export type QuoteStatusGroup =
    | 'pending'
    | 'in_progress'
    | 'closed'
    | 'cancelled';

export type QuoteStatusFilter = 'all' | QuoteStatusGroup;

/** The tabs in the order they are drawn, with what each is called. */
export const QUOTE_STATUS_TABS: ReadonlyArray<{
    value: QuoteStatusFilter;
    label: string;
}> = [
    { value: 'all', label: 'All' },
    { value: 'pending', label: 'Pending' },
    { value: 'in_progress', label: 'In progress' },
    { value: 'closed', label: 'Closed' },
    { value: 'cancelled', label: 'Cancelled' },
];

export type QuoteSort = 'newest' | 'oldest';

export type QuoteFilters = {
    search: string;
    status: QuoteStatusFilter;
    sort: QuoteSort;
};

export const QUOTE_DEFAULT_FILTERS: QuoteFilters = {
    search: '',
    status: 'all',
    sort: 'newest',
};

/**
 * The query string for a filtered list. A filter at its default is left out, so
 * the unfiltered page keeps the bare `/quote` URL, and a search is trimmed so
 * two spellings of one search are one URL.
 */
export const buildQuoteQuery = (
    filters: QuoteFilters,
): Record<string, string> => {
    const query: Record<string, string> = {};
    const search = filters.search.trim();

    if (search !== '') {
        query.search = search;
    }

    if (filters.status !== QUOTE_DEFAULT_FILTERS.status) {
        query.status = filters.status;
    }

    if (filters.sort !== QUOTE_DEFAULT_FILTERS.sort) {
        query.sort = filters.sort;
    }

    return query;
};

/**
 * A row cut to its first `limit` entries, with the number left out — the "+N"
 * a card shows after its stones or services.
 */
export const tally = <T>(
    items: readonly T[],
    limit: number,
): { shown: T[]; hidden: number } => ({
    shown: items.slice(0, limit),
    hidden: Math.max(items.length - limit, 0),
});
