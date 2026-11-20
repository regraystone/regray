/*
 * The paging rules every DataTable shares. Pure, with no imports, for tests/js.
 * The server applies the same page size (App\Http\Requests\DataTableRequest).
 */

/**
 * Every DataTable shows 10 rows a page, with no other size to pick; the server
 * ignores any `perPage` it is sent.
 */
export const DEFAULT_PAGE_SIZE = 10 as const;

/** The pagination block a server list sends beside its rows. */
export type TableMeta = {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
    /** The first row's position, 1-based; null when there are no rows. */
    from: number | null;
    to: number | null;
};

/** A page between 1 and the last one. */
export const clampPage = (page: unknown, lastPage: number): number => {
    const value = Math.trunc(Number(page));

    if (!Number.isFinite(value) || value < 1) {
        return 1;
    }

    return Math.min(value, Math.max(1, lastPage));
};

/** "Showing 11–20 of 42", or "Showing 0 of 0" with no rows. */
export const rangeText = (meta: TableMeta): string =>
    meta.from === null || meta.to === null || meta.total === 0
        ? 'Showing 0 of 0'
        : `Showing ${meta.from}–${meta.to} of ${meta.total}`;

/**
 * The page numbers to draw, with a gap (`null`) where pages are skipped.
 *
 * Seven pages or fewer are all shown. Past that: the first two, the last two,
 * and the current page with one neighbour each side; near either end, the
 * third page from it as well, so the run there is three long. A gap that
 * would hide a single page shows that page instead.
 *
 * 10 pages: on 1, `1 2 3 … 9 10`; on 6, `1 2 … 5 6 7 8 9 10`.
 */
export const pageNumbers = (
    current: number,
    last: number,
): Array<number | null> => {
    if (last <= 1) {
        return [];
    }

    if (last <= 7) {
        return Array.from({ length: last }, (_, index) => index + 1);
    }

    const pages = new Set([
        1,
        2,
        last - 1,
        last,
        current - 1,
        current,
        current + 1,
    ]);

    if (current <= 2) {
        pages.add(3);
    }

    if (current >= last - 1) {
        pages.add(last - 2);
    }

    const shown = [...pages]
        .filter((page) => page >= 1 && page <= last)
        .sort((a, b) => a - b);

    return shown.flatMap((page, index) => {
        const gap = index === 0 ? 1 : page - shown[index - 1];

        if (gap === 2) {
            return [page - 1, page];
        }

        return gap > 2 ? [null, page] : [page];
    });
};

/**
 * How a row's actions are drawn: up to `maxInline` as icon buttons, side by
 * side; more than that as the first (view) beside a menu holding the rest.
 */
export const splitRowActions = <T>(
    actions: readonly T[],
    maxInline = 2,
): { inline: T[]; menu: T[] } =>
    actions.length <= maxInline
        ? { inline: [...actions], menu: [] }
        : { inline: actions.slice(0, 1), menu: actions.slice(1) };

/** A query value a list page keeps in its URL; empty ones are left out. */
export type QueryValue = string | number | null | undefined;

/**
 * The query a list reloads with: the one it has, with `changes` on top. A
 * search or filter starts again from page 1 unless `changes` names a page;
 * an empty value (`''`, null, or the filter's `all`) drops its key, so the URL
 * only holds what narrows the list.
 */
export const nextQuery = (
    current: Readonly<Record<string, string>>,
    changes: Readonly<Record<string, QueryValue>>,
): Record<string, string> => {
    const merged: Record<string, QueryValue> = {
        ...current,
        page: 1,
        ...changes,
    };

    return Object.fromEntries(
        Object.entries(merged)
            .filter(
                (entry): entry is [string, string | number] =>
                    entry[1] !== null &&
                    entry[1] !== undefined &&
                    String(entry[1]).trim() !== '' &&
                    entry[1] !== 'all',
            )
            .map(([key, value]) => [key, String(value).trim()]),
    );
};
