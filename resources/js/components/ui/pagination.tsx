import { ChevronLeft, ChevronRight, MoreHorizontal } from 'lucide-react';
import type { ComponentProps } from 'react';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';

function Pagination({ className, ...props }: ComponentProps<'nav'>) {
    return (
        <nav
            role="navigation"
            aria-label="pagination"
            data-slot="pagination"
            className={cn('flex', className)}
            {...props}
        />
    );
}

function PaginationContent({ className, ...props }: ComponentProps<'ul'>) {
    return (
        <ul
            data-slot="pagination-content"
            className={cn('flex flex-row items-center gap-1', className)}
            {...props}
        />
    );
}

function PaginationItem(props: ComponentProps<'li'>) {
    return <li data-slot="pagination-item" {...props} />;
}

/** A page button: the current page is outlined, the rest are quiet. */
function PaginationButton({
    className,
    isActive = false,
    ...props
}: ComponentProps<'button'> & { isActive?: boolean }) {
    return (
        <button
            type="button"
            aria-current={isActive ? 'page' : undefined}
            data-slot="pagination-button"
            data-active={isActive}
            className={cn(
                buttonVariants({
                    variant: isActive ? 'outline' : 'ghost',
                    size: 'sm',
                }),
                'min-w-9 tracking-normal normal-case',
                className,
            )}
            {...props}
        />
    );
}

function PaginationPrevious({
    className,
    ...props
}: ComponentProps<'button'>) {
    return (
        <PaginationButton
            aria-label="Go to previous page"
            className={cn('gap-1', className)}
            {...props}
        >
            <ChevronLeft aria-hidden />
            <span className="hidden sm:inline">Previous</span>
        </PaginationButton>
    );
}

function PaginationNext({ className, ...props }: ComponentProps<'button'>) {
    return (
        <PaginationButton
            aria-label="Go to next page"
            className={cn('gap-1', className)}
            {...props}
        >
            <span className="hidden sm:inline">Next</span>
            <ChevronRight aria-hidden />
        </PaginationButton>
    );
}

function PaginationEllipsis({ className, ...props }: ComponentProps<'span'>) {
    return (
        <span
            aria-hidden
            data-slot="pagination-ellipsis"
            className={cn('flex size-9 items-center justify-center', className)}
            {...props}
        >
            <MoreHorizontal className="size-4" />
        </span>
    );
}

export {
    Pagination,
    PaginationButton,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationNext,
    PaginationPrevious,
};
