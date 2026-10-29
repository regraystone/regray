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
    DEFAULT_PAGE_SIZE,
    pageNumbers,
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

export type DataTableFilterOption = {
    value: string;
    label: string;
};

/** The query a server list reads: what the toolbar and the pager hold. */
export type DataTableFilters = {
    search: string;
    page: number;
};

/**
 * A server-side table: search on the left of the toolbar, optional status filter
 * on the right, rows, and pager below.
 *
 * Search (debounced) and page go in the query string, and the page is reloaded
 * partially (`only`), so only the list and its filters are read again. Skeleton
 * rows stand in while a reload runs; an empty list shows the page's EmptyState.
 * Uses a fixed page size (DEFAULT_PAGE_SIZE).
 * Every column is at least as wide as its widest value: where the screen is
 * narrower than that, the table scrolls sideways instead of squeezing them.
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
    statusFilter,
    statusOptions,
    onStatusFilterChange,
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
    statusFilter?: string;
    statusOptions?: DataTableFilterOption[];
    onStatusFilterChange?: (value: string) => void;
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
                {statusOptions && statusOptions.length > 0 && (
                    <div className="flex items-center gap-2">
                        <Select
                            value={statusFilter || 'all'}
                            onValueChange={onStatusFilterChange}
                        >
                            <SelectTrigger className="w-40 rounded-none">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {statusOptions.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                )}
            </div>

            {!isLoading && rows.length === 0 ? (
                <EmptyState title={emptyTitle} description={emptyDescription} />
            ) : (
                <div className="overflow-x-auto border border-sidebar-border">
                    <Table aria-busy={isLoading} className="w-full min-w-max">
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
                                                      DEFAULT_PAGE_SIZE,
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

            <div className="flex flex-col items-center gap-4 sm:flex-row sm:justify-between">
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
