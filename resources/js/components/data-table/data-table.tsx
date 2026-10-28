import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import EmptyState from '@/components/empty-state';
import { Input } from '@/components/ui/input';
import {
    Pagination,
    PaginationButton,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    clampPage,
    clampPageSize,
    pageNumbers,
    PAGE_SIZE_OPTIONS,
    rangeText,
    type TableMeta,
} from '@/lib/data-table';

const SEARCH_DEBOUNCE_MS = 300;

export type DataTableColumn<T> = {
    key: string;
    header: string;
    cell: (row: T) => ReactNode;
    className?: string;
};

/** The query a server list reads: what the toolbar and the pager hold. */
export type DataTableFilters = {
    search: string;
    perPage: number;
    page: number;
};

/**
 * A server-side table: search on the left of the toolbar, the page size on
 * the right, the rows, and the pager below them.
 *
 * Search (debounced), page and page size go in the query string, and the page
 * is reloaded partially (`only`), so only the list and its filters are read
 * again. Skeleton rows stand in while a reload runs; an empty list shows the
 * page's EmptyState. The columns are the page's; the Actions column comes last.
 */
function DataTable<T>({
    rows,
    meta,
    filters,
    columns,
    rowKey,
    only,
    searchPlaceholder = 'Search',
    emptyTitle,
    emptyDescription,
}: {
    rows: T[];
    meta: TableMeta;
    filters: DataTableFilters;
    columns: DataTableColumn<T>[];
    rowKey: (row: T) => string | number;
    /** The props a reload reads again: the list and its filters. */
    only: string[];
    searchPlaceholder?: string;
    emptyTitle: string;
    emptyDescription: string;
}) {
    const [search, setSearch] = useState(filters.search);
    const [isLoading, setIsLoading] = useState(false);
    const lastSearch = useRef(filters.search);

    const reload = (query: Partial<DataTableFilters>) => {
        const next = { ...filters, search, ...query };

        router.get(
            window.location.pathname,
            {
                ...(next.search.trim() === ''
                    ? {}
                    : { search: next.search.trim() }),
                perPage: clampPageSize(next.perPage),
                page: next.page,
            },
            {
                only,
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setIsLoading(true),
                onFinish: () => setIsLoading(false),
            },
        );
    };

    useEffect(() => {
        if (search === lastSearch.current) {
            return;
        }

        const timer = window.setTimeout(() => {
            lastSearch.current = search;
            reload({ search, page: 1 });
        }, SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
        // The reload reads the latest filters itself; only the typing restarts the timer.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const goTo = (page: number) =>
        reload({ page: clampPage(page, meta.lastPage) });

    return (
        <div className="space-y-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="relative w-full sm:max-w-xs">
                    <Search
                        aria-hidden
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-secondary-foreground"
                    />
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder={searchPlaceholder}
                        aria-label={searchPlaceholder}
                        className="rounded-none pl-9"
                    />
                </div>
                <div className="flex items-center gap-2 text-sm text-secondary-foreground">
                    <span>Rows per page</span>
                    <Select
                        value={String(clampPageSize(filters.perPage))}
                        onValueChange={(value) =>
                            reload({ perPage: Number(value), page: 1 })
                        }
                    >
                        <SelectTrigger
                            className="w-20 rounded-none"
                            aria-label="Rows per page"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {PAGE_SIZE_OPTIONS.map((size) => (
                                <SelectItem
                                    key={size}
                                    value={String(size)}
                                    className="rounded-none"
                                >
                                    {size}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>

            {!isLoading && rows.length === 0 ? (
                <EmptyState title={emptyTitle} description={emptyDescription} />
            ) : (
                <div className="rounded-md border border-sidebar-border">
                    <Table aria-busy={isLoading}>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                {columns.map((column) => (
                                    <TableHead
                                        key={column.key}
                                        className={column.className}
                                    >
                                        {column.header}
                                    </TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {isLoading
                                ? Array.from(
                                      {
                                          length: Math.max(
                                              1,
                                              Math.min(
                                                  rows.length ||
                                                      filters.perPage,
                                                  10,
                                              ),
                                          ),
                                      },
                                      (_, index) => (
                                          <TableRow
                                              key={`skeleton-${index}`}
                                              data-testid="data-table-skeleton"
                                          >
                                              {columns.map((column) => (
                                                  <TableCell key={column.key}>
                                                      <Skeleton className="h-4 w-full max-w-32" />
                                                  </TableCell>
                                              ))}
                                          </TableRow>
                                      ),
                                  )
                                : rows.map((row) => (
                                      <TableRow
                                          key={rowKey(row)}
                                          data-testid="data-table-row"
                                      >
                                          {columns.map((column) => (
                                              <TableCell
                                                  key={column.key}
                                                  className={column.className}
                                              >
                                                  {column.cell(row)}
                                              </TableCell>
                                          ))}
                                      </TableRow>
                                  ))}
                        </TableBody>
                    </Table>
                </div>
            )}

            <div className="flex flex-col items-center gap-3 sm:flex-row sm:justify-between">
                <p
                    className="text-sm text-secondary-foreground"
                    aria-live="polite"
                >
                    {rangeText(meta)}
                </p>
                {meta.lastPage > 1 ? (
                    <Pagination>
                        <PaginationContent>
                            <PaginationItem>
                                <PaginationPrevious
                                    disabled={meta.currentPage <= 1}
                                    onClick={() => goTo(meta.currentPage - 1)}
                                />
                            </PaginationItem>
                            {pageNumbers(meta.currentPage, meta.lastPage).map(
                                (page, index) => (
                                    <PaginationItem
                                        key={page ?? `gap-${index}`}
                                    >
                                        {page === null ? (
                                            <PaginationEllipsis />
                                        ) : (
                                            <PaginationButton
                                                isActive={
                                                    page === meta.currentPage
                                                }
                                                onClick={() => goTo(page)}
                                            >
                                                {page}
                                            </PaginationButton>
                                        )}
                                    </PaginationItem>
                                ),
                            )}
                            <PaginationItem>
                                <PaginationNext
                                    disabled={meta.currentPage >= meta.lastPage}
                                    onClick={() => goTo(meta.currentPage + 1)}
                                />
                            </PaginationItem>
                        </PaginationContent>
                    </Pagination>
                ) : null}
            </div>
        </div>
    );
}

export default DataTable;
