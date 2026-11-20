import { Link } from '@inertiajs/react';
import { ChevronRight, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * The grouped rows of the Profile and Settings hubs: a small spaced label,
 * then the rows in a bordered box, each at least 68px tall, with its icon in
 * a grey circle, its title and one line saying what is behind it. `grid`
 * sets the rows side by side from tablets up, each in its own box.
 */
export const HubSection = ({
    title,
    children,
    grid = false,
}: {
    title: string;
    children: ReactNode;
    grid?: boolean;
}) => (
    <section>
        <h3 className="mb-3 text-[0.6875rem] font-bold tracking-[0.16em] text-foreground uppercase">
            {title}
        </h3>

        <div
            className={
                grid
                    ? 'grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4 [&>*]:min-w-0 [&>*]:border [&>*]:border-border [&>*]:bg-background'
                    : 'divide-y divide-border border border-border bg-background'
            }
        >
            {children}
        </div>
    </section>
);

const rowClass =
    'group flex min-h-17 w-full cursor-pointer items-center justify-between gap-4 px-4 py-4 text-left transition-colors hover:bg-muted/40 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none focus-visible:ring-inset sm:px-5';

/**
 * One row: a link when given `href`, otherwise a button for `onClick`, such
 * as a row that opens a dialog. `tag` is a short word beside the title, such
 * as "Soon" on a row whose page is not built yet.
 */
export const HubRow = ({
    icon: Icon,
    title,
    description,
    href,
    onClick,
    dataTest,
    tag,
}: {
    icon: LucideIcon;
    title: string;
    description: string;
    dataTest?: string;
    tag?: string;
} & (
    | { href: string; onClick?: never }
    | { href?: never; onClick: () => void }
)) => {
    const body = (
        <>
            <div className="flex min-w-0 items-center gap-4">
                <span className="grid size-10 shrink-0 place-items-center rounded-full bg-muted">
                    <Icon className="size-4 text-foreground" aria-hidden />
                </span>
                <div className="min-w-0 space-y-0.5">
                    <div className="flex items-center gap-2 text-sm font-bold tracking-[-0.02em] text-foreground sm:text-base">
                        {title}
                        {tag ? (
                            <span className="rounded-full border border-border px-2 py-0.5 text-[0.625rem] font-semibold tracking-[0.08em] text-muted-foreground uppercase">
                                {tag}
                            </span>
                        ) : null}
                    </div>
                    <div className="truncate text-xs text-muted-foreground sm:text-sm">
                        {description}
                    </div>
                </div>
            </div>

            <ChevronRight
                className="size-4 shrink-0 text-secondary-foreground transition-transform duration-200 group-hover:translate-x-0.5"
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
