/*
 * The paging rules every DataTable shares. Pure, with no imports, for tests/js.
 * The server applies the same page sizes (App\Http\Requests\DataTableRequest).
 */

/** Fixed page size: removed rows-per-page selector. */
export const DEFAULT_PAGE_SIZE = 20 as const;

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
 * The page numbers to draw: first page, current page with one neighbor on each
 * side, last page, with gaps (`null`) wherever pages are skipped. Adaptive based
 * on current page position.
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

    // For larger ranges, show: first, current±1, last, with gaps
    const pages = new Set([1, last, current - 1, current, current + 1]);
    const shown = [...pages]
        .filter((page) => page >= 1 && page <= last)
        .sort((a, b) => a - b);

    return shown.flatMap((page, index) =>
        index > 0 && page - shown[index - 1] > 1 ? [null, page] : [page],
    );
};
