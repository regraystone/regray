import { Link } from '@inertiajs/react';
import { ChevronRight, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * The grouped rows of the Profile and Settings hubs: a small uppercase label
 * over a bordered group, each row at least 56px tall, its icon, title and one
 * line saying what is behind it.
 */
export const HubSection = ({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) => (
    <section>
        <h3 className="mb-2 text-[0.68rem] font-bold tracking-[0.16em] text-secondary-foreground uppercase">
            {title}
        </h3>

        <div className="divide-y divide-border overflow-hidden rounded-md border border-border bg-background">
            {children}
        </div>
    </section>
);

const rowClass =
    'group flex min-h-[56px] w-full cursor-pointer items-center justify-between gap-4 p-4 text-left transition-colors hover:bg-muted/40 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none focus-visible:ring-inset sm:p-5';

/**
 * One row: a link when given `href`, otherwise a button for `onClick`, such
 * as a row that opens a dialog.
 */
export const HubRow = ({
    icon: Icon,
    title,
    description,
    href,
    onClick,
    dataTest,
}: {
    icon: LucideIcon;
    title: string;
    description: string;
    dataTest?: string;
} & (
    | { href: string; onClick?: never }
    | { href?: never; onClick: () => void }
)) => {
    const body = (
        <>
            <div className="flex min-w-0 items-center gap-3.5 sm:gap-4">
                <Icon
                    className="size-5 shrink-0 text-secondary-foreground"
                    aria-hidden
                />
                <div className="min-w-0 space-y-0.5">
                    <div className="text-sm font-bold tracking-[-0.02em] text-foreground sm:text-base">
                        {title}
                    </div>
                    <div className="truncate text-xs text-muted-foreground sm:text-sm">
                        {description}
                    </div>
                </div>
            </div>

            <ChevronRight
                className="size-5 shrink-0 text-secondary-foreground transition-transform duration-200 group-hover:translate-x-0.5"
                aria-hidden
            />
        </>
    );

    return href !== undefined ? (
        <Link href={href} className={rowClass} data-test={dataTest}>
            {body}
        </Link>
    ) : (
        <button
            type="button"
            onClick={onClick}
            className={rowClass}
            data-test={dataTest}
        >
            {body}
        </button>
    );
};
