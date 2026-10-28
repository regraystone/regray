/*
 * The paging rules every DataTable shares. Pure, with no imports, for tests/js.
 * The server applies the same page sizes (App\Http\Requests\DataTableRequest).
 */

export const PAGE_SIZE_OPTIONS = [10, 25, 50, 100] as const;

export type PageSize = (typeof PAGE_SIZE_OPTIONS)[number];

export const DEFAULT_PAGE_SIZE: PageSize = 10;

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

/** A page size the table offers, or the default for anything else. */
export const clampPageSize = (value: unknown): PageSize => {
    const size = Number(value);

    return (PAGE_SIZE_OPTIONS as readonly number[]).includes(size)
        ? (size as PageSize)
        : DEFAULT_PAGE_SIZE;
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
 * The page numbers to draw: the first and last always, the current one with
 * a neighbour on each side, and a gap (`null`) wherever pages are skipped.
 */
export const pageNumbers = (
    current: number,
    last: number,
): Array<number | null> => {
    if (last <= 7) {
        return Array.from({ length: last }, (_, index) => index + 1);
    }

    const pages = new Set([1, last, current - 1, current, current + 1]);
    const shown = [...pages]
        .filter((page) => page >= 1 && page <= last)
        .sort((a, b) => a - b);

    return shown.flatMap((page, index) =>
        index > 0 && page - shown[index - 1] > 1 ? [null, page] : [page],
    );
};
