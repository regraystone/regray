/*
 * The Quote page's pure logic: what goes into the URL when the list is
 * searched or sorted, and how a long row is cut down to a tally.
 *
 * No imports, on purpose. The tests under tests/js run this file straight under
 * Node, where the `@/` alias does not exist.
 */

export type QuoteSort = 'newest' | 'oldest';

/**
 * The Quotations page's tabs (App\Enums\QuoteTab): where an inquiry stands for
 * the client. `all` lists every one, cancelled included.
 */
export type QuoteTab = 'all' | 'pending' | 'quoted' | 'accepted';

export const QUOTE_TABS: ReadonlyArray<{ value: QuoteTab; label: string }> = [
    { value: 'all', label: 'All' },
    { value: 'pending', label: 'Pending' },
    { value: 'quoted', label: 'Quoted' },
    { value: 'accepted', label: 'Accepted' },
];

export type QuoteFilters = {
    search: string;
    sort: QuoteSort;
    tab: QuoteTab;
};

export const QUOTE_DEFAULT_FILTERS: QuoteFilters = {
    search: '',
    sort: 'newest',
    tab: 'all',
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

    if (filters.sort !== QUOTE_DEFAULT_FILTERS.sort) {
        query.sort = filters.sort;
    }

    // A filter set made before the tabs has none: that is All.
    const tab = filters.tab ?? QUOTE_DEFAULT_FILTERS.tab;

    if (tab !== QUOTE_DEFAULT_FILTERS.tab) {
        query.tab = tab;
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
