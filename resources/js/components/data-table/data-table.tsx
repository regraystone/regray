import { useState, type ReactNode } from 'react';
import DataTableFilter, {
    type DataTableFilterOption,
} from '@/components/data-table/data-table-filter';
import DataTablePagination from '@/components/data-table/data-table-pagination';
import DataTableSearch from '@/components/data-table/data-table-search';
import DataTableToolbar from '@/components/data-table/data-table-toolbar';
import { useDataTableQuery } from '@/components/data-table/use-data-table-query';
import EmptyState from '@/components/empty-state';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { DEFAULT_PAGE_SIZE, type TableMeta } from '@/lib/data-table';

export type { DataTableFilterOption };

export type DataTableColumn<T> = {
    key: string;
    header: string;
    cell: (row: T) => ReactNode;
    className?: string;
};

/** The query a server list reads: what the toolbar and the pager hold. */
export type DataTableFilters = {
    search: string;
    page: number;
    status?: string;
};

/**
 * A server-side table: search on the left of the toolbar, an optional status
 * filter on the right, rows, and the pager below. Built from the pieces beside
 * it (DataTableToolbar, DataTableSearch, DataTableFilter, DataTablePagination),
 * which a page may also use on their own.
 *
 * Search (debounced), status and page go in the query string through
 * useDataTableQuery, and the page is reloaded partially (`only`), so only the
 * list and its filters are read again. Skeleton rows stand in while a reload
 * runs; an empty list shows the page's EmptyState, or its "no match" one
 * while a search or filter is on. Every table shows DEFAULT_PAGE_SIZE rows.
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
    filteredEmptyTitle,
    filteredEmptyDescription,
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
    /** What an empty list says while a search or filter is on. */
    filteredEmptyTitle?: string;
    filteredEmptyDescription?: string;
    /** The status shown; the page's `filters.status` when left out. */
    statusFilter?: string;
    statusOptions?: DataTableFilterOption[];
    /** A page's own handling; the table reloads by itself when left out. */
    onStatusFilterChange?: (value: string) => void;
}) {
    const { update, isLoading } = useDataTableQuery(only);
    const [status, setStatus] = useState(
        statusFilter ?? filters.status ?? 'all',
    );
    const shownStatus = statusFilter ?? status;
    const isFiltered =
        filters.search.trim() !== '' ||
        (shownStatus !== 'all' && shownStatus !== '');

    const changeStatus = (value: string) => {
        if (onStatusFilterChange) {
            onStatusFilterChange(value);

            return;
        }

        setStatus(value);
        update({ status: value });
    };

    return (
        <div className="space-y-4">
            <DataTableToolbar
                search={
                    <DataTableSearch
                        value={filters.search}
                        placeholder={searchPlaceholder}
                        onSearch={(search) => update({ search })}
                    />
                }
                filters={
                    statusOptions && statusOptions.length > 0 ? (
                        <DataTableFilter
                            label="Status"
                            options={statusOptions}
                            value={shownStatus || 'all'}
                            onChange={changeStatus}
                        />
                    ) : null
                }
            />

            {!isLoading && rows.length === 0 ? (
                <EmptyState
                    title={
                        isFiltered && filteredEmptyTitle
                            ? filteredEmptyTitle
                            : emptyTitle
                    }
                    description={
                        isFiltered && filteredEmptyDescription
                            ? filteredEmptyDescription
                            : emptyDescription
                    }
                />
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
                                              rows.length || DEFAULT_PAGE_SIZE,
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

            <DataTablePagination
                meta={meta}
                onPageChange={(page) => update({ page })}
            />
        </div>
    );
}

export default DataTable;
