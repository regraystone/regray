/*
 * The Quote page's pure logic: what goes into the URL when the list is
 * searched or sorted, and how a long row is cut down to a tally.
 *
 * No imports, on purpose. The tests under tests/js run this file straight under
 * Node, where the `@/` alias does not exist.
 */

export type QuoteSort = 'newest' | 'oldest';

export type QuoteFilters = {
    search: string;
    sort: QuoteSort;
};

export const QUOTE_DEFAULT_FILTERS: QuoteFilters = {
    search: '',
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
