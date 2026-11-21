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
    clampPage,
    pageNumbers,
    rangeText,
    type TableMeta,
} from '@/lib/data-table';

/**
 * Below a list: which rows are showing ("Showing 11–20 of 42"), announced as
 * it changes, and the pager (pageNumbers) once there is more than one page.
 */
const DataTablePagination = ({
    meta,
    onPageChange,
}: {
    meta: TableMeta;
    onPageChange: (page: number) => void;
}) => {
    const goTo = (page: number) => onPageChange(clampPage(page, meta.lastPage));

    return (
        <div className="flex flex-col items-center gap-4 sm:flex-row sm:justify-between">
            <p className="text-sm text-secondary-foreground" aria-live="polite">
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
                                <PaginationItem key={page ?? `gap-${index}`}>
                                    {page === null ? (
                                        <PaginationEllipsis />
                                    ) : (
                                        <PaginationButton
                                            isActive={page === meta.currentPage}
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
    );
};

export default DataTablePagination;
